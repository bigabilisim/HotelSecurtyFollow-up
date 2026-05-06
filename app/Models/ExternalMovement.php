<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class ExternalMovement
{
    private static bool $tableEnsured = false;

    public function createExit(array $data, int $userId): int
    {
        $this->ensureTable();

        $stmt = Database::connection()->prepare(
            'INSERT INTO external_movements (
                department_id,
                person_name,
                normalized_person_name,
                vehicle_plate,
                destination_note,
                exit_km,
                exit_user_id,
                status,
                exit_at
             ) VALUES (
                :department_id,
                :person_name,
                :normalized_person_name,
                :vehicle_plate,
                :destination_note,
                :exit_km,
                :exit_user_id,
                "outside",
                NOW()
             )'
        );
        $stmt->execute([
            'department_id' => !empty($data['department_id']) ? (int) $data['department_id'] : null,
            'person_name' => $this->shortText((string) $data['person_name'], 160),
            'normalized_person_name' => $this->normalizeName((string) $data['person_name']),
            'vehicle_plate' => $this->shortText((string) ($data['vehicle_plate'] ?? ''), 40) ?: null,
            'destination_note' => $this->shortText((string) ($data['destination_note'] ?? ''), 500) ?: null,
            'exit_km' => $this->positiveInt($data['exit_km'] ?? null),
            'exit_user_id' => $userId > 0 ? $userId : null,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function createReturn(int $id, array $data, int $userId): bool
    {
        $this->ensureTable();

        $movement = $this->find($id);
        if (!$movement || (string) ($movement['status'] ?? '') !== 'outside') {
            return false;
        }

        $returnKm = $this->positiveInt($data['return_km'] ?? null);
        $exitKm = isset($movement['exit_km']) ? (int) $movement['exit_km'] : null;
        if ($returnKm !== null && $exitKm !== null && $returnKm < $exitKm) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE external_movements
             SET return_km = :return_km,
                 return_note = :return_note,
                 return_user_id = :return_user_id,
                 return_at = NOW(),
                 status = "returned"
             WHERE id = :id
               AND status = "outside"
               AND deleted_at IS NULL'
        );
        $stmt->execute([
            'id' => $id,
            'return_km' => $returnKm,
            'return_note' => $this->shortText((string) ($data['return_note'] ?? ''), 500) ?: null,
            'return_user_id' => $userId > 0 ? $userId : null,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function outside(): array
    {
        $this->ensureTable();

        $stmt = Database::connection()->query(
            'SELECT em.*,
                    d.name AS department_name,
                    exit_user.full_name AS exit_user_name,
                    TIMESTAMPDIFF(MINUTE, em.exit_at, NOW()) AS elapsed_minutes
             FROM external_movements em
             LEFT JOIN departments d ON d.id = em.department_id
             LEFT JOIN users exit_user ON exit_user.id = em.exit_user_id
             WHERE em.status = "outside"
               AND em.deleted_at IS NULL
             ORDER BY em.exit_at DESC, em.id DESC
             LIMIT 100'
        );

        return array_map([$this, 'present'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function recentReturned(int $limit = 20): array
    {
        $this->ensureTable();

        $stmt = Database::connection()->prepare(
            'SELECT em.*,
                    d.name AS department_name,
                    exit_user.full_name AS exit_user_name,
                    return_user.full_name AS return_user_name
             FROM external_movements em
             LEFT JOIN departments d ON d.id = em.department_id
             LEFT JOIN users exit_user ON exit_user.id = em.exit_user_id
             LEFT JOIN users return_user ON return_user.id = em.return_user_id
             WHERE em.status = "returned"
               AND em.deleted_at IS NULL
             ORDER BY em.return_at DESC, em.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue('limit', max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'present'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function quickNotes(int $limit = 8): array
    {
        $this->ensureTable();

        $stmt = Database::connection()->prepare(
            'SELECT destination_note, COUNT(*) AS usage_count, MAX(exit_at) AS last_used_at
             FROM external_movements
             WHERE destination_note IS NOT NULL
               AND destination_note <> ""
               AND deleted_at IS NULL
             GROUP BY destination_note
             ORDER BY usage_count DESC, last_used_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('limit', max(1, min(12, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        $notes = ['Sahil'];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $note = trim((string) ($row['destination_note'] ?? ''));
            if ($note !== '' && !in_array($note, $notes, true)) {
                $notes[] = $note;
            }
        }

        return array_slice($notes, 0, $limit);
    }

    public function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS external_movements (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                department_id BIGINT UNSIGNED NULL,
                person_name VARCHAR(160) NOT NULL,
                normalized_person_name VARCHAR(160) NOT NULL,
                vehicle_plate VARCHAR(40) NULL,
                destination_note VARCHAR(500) NULL,
                exit_km INT UNSIGNED NULL,
                return_km INT UNSIGNED NULL,
                status ENUM("outside", "returned", "cancelled") NOT NULL DEFAULT "outside",
                exit_user_id BIGINT UNSIGNED NULL,
                return_user_id BIGINT UNSIGNED NULL,
                exit_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                return_at DATETIME NULL,
                return_note VARCHAR(500) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL,
                PRIMARY KEY (id),
                KEY idx_external_movements_status_exit (status, exit_at),
                KEY idx_external_movements_department (department_id),
                KEY idx_external_movements_person (normalized_person_name),
                KEY idx_external_movements_vehicle (vehicle_plate),
                KEY idx_external_movements_exit_user (exit_user_id),
                KEY idx_external_movements_return_user (return_user_id),
                CONSTRAINT fk_external_movements_department
                    FOREIGN KEY (department_id) REFERENCES departments(id)
                    ON DELETE SET NULL,
                CONSTRAINT fk_external_movements_exit_user
                    FOREIGN KEY (exit_user_id) REFERENCES users(id)
                    ON DELETE SET NULL,
                CONSTRAINT fk_external_movements_return_user
                    FOREIGN KEY (return_user_id) REFERENCES users(id)
                    ON DELETE SET NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::$tableEnsured = true;
    }

    private function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM external_movements
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function present(array $row): array
    {
        $exitKm = isset($row['exit_km']) ? (int) $row['exit_km'] : null;
        $returnKm = isset($row['return_km']) ? (int) $row['return_km'] : null;
        $row['total_km'] = $exitKm !== null && $returnKm !== null ? max(0, $returnKm - $exitKm) : null;
        $row['elapsed_minutes'] = isset($row['elapsed_minutes']) ? (int) $row['elapsed_minutes'] : null;

        return $row;
    }

    private function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        $map = [
            'İ' => 'I',
            'ı' => 'I',
            'Ş' => 'S',
            'ş' => 'S',
            'Ğ' => 'G',
            'ğ' => 'G',
            'Ü' => 'U',
            'ü' => 'U',
            'Ö' => 'O',
            'ö' => 'O',
            'Ç' => 'C',
            'ç' => 'C',
        ];
        $name = strtoupper(strtr($name, $map));

        return preg_replace('/[^A-Z0-9]+/', ' ', $name) ?: $name;
    }

    private function positiveInt(mixed $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return max(0, (int) $value);
    }

    private function shortText(string $value, int $length): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
    }
}
