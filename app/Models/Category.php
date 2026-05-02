<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Category
{
    private static bool $quickAccessColumnsEnsured = false;

    public function all(): array
    {
        $this->ensureQuickAccessColumns();

        $stmt = Database::connection()->query(
            'SELECT *
             FROM visitor_categories
             WHERE deleted_at IS NULL
             ORDER BY status, is_quick_access DESC, quick_access_order IS NULL, quick_access_order, name'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $this->ensureQuickAccessColumns();

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM visitor_categories
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        return $category ?: null;
    }

    public function create(array $data, ?int $userId): void
    {
        $this->save($data, $userId);
    }

    public function save(array $data, ?int $userId): void
    {
        $this->ensureQuickAccessColumns();

        $stmt = Database::connection()->prepare(
            !empty($data['id'])
                ? 'UPDATE visitor_categories
                   SET code = :code,
                       name = :name,
                       color = :color,
                       max_duration_minutes = :max_duration_minutes,
                       warning_before_minutes = :warning_before_minutes,
                       requires_department_approval = :requires_department_approval,
                       escalation_after_minutes = :escalation_after_minutes,
                       is_notification_enabled = :is_notification_enabled,
                       is_quick_access = :is_quick_access,
                       quick_access_order = :quick_access_order,
                       escalation_level_1_user_id = :escalation_level_1_user_id,
                       escalation_level_1_after_minutes = :escalation_level_1_after_minutes,
                       escalation_level_2_user_id = :escalation_level_2_user_id,
                       escalation_level_2_after_minutes = :escalation_level_2_after_minutes,
                       escalation_level_3_user_id = :escalation_level_3_user_id,
                       escalation_level_3_after_minutes = :escalation_level_3_after_minutes,
                       status = :status
                   WHERE id = :id AND deleted_at IS NULL'
                : 'INSERT INTO visitor_categories (
                code,
                name,
                color,
                max_duration_minutes,
                warning_before_minutes,
                requires_department_approval,
                escalation_after_minutes,
                is_notification_enabled,
                is_quick_access,
                quick_access_order,
                escalation_level_1_user_id,
                escalation_level_1_after_minutes,
                escalation_level_2_user_id,
                escalation_level_2_after_minutes,
                escalation_level_3_user_id,
                escalation_level_3_after_minutes,
                status,
                created_by
             ) VALUES (
                :code,
                :name,
                :color,
                :max_duration_minutes,
                :warning_before_minutes,
                :requires_department_approval,
                :escalation_after_minutes,
                :is_notification_enabled,
                :is_quick_access,
                :quick_access_order,
                :escalation_level_1_user_id,
                :escalation_level_1_after_minutes,
                :escalation_level_2_user_id,
                :escalation_level_2_after_minutes,
                :escalation_level_3_user_id,
                :escalation_level_3_after_minutes,
                :status,
                :created_by
             )
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                color = VALUES(color),
                max_duration_minutes = VALUES(max_duration_minutes),
                warning_before_minutes = VALUES(warning_before_minutes),
                requires_department_approval = VALUES(requires_department_approval),
                escalation_after_minutes = VALUES(escalation_after_minutes),
                is_notification_enabled = VALUES(is_notification_enabled),
                is_quick_access = VALUES(is_quick_access),
                quick_access_order = VALUES(quick_access_order),
                escalation_level_1_user_id = VALUES(escalation_level_1_user_id),
                escalation_level_1_after_minutes = VALUES(escalation_level_1_after_minutes),
                escalation_level_2_user_id = VALUES(escalation_level_2_user_id),
                escalation_level_2_after_minutes = VALUES(escalation_level_2_after_minutes),
                escalation_level_3_user_id = VALUES(escalation_level_3_user_id),
                escalation_level_3_after_minutes = VALUES(escalation_level_3_after_minutes),
                status = VALUES(status),
                deleted_at = NULL'
        );

        $payload = [
            'code' => $this->normalizeCode($data['code'] ?: $data['name']),
            'name' => $data['name'],
            'color' => $data['color'] ?: '#0f766e',
            'max_duration_minutes' => $data['max_duration_minutes'] ?: null,
            'warning_before_minutes' => $data['warning_before_minutes'] ?: 10,
            'requires_department_approval' => !empty($data['requires_department_approval']) ? 1 : 0,
            'escalation_after_minutes' => ($data['escalation_after_minutes'] ?? 0) ?: 5,
            'is_notification_enabled' => !empty($data['is_notification_enabled']) ? 1 : 0,
            'is_quick_access' => !empty($data['is_quick_access']) ? 1 : 0,
            'quick_access_order' => !empty($data['is_quick_access'])
                ? max(1, min(4, (int) ($data['quick_access_order'] ?: 4)))
                : null,
            'escalation_level_1_user_id' => ($data['escalation_level_1_user_id'] ?? 0) ?: null,
            'escalation_level_1_after_minutes' => $this->minutesOrNull($data['escalation_level_1_after_minutes'] ?? null),
            'escalation_level_2_user_id' => ($data['escalation_level_2_user_id'] ?? 0) ?: null,
            'escalation_level_2_after_minutes' => $this->minutesOrNull($data['escalation_level_2_after_minutes'] ?? null),
            'escalation_level_3_user_id' => ($data['escalation_level_3_user_id'] ?? 0) ?: null,
            'escalation_level_3_after_minutes' => $this->minutesOrNull($data['escalation_level_3_after_minutes'] ?? null),
            'status' => $data['status'] ?: 'active',
            'created_by' => $userId,
        ];

        if (!empty($data['id'])) {
            unset($payload['created_by']);
            $payload['id'] = (int) $data['id'];
        }

        $stmt->execute($payload);
    }

    public function ensureQuickAccessColumns(): void
    {
        if (self::$quickAccessColumnsEnsured) {
            return;
        }

        $pdo = Database::connection();
        $columns = $pdo->query('SHOW COLUMNS FROM visitor_categories')->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('is_quick_access', $columns, true)) {
            $pdo->exec('ALTER TABLE visitor_categories ADD COLUMN is_quick_access TINYINT(1) NOT NULL DEFAULT 0 AFTER is_notification_enabled');
        }

        if (!in_array('quick_access_order', $columns, true)) {
            $pdo->exec('ALTER TABLE visitor_categories ADD COLUMN quick_access_order TINYINT UNSIGNED NULL AFTER is_quick_access');
        }

        $afterColumn = 'quick_access_order';
        foreach ([1, 2, 3] as $level) {
            $userColumn = 'escalation_level_' . $level . '_user_id';
            $minutesColumn = 'escalation_level_' . $level . '_after_minutes';

            if (!in_array($userColumn, $columns, true)) {
                $pdo->exec('ALTER TABLE visitor_categories ADD COLUMN ' . $userColumn . ' BIGINT UNSIGNED NULL AFTER ' . $afterColumn);
            }

            if (!in_array($minutesColumn, $columns, true)) {
                $pdo->exec('ALTER TABLE visitor_categories ADD COLUMN ' . $minutesColumn . ' INT UNSIGNED NULL AFTER ' . $userColumn);
            }

            $afterColumn = $minutesColumn;
        }

        self::$quickAccessColumnsEnsured = true;
    }

    public function delete(int $id): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE visitor_categories
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

    private function minutesOrNull(mixed $value): ?int
    {
        $minutes = (int) ($value ?? 0);
        return $minutes > 0 ? $minutes : null;
    }
}
