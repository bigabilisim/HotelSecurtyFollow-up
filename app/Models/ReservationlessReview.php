<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\Notifications\NotificationQueue;
use App\Services\Notifications\NotificationService;
use PDO;

final class ReservationlessReview
{
    private static bool $ready = false;

    public function createForVisitIfNeeded(int $visitId): array
    {
        $this->ensureReady();
        $visit = $this->visit($visitId);

        if (!$visit || (string) ($visit['category_code'] ?? '') !== 'REZERVASYONSUZ_GIRIS') {
            return ['requested' => false, 'queued' => 0, 'sent' => 0, 'message' => ''];
        }

        if ((int) ($visit['department_id'] ?? 0) <= 0) {
            return ['requested' => false, 'queued' => 0, 'sent' => 0, 'message' => 'Rezervasyonsuz giriş oda bilgisi için departman seçilmedi.'];
        }

        $recipientEmail = trim((string) (($visit['manager_email'] ?? '') ?: ($visit['department_email'] ?? '')));
        if ($recipientEmail === '') {
            return ['requested' => false, 'queued' => 0, 'sent' => 0, 'message' => 'Rezervasyonsuz giriş oda bilgisi için departman yöneticisi e-postası eksik.'];
        }

        $review = $this->existingForVisit($visitId);
        if (!$review) {
            $token = bin2hex(random_bytes(24));
            $stmt = Database::connection()->prepare(
                'INSERT INTO reservationless_reviews (
                    visit_id,
                    department_id,
                    requested_user_id,
                    response_token,
                    status
                 ) VALUES (
                    :visit_id,
                    :department_id,
                    :requested_user_id,
                    :response_token,
                    "pending"
                 )'
            );
            $stmt->execute([
                'visit_id' => $visitId,
                'department_id' => (int) $visit['department_id'],
                'requested_user_id' => ($visit['manager_user_id'] ?? null) ?: null,
                'response_token' => $token,
            ]);

            $review = [
                'id' => (int) Database::connection()->lastInsertId(),
                'response_token' => $token,
            ];
        }

        $queued = (new NotificationService())->queueMail(
            $recipientEmail,
            (string) (($visit['manager_name'] ?? '') ?: ($visit['department_name'] . ' Departmanı')),
            'Rezervasyonsuz Giriş İçin Oda Bilgisi Bekleniyor',
            $this->requestMessage($visit, (string) $review['response_token'])
        );
        $processed = $queued > 0 ? (new NotificationQueue())->process(10) : ['sent' => 0, 'failed' => 0];

        return [
            'requested' => true,
            'queued' => $queued,
            'sent' => (int) ($processed['sent'] ?? 0),
            'failed' => (int) ($processed['failed'] ?? 0),
            'message' => $queued > 0
                ? 'Rezervasyonsuz giriş oda bilgisi yöneticiden istendi.'
                : 'Rezervasyonsuz giriş oda bilgisi mail kuyruğuna eklenemedi.',
        ];
    }

    public function findByToken(string $token): ?array
    {
        $this->ensureReady();

        $stmt = Database::connection()->prepare(
            'SELECT
                rr.*,
                v.entry_at,
                v.host_name,
                v.purpose,
                v.entry_note,
                vi.full_name AS visitor_name,
                vi.phone AS visitor_phone,
                vi.company AS visitor_company,
                vi.vehicle_plate,
                vc.name AS category_name,
                d.name AS department_name
             FROM reservationless_reviews rr
             INNER JOIN visits v ON v.id = rr.visit_id
             INNER JOIN visitors vi ON vi.id = v.visitor_id
             INNER JOIN visitor_categories vc ON vc.id = v.category_id
             LEFT JOIN departments d ON d.id = rr.department_id
             WHERE rr.response_token = :token
             LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);

        return $review ?: null;
    }

    public function submit(string $token, string $roomNumber, string $managerNote): array
    {
        $review = $this->findByToken($token);
        if (!$review) {
            return ['ok' => false, 'message' => 'Bağlantı geçersiz veya kayıt bulunamadı.'];
        }

        if (($review['status'] ?? '') === 'submitted') {
            return ['ok' => false, 'message' => 'Bu oda bilgisi daha önce kaydedilmiş.'];
        }

        $roomNumber = trim($roomNumber);
        $managerNote = trim($managerNote);
        if ($roomNumber === '' || $managerNote === '') {
            return ['ok' => false, 'message' => 'Oda numarası ve not alanı zorunludur.'];
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'UPDATE reservationless_reviews
                 SET room_number = :room_number,
                     manager_note = :manager_note,
                     status = "submitted",
                     submitted_at = NOW()
                 WHERE id = :id AND status = "pending"'
            );
            $stmt->execute([
                'id' => (int) $review['id'],
                'room_number' => $roomNumber,
                'manager_note' => $managerNote,
            ]);

            $eventStmt = $pdo->prepare(
                'INSERT INTO visit_events (visit_id, user_id, event_type, event_note)
                 VALUES (:visit_id, NULL, "note", :event_note)'
            );
            $eventStmt->execute([
                'visit_id' => (int) $review['visit_id'],
                'event_note' => 'Rezervasyonsuz giriş oda bilgisi: Oda ' . $roomNumber . ' - ' . $managerNote,
            ]);

            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }

        $updatedReview = $this->findByToken($token) ?? ($review + [
            'room_number' => $roomNumber,
            'manager_note' => $managerNote,
        ]);
        $summary = $this->notifyDepartmentManagers($updatedReview);

        return [
            'ok' => true,
            'message' => 'Oda bilgisi kaydedildi. Departman yöneticilerine bilgilendirme gönderildi.',
            'mail' => $summary,
        ];
    }

    public function ensureReady(): void
    {
        if (self::$ready) {
            return;
        }

        $pdo = Database::connection();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS reservationless_reviews (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                visit_id BIGINT UNSIGNED NOT NULL,
                department_id BIGINT UNSIGNED NULL,
                requested_user_id BIGINT UNSIGNED NULL,
                response_token VARCHAR(64) NOT NULL,
                room_number VARCHAR(80) NULL,
                manager_note TEXT NULL,
                status ENUM("pending", "submitted", "cancelled") NOT NULL DEFAULT "pending",
                requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                submitted_at TIMESTAMP NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uk_reservationless_reviews_visit (visit_id),
                UNIQUE KEY uk_reservationless_reviews_token (response_token),
                KEY idx_reservationless_reviews_status (status, requested_at),
                KEY idx_reservationless_reviews_department (department_id),
                CONSTRAINT fk_reservationless_reviews_visit
                    FOREIGN KEY (visit_id) REFERENCES visits(id)
                    ON DELETE CASCADE,
                CONSTRAINT fk_reservationless_reviews_department
                    FOREIGN KEY (department_id) REFERENCES departments(id)
                    ON DELETE SET NULL,
                CONSTRAINT fk_reservationless_reviews_requested_user
                    FOREIGN KEY (requested_user_id) REFERENCES users(id)
                    ON DELETE SET NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::$ready = true;
    }

    private function existingForVisit(int $visitId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM reservationless_reviews
             WHERE visit_id = :visit_id
             LIMIT 1'
        );
        $stmt->execute(['visit_id' => $visitId]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);

        return $review ?: null;
    }

    private function visit(int $visitId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                v.*,
                vi.full_name AS visitor_name,
                vi.phone AS visitor_phone,
                vi.company AS visitor_company,
                vi.vehicle_plate,
                vc.name AS category_name,
                vc.code AS category_code,
                d.name AS department_name,
                d.email AS department_email,
                d.manager_user_id,
                u.full_name AS manager_name,
                u.email AS manager_email
             FROM visits v
             INNER JOIN visitors vi ON vi.id = v.visitor_id
             INNER JOIN visitor_categories vc ON vc.id = v.category_id
             LEFT JOIN departments d ON d.id = v.department_id
             LEFT JOIN users u ON u.id = d.manager_user_id
                AND u.status = "active"
                AND u.deleted_at IS NULL
             WHERE v.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $visitId]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);

        return $visit ?: null;
    }

    private function requestMessage(array $visit, string $token): string
    {
        $link = $this->absoluteUrl('/reservationless/review', ['token' => $token]);

        return implode("\n", [
            'Merhaba,',
            '',
            'Rezervasyonsuz giriş kaydı oluşturuldu. Lütfen verilen oda numarasını ve departman notunuzu aşağıdaki linkten kaydedin.',
            '',
            'Ziyaretçi: ' . (string) ($visit['visitor_name'] ?? '-'),
            'Telefon: ' . (string) (($visit['visitor_phone'] ?? '') ?: '-'),
            'Firma: ' . (string) (($visit['visitor_company'] ?? '') ?: '-'),
            'Departman: ' . (string) (($visit['department_name'] ?? '') ?: '-'),
            'Görüşeceği kişi: ' . (string) (($visit['host_name'] ?? '') ?: '-'),
            'Giriş saati: ' . $this->dateText($visit['entry_at'] ?? null),
            '',
            'Oda ve not formu: ' . $link,
            '',
            'Form kaydedildiğinde bilgi tüm departman yöneticilerine otomatik gönderilecektir.',
        ]);
    }

    private function notifyDepartmentManagers(array $review): array
    {
        $recipients = $this->departmentManagers();
        $queued = 0;
        $service = new NotificationService();
        foreach ($recipients as $recipient) {
            $queued += $service->queueMail(
                (string) $recipient['email'],
                (string) $recipient['name'],
                'Rezervasyonsuz Giriş Oda Bilgisi',
                $this->submittedMessage($review)
            );
        }

        $processed = $queued > 0 ? (new NotificationQueue())->process(max(10, $queued + 2)) : ['sent' => 0, 'failed' => 0];

        return [
            'recipients' => count($recipients),
            'queued' => $queued,
            'sent' => (int) ($processed['sent'] ?? 0),
            'failed' => (int) ($processed['failed'] ?? 0),
        ];
    }

    private function departmentManagers(): array
    {
        $stmt = Database::connection()->query(
            'SELECT
                d.name AS department_name,
                d.email AS department_email,
                u.full_name AS manager_name,
                u.email AS manager_email
             FROM departments d
             LEFT JOIN users u ON u.id = d.manager_user_id
                AND u.status = "active"
                AND u.deleted_at IS NULL
             WHERE d.deleted_at IS NULL
               AND d.status = "active"
             ORDER BY d.name'
        );

        $seen = [];
        $recipients = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $email = trim((string) (($row['manager_email'] ?? '') ?: ($row['department_email'] ?? '')));
            if ($email === '') {
                continue;
            }

            $key = strtolower($email);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $recipients[] = [
                'name' => (string) (($row['manager_name'] ?? '') ?: ($row['department_name'] . ' Departmanı')),
                'email' => $email,
            ];
        }

        return $recipients;
    }

    private function submittedMessage(array $review): string
    {
        return implode("\n", [
            'Rezervasyonsuz giriş oda bilgisi departman yöneticisi tarafından kaydedildi.',
            '',
            'Ziyaretçi: ' . (string) ($review['visitor_name'] ?? '-'),
            'Telefon: ' . (string) (($review['visitor_phone'] ?? '') ?: '-'),
            'Firma: ' . (string) (($review['visitor_company'] ?? '') ?: '-'),
            'Plaka: ' . (string) (($review['vehicle_plate'] ?? '') ?: '-'),
            'Departman: ' . (string) (($review['department_name'] ?? '') ?: '-'),
            'Giriş saati: ' . $this->dateText($review['entry_at'] ?? null),
            '',
            'Verilen oda: ' . (string) ($review['room_number'] ?? '-'),
            'Departman notu: ' . (string) ($review['manager_note'] ?? '-'),
        ]);
    }

    private function absoluteUrl(string $route, array $params = []): string
    {
        return rtrim((string) config('app.url', ''), '/') . route($route, $params);
    }

    private function dateText(mixed $value): string
    {
        $timestamp = $value ? strtotime((string) $value) : false;

        return $timestamp ? date('d.m.Y H:i', $timestamp) : '-';
    }
}
