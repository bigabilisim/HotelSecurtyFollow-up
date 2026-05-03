<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class VisitRecord
{
    private static bool $appointmentColumnEnsured = false;

    public function filters(array $input): array
    {
        $today = date('Y-m-d');
        $dateFrom = $this->dateValue((string) ($input['date_from'] ?? '')) ?: $today;
        $dateTo = $this->dateValue((string) ($input['date_to'] ?? '')) ?: $dateFrom;

        if ($dateTo < $dateFrom) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'category_id' => max(0, (int) ($input['category_id'] ?? 0)),
            'department_id' => max(0, (int) ($input['department_id'] ?? 0)),
            'status' => $this->statusValue((string) ($input['status'] ?? '')),
            'search' => trim((string) ($input['search'] ?? '')),
        ];
    }

    public function records(array $filters, int $limit = 250): array
    {
        $this->ensureAppointmentColumn();

        [$where, $params] = $this->where($filters);
        $limit = max(1, min($limit, 1000));

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM (' . $this->movementSql() . ') movement_records
             WHERE ' . $where . '
             ORDER BY movement_at DESC, id DESC
             LIMIT ' . $limit
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(array $filters): int
    {
        $this->ensureAppointmentColumn();

        [$where, $params] = $this->where($filters);

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM (' . $this->movementSql() . ') movement_records
             WHERE ' . $where
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function statusLabels(): array
    {
        return [
            '' => 'Tümü',
            'inside' => 'İçeride',
            'exited' => 'Çıkış yaptı',
            'overdue' => 'Süre aşımı',
            'department_asked' => 'Departman soruldu',
            'department_approved' => 'Departman onayladı',
            'escalated' => 'Eskalasyon',
            'cancelled' => 'İptal',
        ];
    }

    private function where(array $filters): array
    {
        $where = [
            '1 = 1',
        ];
        $params = [
            'entry_date_from' => $filters['date_from'] . ' 00:00:00',
            'entry_date_to' => $filters['date_to'] . ' 23:59:59',
            'exit_date_from' => $filters['date_from'] . ' 00:00:00',
            'exit_date_to' => $filters['date_to'] . ' 23:59:59',
        ];

        if ((int) ($filters['category_id'] ?? 0) > 0) {
            $where[] = 'category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if ((int) ($filters['department_id'] ?? 0) > 0) {
            $where[] = 'department_id = :department_id';
            $params['department_id'] = (int) $filters['department_id'];
        }

        $status = (string) ($filters['status'] ?? '');
        if ($status !== '') {
            if ($status === 'inside') {
                $where[] = "movement_type = 'entry' AND exit_at IS NULL AND status <> 'exited'";
            } elseif ($status === 'exited') {
                $where[] = "movement_type = 'exit' AND status = 'exited'";
            } elseif ($status === 'overdue') {
                $where[] = "movement_type = 'entry' AND status IN ('overdue', 'department_asked', 'escalated')";
            } else {
                $where[] = 'status = :status';
                $params['status'] = $status;
            }
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $searchColumns = [
                'full_name',
                'phone',
                'company',
                'vehicle_plate',
                'host_name',
                'purpose',
                'entry_note',
                'exit_note',
                'movement_note',
            ];
            $searchParts = [];

            foreach ($searchColumns as $index => $column) {
                $param = 'search_' . $index;
                $searchParts[] = $column . ' LIKE :' . $param;
                $params[$param] = '%' . $search . '%';
            }

            $where[] = '(' . implode(' OR ', $searchParts) . ')';
        }

        return [implode(' AND ', $where), $params];
    }

    private function movementSql(): string
    {
        return '
            SELECT
                v.id,
                v.category_id,
                v.department_id,
                v.entry_at,
                v.exit_at,
                v.status,
                v.appointment_status,
                v.host_name,
                v.purpose,
                v.entry_note,
                v.exit_note,
                TIMESTAMPDIFF(MINUTE, v.entry_at, COALESCE(v.exit_at, NOW())) AS elapsed_minutes,
                vi.full_name,
                vi.phone,
                vi.company,
                vi.vehicle_plate,
                vi.note AS visitor_note,
                vc.name AS category_name,
                d.name AS department_name,
                entry_user.full_name AS entry_user_name,
                exit_user.full_name AS exit_user_name,
                \'entry\' AS movement_type,
                v.entry_at AS movement_at,
                COALESCE(v.entry_note, v.purpose) AS movement_note,
                entry_user.full_name AS movement_user_name
             FROM visits v
             INNER JOIN visitors vi ON vi.id = v.visitor_id
             INNER JOIN visitor_categories vc ON vc.id = v.category_id
             LEFT JOIN departments d ON d.id = v.department_id
             LEFT JOIN users entry_user ON entry_user.id = v.entry_user_id
             LEFT JOIN users exit_user ON exit_user.id = v.exit_user_id
             WHERE vi.deleted_at IS NULL
               AND v.entry_at BETWEEN :entry_date_from AND :entry_date_to
            UNION ALL
            SELECT
                v.id,
                v.category_id,
                v.department_id,
                v.entry_at,
                v.exit_at,
                v.status,
                v.appointment_status,
                v.host_name,
                v.purpose,
                v.entry_note,
                v.exit_note,
                TIMESTAMPDIFF(MINUTE, v.entry_at, v.exit_at) AS elapsed_minutes,
                vi.full_name,
                vi.phone,
                vi.company,
                vi.vehicle_plate,
                vi.note AS visitor_note,
                vc.name AS category_name,
                d.name AS department_name,
                entry_user.full_name AS entry_user_name,
                exit_user.full_name AS exit_user_name,
                \'exit\' AS movement_type,
                v.exit_at AS movement_at,
                COALESCE(v.exit_note, \'Çıkış kaydı oluşturuldu.\') AS movement_note,
                exit_user.full_name AS movement_user_name
             FROM visits v
             INNER JOIN visitors vi ON vi.id = v.visitor_id
             INNER JOIN visitor_categories vc ON vc.id = v.category_id
             LEFT JOIN departments d ON d.id = v.department_id
             LEFT JOIN users entry_user ON entry_user.id = v.entry_user_id
             LEFT JOIN users exit_user ON exit_user.id = v.exit_user_id
             WHERE vi.deleted_at IS NULL
               AND v.exit_at BETWEEN :exit_date_from AND :exit_date_to';
    }

    private function dateValue(string $value): ?string
    {
        $value = trim($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    private function statusValue(string $value): string
    {
        return in_array($value, array_keys($this->statusLabels()), true) ? $value : '';
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
