<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Department
{
    public function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT d.*, u.full_name AS manager_name
             FROM departments d
             LEFT JOIN users u ON u.id = d.manager_user_id
             WHERE d.deleted_at IS NULL
             ORDER BY d.status, d.name'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT d.*, u.full_name AS manager_name
             FROM departments d
             LEFT JOIN users u ON u.id = d.manager_user_id
             WHERE d.id = :id AND d.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $department = $stmt->fetch(PDO::FETCH_ASSOC);

        return $department ?: null;
    }

    public function create(array $data): void
    {
        $this->save($data);
    }

    public function save(array $data): void
    {
        $stmt = Database::connection()->prepare(
            !empty($data['id'])
                ? 'UPDATE departments
                   SET code = :code,
                       name = :name,
                       manager_user_id = :manager_user_id,
                       email = :email,
                       phone = :phone,
                       status = :status
                   WHERE id = :id AND deleted_at IS NULL'
                : 'INSERT INTO departments (code, name, manager_user_id, email, phone, status)
             VALUES (:code, :name, :manager_user_id, :email, :phone, :status)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                manager_user_id = VALUES(manager_user_id),
                email = VALUES(email),
                phone = VALUES(phone),
                status = VALUES(status),
                deleted_at = NULL'
        );

        $payload = [
            'code' => $this->normalizeCode($data['code'] ?: $data['name']),
            'name' => $data['name'],
            'manager_user_id' => $data['manager_user_id'] ?: null,
            'email' => $data['email'] ?: null,
            'phone' => $data['phone'] ?: null,
            'status' => $data['status'] ?: 'active',
        ];

        if (!empty($data['id'])) {
            $payload['id'] = (int) $data['id'];
        }

        $stmt->execute($payload);
    }

    public function delete(int $id): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE departments
             SET deleted_at = NOW(), status = 'passive'
             WHERE id = :id AND deleted_at IS NULL"
        );
        $stmt->execute(['id' => $id]);
    }

    private function normalizeCode(string $value): string
    {
        $value = trim($value);
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
        $value = strtoupper(strtr($value, $map));
        $value = preg_replace('/[^A-Z0-9]+/', '_', $value) ?: $value;

        return trim($value, '_');
    }
}
