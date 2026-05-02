<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class NotificationChannel
{
    public function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, code, name, is_enabled, config_json, updated_at
             FROM notification_channels
             ORDER BY name'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByCode(string $code): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, code, name, is_enabled, config_json, updated_at
             FROM notification_channels
             WHERE code = :code
             LIMIT 1'
        );
        $stmt->execute(['code' => $code]);
        $channel = $stmt->fetch(PDO::FETCH_ASSOC);

        return $channel ?: null;
    }

    public function update(string $code, string $name, bool $isEnabled, string $configJson): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE notification_channels
             SET name = :name,
                 is_enabled = :is_enabled,
                 config_json = :config_json
             WHERE code = :code'
        );
        $stmt->execute([
            'code' => $code,
            'name' => $name,
            'is_enabled' => $isEnabled ? 1 : 0,
            'config_json' => $configJson,
        ]);
    }
}
