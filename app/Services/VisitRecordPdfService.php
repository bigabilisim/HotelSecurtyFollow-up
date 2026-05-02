<?php

declare(strict_types=1);

namespace App\Services;

final class VisitRecordPdfService
{
    private const PAGE_WIDTH = 842;
    private const PAGE_HEIGHT = 595;
    private const ROWS_PER_PAGE = 26;

    public function create(array $records, array $filters, int $totalCount): string
    {
        $directory = BASE_PATH . '/storage/reports';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $fileName = 'kayitlar-' . date('Ymd-His') . '.pdf';
        $filePath = $directory . '/' . $fileName;
        file_put_contents($filePath, $this->pdf($this->pageStreams($records, $filters, $totalCount)));

        return $filePath;
    }

    private function pageStreams(array $records, array $filters, int $totalCount): array
    {
        $chunks = array_chunk($records, self::ROWS_PER_PAGE);
        if (!$chunks) {
            $chunks = [[]];
        }

        $streams = [];
        $pageCount = count($chunks);
        foreach ($chunks as $index => $chunk) {
            $streams[] = $this->pageStream($chunk, $filters, $totalCount, $index + 1, $pageCount);
        }

        return $streams;
    }

    private function pageStream(array $records, array $filters, int $totalCount, int $page, int $pageCount): string
    {
        $stream = '';
        $stream .= $this->text(28, 558, 16, 'Otel Guvenlik Giris Cikis Kayitlari');
        $stream .= $this->text(28, 538, 9, 'Filtre: ' . $this->filterText($filters) . ' | Toplam kayit: ' . $totalCount . ' | Olusturma: ' . date('d.m.Y H:i'));
        $stream .= $this->text(770, 558, 9, 'Sayfa ' . $page . '/' . $pageCount);
        $stream .= $this->line(28, 525, 814, 525);

        $headers = [
            [30, 'ID'],
            [62, 'Ad Soyad'],
            [170, 'Kategori'],
            [250, 'Departman'],
            [340, 'Hareket'],
            [420, 'Zaman'],
            [510, 'Sure'],
            [555, 'Plaka'],
            [630, 'Durum'],
            [705, 'Not'],
        ];

        foreach ($headers as [$x, $label]) {
            $stream .= $this->text($x, 506, 8, $label);
        }
        $stream .= $this->line(28, 498, 814, 498);

        $y = 482;
        foreach ($records as $record) {
            $stream .= $this->text(30, $y, 7, (string) $record['id']);
            $stream .= $this->text(62, $y, 7, $this->clip((string) $record['full_name'], 24));
            $categoryText = (string) $record['category_name'];
            if (($record['appointment_status'] ?? 'walk_in') === 'appointment') {
                $categoryText .= ' / Randevulu';
            }

            $stream .= $this->text(170, $y, 7, $this->clip($categoryText, 17));
            $stream .= $this->text(250, $y, 7, $this->clip((string) ($record['department_name'] ?? '-'), 18));
            $stream .= $this->text(340, $y, 7, $this->movementLabel((string) ($record['movement_type'] ?? 'entry')));
            $stream .= $this->text(420, $y, 7, $this->dateTime($record['movement_at'] ?? null));
            $stream .= $this->text(510, $y, 7, (string) ((int) ($record['elapsed_minutes'] ?? 0)) . ' dk');
            $stream .= $this->text(555, $y, 7, $this->clip((string) ($record['vehicle_plate'] ?? '-'), 13));
            $stream .= $this->text(630, $y, 7, $this->statusLabel((string) $record['status']));
            $stream .= $this->text(705, $y, 7, $this->clip((string) (($record['movement_note'] ?? '') ?: ($record['entry_note'] ?? '') ?: ($record['purpose'] ?? '-')), 25));
            $stream .= $this->line(28, $y - 6, 814, $y - 6);
            $y -= 16;
        }

        if (!$records) {
            $stream .= $this->text(30, 474, 9, 'Bu filtrelerle kayit bulunamadi.');
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

            $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . '] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObjectId . ' 0 R >>';
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

        $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF\n";

        return $pdf;
    }

    private function text(int $x, int $y, int $size, string $text): string
    {
        return 'BT /F1 ' . $size . ' Tf 1 0 0 1 ' . $x . ' ' . $y . ' Tm (' . $this->escape($text) . ") Tj ET\n";
    }

    private function line(int $x1, int $y1, int $x2, int $y2): string
    {
        return '0.4 w ' . $x1 . ' ' . $y1 . ' m ' . $x2 . ' ' . $y2 . " l S\n";
    }

    private function filterText(array $filters): string
    {
        $parts = [
            ($filters['date_from'] ?? '-') . ' / ' . ($filters['date_to'] ?? '-'),
        ];

        if (!empty($filters['status'])) {
            $parts[] = 'Durum: ' . $this->statusLabel((string) $filters['status']);
        }

        if (!empty($filters['search'])) {
            $parts[] = 'Arama: ' . $filters['search'];
        }

        return implode(' | ', $parts);
    }

    private function dateTime(mixed $value): string
    {
        if (!$value) {
            return '-';
        }

        $timestamp = strtotime((string) $value);

        return $timestamp ? date('d.m.Y H:i', $timestamp) : '-';
    }

    private function statusLabel(string $status): string
    {
        return [
            'inside' => 'Iceride',
            'exited' => 'Cikis',
            'overdue' => 'Sure asimi',
            'department_asked' => 'Soru',
            'department_approved' => 'Onay',
            'escalated' => 'Eskalasyon',
            'cancelled' => 'Iptal',
        ][$status] ?? $status;
    }

    private function movementLabel(string $movementType): string
    {
        return [
            'entry' => 'Giris',
            'exit' => 'Cikis',
        ][$movementType] ?? 'Hareket';
    }

    private function clip(string $value, int $length): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        if (mb_strlen($value, 'UTF-8') <= $length) {
            return $value;
        }

        return mb_substr($value, 0, max(1, $length - 1), 'UTF-8') . '.';
    }

    private function escape(string $value): string
    {
        $value = $this->ascii($value);
        $value = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);

        return preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';
    }

    private function ascii(string $value): string
    {
        $value = strtr($value, [
            'İ' => 'I',
            'I' => 'I',
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

        return $value;
    }
}
