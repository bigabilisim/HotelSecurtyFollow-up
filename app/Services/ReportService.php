<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Report;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use PDO;

final class ReportService
{
    public function runDue(int $limit = 10): array
    {
        (new Report())->ensureReady();

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM report_schedules
             WHERE is_active = 1
               AND deleted_at IS NULL
               AND (next_run_at IS NULL OR next_run_at <= NOW())
             ORDER BY COALESCE(next_run_at, created_at) ASC
             LIMIT ' . max(1, min($limit, 50))
        );
        $stmt->execute();

        $summary = ['processed' => 0, 'created' => 0, 'failed' => 0, 'notifications' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $schedule) {
            $summary['processed']++;
            try {
                $result = $this->runSchedule((int) $schedule['id']);
                $summary['created']++;
                $summary['notifications'] += $result['notifications'];
            } catch (\Throwable) {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    public function runSchedule(int $scheduleId): array
    {
        (new Report())->ensureReady();

        $schedule = $this->schedule($scheduleId);
        if (!$schedule) {
            throw new \RuntimeException('Rapor planı bulunamadı.');
        }

        [$periodStart, $periodEnd] = $this->periodFor((string) $schedule['report_type']);
        $data = $this->collectData($periodStart, $periodEnd);
        $filePath = $this->writeReport($schedule, $periodStart, $periodEnd, $data);
        $summary = $this->summaryText($data);
        $isPdf = strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'pdf';
        $eventType = (string) $schedule['report_type'] . '_report';
        $payload = [
            'report_name' => $schedule['name'],
            'report_type' => $schedule['report_type'],
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'report_summary' => $summary,
            'file_path' => $filePath,
            'attachment_path' => $isPdf ? $filePath : '',
            'attachment_name' => $isPdf ? basename($filePath) : '',
            'message' => $schedule['name'] . ' hazırlandı.',
        ];
        $queued = (new NotificationService())->queueSystemEvent($eventType, $payload);
        $queued += $this->queueUserReportSubscriptions($schedule, $eventType, $payload);

        $logStmt = Database::connection()->prepare(
            'INSERT INTO report_logs (
                schedule_id,
                report_type,
                period_start,
                period_end,
                file_path,
                status
             ) VALUES (
                :schedule_id,
                :report_type,
                :period_start,
                :period_end,
                :file_path,
                :status
             )'
        );
        $logStmt->execute([
            'schedule_id' => (int) $schedule['id'],
            'report_type' => $schedule['report_type'],
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'file_path' => $filePath,
            'status' => 'created',
        ]);

        $this->touchSchedule((int) $schedule['id'], (string) $schedule['report_type'], (string) $schedule['run_time'], $schedule['day_of_month'] ? (int) $schedule['day_of_month'] : null);

        return ['file_path' => $filePath, 'notifications' => $queued];
    }

    private function schedule(int $scheduleId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM report_schedules WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $scheduleId]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

        return $schedule ?: null;
    }

    private function periodFor(string $type): array
    {
        if ($type === 'monthly') {
            return [date('Y-m-01'), date('Y-m-t')];
        }

        if ($type === 'weekly') {
            return [
                date('Y-m-d', strtotime('monday this week')),
                date('Y-m-d', strtotime('sunday this week')),
            ];
        }

        return [date('Y-m-d'), date('Y-m-d')];
    }

    private function collectData(string $periodStart, string $periodEnd): array
    {
        $pdo = Database::connection();
        $rangeStart = $periodStart . ' 00:00:00';
        $rangeEnd = $periodEnd . ' 23:59:59';

        $counts = $this->singleRow(
            'SELECT
                COUNT(*) AS total_entries,
                SUM(CASE WHEN exit_at IS NOT NULL THEN 1 ELSE 0 END) AS total_exits,
                SUM(CASE WHEN exit_at IS NULL THEN 1 ELSE 0 END) AS still_inside,
                SUM(CASE WHEN status IN ("overdue", "department_asked", "escalated") THEN 1 ELSE 0 END) AS overdue_count
             FROM visits
             WHERE entry_at BETWEEN :start_at AND :end_at',
            ['start_at' => $rangeStart, 'end_at' => $rangeEnd]
        );

        $categoryStmt = $pdo->prepare(
            'SELECT vc.name, COUNT(*) AS total
             FROM visits v
             INNER JOIN visitor_categories vc ON vc.id = v.category_id
             WHERE v.entry_at BETWEEN :start_at AND :end_at
             GROUP BY vc.name
             ORDER BY total DESC, vc.name'
        );
        $categoryStmt->execute(['start_at' => $rangeStart, 'end_at' => $rangeEnd]);

        $departmentStmt = $pdo->prepare(
            'SELECT COALESCE(d.name, "Departman seçilmedi") AS name, COUNT(*) AS total
             FROM visits v
             LEFT JOIN departments d ON d.id = v.department_id
             WHERE v.entry_at BETWEEN :start_at AND :end_at
             GROUP BY COALESCE(d.name, "Departman seçilmedi")
             ORDER BY total DESC, name'
        );
        $departmentStmt->execute(['start_at' => $rangeStart, 'end_at' => $rangeEnd]);

        return [
            'counts' => $counts,
            'categories' => $categoryStmt->fetchAll(PDO::FETCH_ASSOC),
            'departments' => $departmentStmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    private function singleRow(string $sql, array $params): array
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [];
    }

    private function writeReport(array $schedule, string $periodStart, string $periodEnd, array $data): string
    {
        return ($schedule['output_format'] ?? 'html') === 'pdf'
            ? $this->writePdfReport($schedule, $periodStart, $periodEnd, $data)
            : $this->writeHtmlReport($schedule, $periodStart, $periodEnd, $data);
    }

    private function writeHtmlReport(array $schedule, string $periodStart, string $periodEnd, array $data): string
    {
        $directory = BASE_PATH . '/storage/reports';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $fileName = sprintf('%s-%s-%s.html', $schedule['report_type'], $periodStart, date('His'));
        $filePath = $directory . '/' . $fileName;
        $html = $this->html($schedule, $periodStart, $periodEnd, $data);

        file_put_contents($filePath, $html);

        return $filePath;
    }

    private function writePdfReport(array $schedule, string $periodStart, string $periodEnd, array $data): string
    {
        $directory = BASE_PATH . '/storage/reports';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $fileName = sprintf('%s-%s-%s.pdf', $schedule['report_type'], $periodStart, date('His'));
        $filePath = $directory . '/' . $fileName;
        file_put_contents($filePath, $this->pdf($this->pdfStreams($schedule, $periodStart, $periodEnd, $data)));

        return $filePath;
    }

    private function html(array $schedule, string $periodStart, string $periodEnd, array $data): string
    {
        $counts = $data['counts'];
        $categoryRows = $this->tableRows($data['categories']);
        $departmentRows = $this->tableRows($data['departments']);

        return '<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>' .
            $this->escape($schedule['name']) .
            '</title><style>body{font-family:Arial,sans-serif;color:#18211f;margin:28px}h1{margin-bottom:4px}.muted{color:#66706d}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:20px 0}.metric{border:1px solid #d9ded5;border-radius:8px;padding:12px}.metric strong{display:block;font-size:28px}table{border-collapse:collapse;width:100%;margin-top:12px}th,td{border:1px solid #d9ded5;padding:8px;text-align:left}th{background:#eef2e8}</style></head><body><h1>' .
            $this->escape($schedule['name']) .
            '</h1><p class="muted">Dönem: ' . $this->escape($periodStart) . ' - ' . $this->escape($periodEnd) .
            '</p><section class="grid"><div class="metric"><span>Giriş</span><strong>' . (int) ($counts['total_entries'] ?? 0) .
            '</strong></div><div class="metric"><span>Çıkış</span><strong>' . (int) ($counts['total_exits'] ?? 0) .
            '</strong></div><div class="metric"><span>İçeride</span><strong>' . (int) ($counts['still_inside'] ?? 0) .
            '</strong></div><div class="metric"><span>Süre Aşımı</span><strong>' . (int) ($counts['overdue_count'] ?? 0) .
            '</strong></div></section><h2>Kategori Dağılımı</h2><table><tr><th>Kategori</th><th>Adet</th></tr>' .
            $categoryRows .
            '</table><h2>Departman Dağılımı</h2><table><tr><th>Departman</th><th>Adet</th></tr>' .
            $departmentRows .
            '</table></body></html>';
    }

    private function tableRows(array $rows): string
    {
        if (!$rows) {
            return '<tr><td colspan="2">Kayıt yok</td></tr>';
        }

        $html = '';
        foreach ($rows as $row) {
            $html .= '<tr><td>' . $this->escape((string) $row['name']) . '</td><td>' . (int) $row['total'] . '</td></tr>';
        }

        return $html;
    }

    private function pdfStreams(array $schedule, string $periodStart, string $periodEnd, array $data): array
    {
        $counts = $data['counts'];
        $stream = '';
        $stream .= $this->pdfText(36, 555, 16, (string) $schedule['name']);
        $stream .= $this->pdfText(36, 535, 9, 'Donem: ' . $periodStart . ' - ' . $periodEnd . ' | Olusturma: ' . date('d.m.Y H:i'));
        $stream .= $this->pdfLine(36, 520, 806, 520);

        $metrics = [
            ['Giris', (int) ($counts['total_entries'] ?? 0)],
            ['Cikis', (int) ($counts['total_exits'] ?? 0)],
            ['Iceride', (int) ($counts['still_inside'] ?? 0)],
            ['Sure Asimi', (int) ($counts['overdue_count'] ?? 0)],
        ];
        $x = 36;
        foreach ($metrics as [$label, $value]) {
            $stream .= $this->pdfRect($x, 462, 170, 44);
            $stream .= $this->pdfText($x + 10, 490, 8, $label);
            $stream .= $this->pdfText($x + 10, 472, 18, (string) $value);
            $x += 192;
        }

        $stream .= $this->pdfText(36, 438, 12, 'Kategori Dagilimi');
        $stream .= $this->pdfTable($data['categories'], 36, 420);
        $stream .= $this->pdfText(430, 438, 12, 'Departman Dagilimi');
        $stream .= $this->pdfTable($data['departments'], 430, 420);

        return [$stream];
    }

    private function pdfTable(array $rows, int $x, int $y): string
    {
        $stream = $this->pdfText($x, $y, 8, 'Ad') . $this->pdfText($x + 260, $y, 8, 'Adet');
        $stream .= $this->pdfLine($x, $y - 7, $x + 340, $y - 7);
        $rowY = $y - 22;

        if (!$rows) {
            return $stream . $this->pdfText($x, $rowY, 8, 'Kayit yok');
        }

        foreach (array_slice($rows, 0, 20) as $row) {
            $stream .= $this->pdfText($x, $rowY, 8, $this->clipPdf((string) $row['name'], 42));
            $stream .= $this->pdfText($x + 260, $rowY, 8, (string) ((int) $row['total']));
            $rowY -= 15;
        }

        return $stream;
    }

    private function pdf(array $pageStreams): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $kids = [];

        foreach ($pageStreams as $index => $stream) {
            $pageObjectId = 4 + ($index * 2);
            $contentObjectId = $pageObjectId + 1;
            $kids[] = $pageObjectId . ' 0 R';
            $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObjectId . ' 0 R >>';
            $objects[$contentObjectId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($pageStreams) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxObject = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxObject + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObject; $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i] ?? 0) . "\n";
        }

        return $pdf . "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF\n";
    }

    private function pdfText(int $x, int $y, int $size, string $text): string
    {
        return 'BT /F1 ' . $size . ' Tf 1 0 0 1 ' . $x . ' ' . $y . ' Tm (' . $this->pdfEscape($text) . ") Tj ET\n";
    }

    private function pdfLine(int $x1, int $y1, int $x2, int $y2): string
    {
        return '0.4 w ' . $x1 . ' ' . $y1 . ' m ' . $x2 . ' ' . $y2 . " l S\n";
    }

    private function pdfRect(int $x, int $y, int $width, int $height): string
    {
        return '0.4 w ' . $x . ' ' . $y . ' ' . $width . ' ' . $height . " re S\n";
    }

    private function summaryText(array $data): string
    {
        $counts = $data['counts'];
        return sprintf(
            'Giriş: %d, Çıkış: %d, İçeride: %d, Süre aşımı: %d',
            (int) ($counts['total_entries'] ?? 0),
            (int) ($counts['total_exits'] ?? 0),
            (int) ($counts['still_inside'] ?? 0),
            (int) ($counts['overdue_count'] ?? 0)
        );
    }

    private function touchSchedule(int $scheduleId, string $type, string $runTime, ?int $dayOfMonth): void
    {
        $nextRun = $this->nextRunAt($type, $runTime, $dayOfMonth);
        $stmt = Database::connection()->prepare(
            'UPDATE report_schedules
             SET last_run_at = NOW(), next_run_at = :next_run_at
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $scheduleId,
            'next_run_at' => $nextRun,
        ]);
    }

    private function nextRunAt(string $type, string $runTime, ?int $dayOfMonth): string
    {
        if ($type === 'monthly') {
            $day = max(1, min($dayOfMonth ?: 1, 28));
            return date('Y-m-d ' . $runTime, strtotime('first day of next month +' . ($day - 1) . ' days'));
        }

        if ($type === 'weekly') {
            return date('Y-m-d ' . $runTime, strtotime('+1 week'));
        }

        return date('Y-m-d ' . $runTime, strtotime('+1 day'));
    }

    private function queueUserReportSubscriptions(array $schedule, string $eventType, array $payload): int
    {
        $type = (string) $schedule['report_type'];
        $subscribers = (new User())->reportSubscribers($type);
        $queued = 0;
        $subject = 'Otel Güvenlik: ' . $schedule['name'];
        $message = implode("\n", [
            $schedule['name'] . ' hazırlandı.',
            'Dönem: ' . $payload['period_start'] . ' - ' . $payload['period_end'],
            'Özet: ' . $payload['report_summary'],
            'Dosya: ' . $payload['file_path'],
        ]);

        foreach ($subscribers as $subscriber) {
            $queued += (new NotificationService())->queueDirectUserSystem(
                (int) $subscriber['id'],
                $subject,
                $message,
                $payload + [
                    'mail_template_event' => $eventType,
                    'channel_codes' => ['mail'],
                    'subject' => $subject,
                    'message' => $message,
                ]
            );
        }

        return $queued;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function clipPdf(string $value, int $length): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        if (mb_strlen($value, 'UTF-8') <= $length) {
            return $value;
        }

        return mb_substr($value, 0, max(1, $length - 1), 'UTF-8') . '.';
    }

    private function pdfEscape(string $value): string
    {
        $value = strtr($value, [
            'İ' => 'I',
            'ı' => 'i',
            'Ş' => 'S',
            'ş' => 's',
            'Ğ' => 'G',
            'ğ' => 'g',
            'Ü' => 'U',
            'ü' => 'u',
            'Ö' => 'O',
            'ö' => 'o',
            'Ç' => 'C',
            'ç' => 'c',
        ]);
        $value = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);

        return preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';
    }
}
