<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Settings
{
    public function all(): array
    {
        $stmt = Database::connection()->query('SELECT setting_key, setting_value FROM app_settings');
        $settings = [];

        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        return $settings;
    }

    public function setMany(array $settings): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO app_settings (setting_key, setting_value, is_encrypted)
             VALUES (:setting_key, :setting_value, 0)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_encrypted = VALUES(is_encrypted)'
        );

        foreach ($settings as $key => $value) {
            $stmt->execute([
                'setting_key' => $key,
                'setting_value' => (string) $value,
            ]);
        }
    }
}
