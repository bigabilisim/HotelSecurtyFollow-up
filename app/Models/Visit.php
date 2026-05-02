<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Visit
{
    private static bool $appointmentColumnEnsured = false;

    public function createEntry(array $data, int $userId): int
    {
        $this->ensureAppointmentColumn();

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $visitorId = $this->findOrCreateVisitor($data);
            $category = $this->category((int) $data['category_id']);

            $stmt = $pdo->prepare(
                "INSERT INTO visits (
                    visitor_id,
                    category_id,
                    department_id,
                    host_name,
                    purpose,
                    appointment_status,
                    entry_user_id,
                    status,
                    max_duration_minutes_snapshot,
                    entry_note
                ) VALUES (
                    :visitor_id,
                    :category_id,
                    :department_id,
                    :host_name,
                    :purpose,
                    :appointment_status,
                    :entry_user_id,
                    'inside',
                    :max_duration_minutes_snapshot,
                    :entry_note
                )"
            );
            $stmt->execute([
                'visitor_id' => $visitorId,
                'category_id' => (int) $data['category_id'],
                'department_id' => $data['department_id'] ?: null,
                'host_name' => $data['host_name'] ?: null,
                'purpose' => $data['purpose'] ?: null,
                'appointment_status' => ($data['appointment_status'] ?? 'walk_in') === 'appointment' ? 'appointment' : 'walk_in',
                'entry_user_id' => $userId,
                'max_duration_minutes_snapshot' => $category['max_duration_minutes'] ?? null,
                'entry_note' => $data['note'] ?: null,
            ]);

            $visitId = (int) $pdo->lastInsertId();
            $this->addEvent($visitId, $userId, 'entry', 'Giriş kaydı oluşturuldu.');

            $pdo->commit();
            return $visitId;
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    public function createExit(int $visitId, int $userId, ?string $note = null): void
    {
        $this->ensureAppointmentColumn();

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                "UPDATE visits
                 SET exit_at = NOW(), exit_user_id = :user_id, status = 'exited', exit_note = :exit_note
                 WHERE id = :id AND exit_at IS NULL"
            );
            $stmt->execute([
                'id' => $visitId,
                'user_id' => $userId,
                'exit_note' => $note,
            ]);

            $this->addEvent($visitId, $userId, 'exit', 'Çıkış kaydı oluşturuldu.');
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    public function updateInsideVisit(int $visitId, array $data, int $userId): bool
    {
        $this->ensureAppointmentColumn();

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT v.*, vi.id AS visitor_id
                 FROM visits v
                 INNER JOIN visitors vi ON vi.id = v.visitor_id
                 WHERE v.id = :id
                   AND v.exit_at IS NULL
                   AND v.status <> "exited"
                 LIMIT 1'
            );
            $stmt->execute(['id' => $visitId]);
            $visit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$visit) {
                $pdo->rollBack();
                return false;
            }

            $category = $this->category((int) $data['category_id']);
            if (!$category) {
                $pdo->rollBack();
                return false;
            }

            $visitorUpdate = $pdo->prepare(
                'UPDATE visitors
                 SET full_name = :full_name,
                     normalized_name = :normalized_name,
                     phone = :phone,
                     company = :company,
                     vehicle_plate = :vehicle_plate,
                     note = :visitor_note
                 WHERE id = :id'
            );
            $visitorUpdate->execute([
                'id' => (int) $visit['visitor_id'],
                'full_name' => $data['full_name'],
                'normalized_name' => $this->normalizeName((string) $data['full_name']),
                'phone' => $data['phone'] ?: null,
                'company' => $data['company'] ?: null,
                'vehicle_plate' => $data['vehicle_plate'] ?: null,
                'visitor_note' => $data['note'] ?: null,
            ]);

            $visitUpdate = $pdo->prepare(
                'UPDATE visits
                 SET category_id = :category_id,
                     department_id = :department_id,
                     host_name = :host_name,
                     purpose = :purpose,
                     appointment_status = :appointment_status,
                     max_duration_minutes_snapshot = :max_duration_minutes_snapshot,
                     entry_note = :entry_note
                 WHERE id = :id'
            );
            $visitUpdate->execute([
                'id' => $visitId,
                'category_id' => (int) $data['category_id'],
                'department_id' => $data['department_id'] ?: null,
                'host_name' => $data['host_name'] ?: null,
                'purpose' => $data['purpose'] ?: null,
                'appointment_status' => ($data['appointment_status'] ?? 'walk_in') === 'appointment' ? 'appointment' : 'walk_in',
                'max_duration_minutes_snapshot' => $category['max_duration_minutes'] ?? null,
                'entry_note' => $data['note'] ?: null,
            ]);

            $this->addEvent($visitId, $userId, 'note', 'Giriş bilgileri güvenlik tarafından güncellendi.');

            $pdo->commit();
            return true;
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    private function findOrCreateVisitor(array $data): int
    {
        $pdo = Database::connection();
        $normalizedName = $this->normalizeName($data['full_name']);

        $stmt = $pdo->prepare(
            'SELECT id
             FROM visitors
             WHERE normalized_name = :normalized_name AND deleted_at IS NULL
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute(['normalized_name' => $normalizedName]);
        $visitorId = $stmt->fetchColumn();

        if ($visitorId) {
            $update = $pdo->prepare(
                "UPDATE visitors
                 SET phone = COALESCE(NULLIF(:phone, ''), phone),
                     company = COALESCE(NULLIF(:company, ''), company),
                     vehicle_plate = COALESCE(NULLIF(:vehicle_plate, ''), vehicle_plate),
                     note = COALESCE(NULLIF(:note, ''), note)
                 WHERE id = :id"
            );
            $update->execute([
                'id' => (int) $visitorId,
                'phone' => $data['phone'] ?? '',
                'company' => $data['company'] ?? '',
                'vehicle_plate' => $data['vehicle_plate'] ?? '',
                'note' => $data['note'] ?? '',
            ]);

            return (int) $visitorId;
        }

        $insert = $pdo->prepare(
            'INSERT INTO visitors (full_name, normalized_name, phone, company, vehicle_plate, note)
             VALUES (:full_name, :normalized_name, :phone, :company, :vehicle_plate, :note)'
        );
        $insert->execute([
            'full_name' => $data['full_name'],
            'normalized_name' => $normalizedName,
            'phone' => $data['phone'] ?: null,
            'company' => $data['company'] ?: null,
            'vehicle_plate' => $data['vehicle_plate'] ?: null,
            'note' => $data['note'] ?: null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    private function category(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM visitor_categories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        return $category ?: null;
    }

    private function addEvent(int $visitId, int $userId, string $type, string $note): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO visit_events (visit_id, user_id, event_type, event_note)
             VALUES (:visit_id, :user_id, :event_type, :event_note)'
        );
        $stmt->execute([
            'visit_id' => $visitId,
            'user_id' => $userId,
            'event_type' => $type,
            'event_note' => $note,
        ]);
    }

    private function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        $map = [
            'İ' => 'i',
            'I' => 'i',
            'ı' => 'i',
            'Ş' => 's',
            'ş' => 's',
            'Ğ' => 'g',
            'ğ' => 'g',
            'Ü' => 'u',
            'ü' => 'u',
            'Ö' => 'o',
            'ö' => 'o',
            'Ç' => 'c',
            'ç' => 'c',
        ];

        return strtolower(strtr($name, $map));
    }

    private function ensureAppointmentColumn(): void
    {
        if (self::$appointmentColumnEnsured) {
            return;
        }

        $pdo = Database::connection();
        $columns = $pdo->query('SHOW COLUMNS FROM visits')->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('appointment_status', $columns, true)) {
            $pdo->exec('ALTER TABLE visits ADD COLUMN appointment_status ENUM("walk_in", "appointment") NOT NULL DEFAULT "walk_in" AFTER purpose');
        }

        self::$appointmentColumnEnsured = true;
    }
}
