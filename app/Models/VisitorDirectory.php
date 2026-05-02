<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class VisitorDirectory
{
    public function all(array $filters = [], int $limit = 300): array
    {
        $where = ['vi.deleted_at IS NULL'];
        $params = [];
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $where[] = '(
                vi.full_name LIKE :search
                OR vi.phone LIKE :search
                OR vi.company LIKE :search
                OR vi.vehicle_plate LIKE :search
                OR vi.note LIKE :search
            )';
            $params['search'] = '%' . $search . '%';
        }

        $stmt = Database::connection()->prepare(
            'SELECT
                vi.*,
                latest.last_entry_at,
                latest.last_exit_at,
                latest.visit_count,
                vc.name AS last_category_name,
                d.name AS last_department_name
             FROM visitors vi
             LEFT JOIN (
                SELECT
                    visitor_id,
                    MAX(id) AS last_visit_id,
                    MAX(entry_at) AS last_entry_at,
                    MAX(exit_at) AS last_exit_at,
                    COUNT(*) AS visit_count
                FROM visits
                GROUP BY visitor_id
             ) latest ON latest.visitor_id = vi.id
             LEFT JOIN visits v ON v.id = latest.last_visit_id
             LEFT JOIN visitor_categories vc ON vc.id = v.category_id
             LEFT JOIN departments d ON d.id = v.department_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY vi.updated_at DESC, vi.id DESC
             LIMIT ' . max(1, min($limit, 1000))
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM visitors
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $visitor = $stmt->fetch(PDO::FETCH_ASSOC);

        return $visitor ?: null;
    }

    public function save(array $data): int
    {
        $id = (int) ($data['id'] ?? 0);
        $fullName = trim((string) ($data['full_name'] ?? ''));

        if ($fullName === '') {
            throw new \RuntimeException('Ad soyad zorunludur.');
        }

        $payload = [
            'full_name' => $fullName,
            'normalized_name' => $this->normalizeName($fullName),
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'company' => trim((string) ($data['company'] ?? '')) ?: null,
            'vehicle_plate' => trim((string) ($data['vehicle_plate'] ?? '')) ?: null,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
        ];

        if ($id > 0) {
            $stmt = Database::connection()->prepare(
                'UPDATE visitors
                 SET full_name = :full_name,
                     normalized_name = :normalized_name,
                     phone = :phone,
                     company = :company,
                     vehicle_plate = :vehicle_plate,
                     note = :note
                 WHERE id = :id
                   AND deleted_at IS NULL'
            );
            $stmt->execute($payload + ['id' => $id]);

            return $id;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO visitors (full_name, normalized_name, phone, company, vehicle_plate, note)
             VALUES (:full_name, :normalized_name, :phone, :company, :vehicle_plate, :note)'
        );
        $stmt->execute($payload);

        return (int) Database::connection()->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = Database::connection()->prepare(
            'UPDATE visitors
             SET deleted_at = NOW()
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
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
}
