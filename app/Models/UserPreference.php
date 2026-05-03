<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class UserPreference
{
    private const MAX_VALUE_LENGTH = 6000;

    public function all(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            'SELECT setting_value
             FROM app_settings
             WHERE setting_key = :setting_key
             LIMIT 1'
        );
        $stmt->execute(['setting_key' => $this->settingKey($userId)]);
        $raw = $stmt->fetchColumn();
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $preferences = [];
        foreach ($decoded as $key => $value) {
            if (!$this->isAllowedKey((string) $key) || !is_scalar($value)) {
                continue;
            }

            $preferences[(string) $key] = mb_substr((string) $value, 0, self::MAX_VALUE_LENGTH);
        }

        return $preferences;
    }

    public function setMany(int $userId, array $changes): void
    {
        if ($userId <= 0) {
            return;
        }

        $preferences = $this->all($userId);
        foreach ($changes as $key => $value) {
            $key = (string) $key;
            if (!$this->isAllowedKey($key)) {
                continue;
            }

            if ($value === null) {
                unset($preferences[$key]);
                continue;
            }

            if (!is_scalar($value)) {
                continue;
            }

            $preferences[$key] = mb_substr((string) $value, 0, self::MAX_VALUE_LENGTH);
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO app_settings (setting_key, setting_value, is_encrypted)
             VALUES (:setting_key, :setting_value, 0)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_encrypted = VALUES(is_encrypted)'
        );
        $stmt->execute([
            'setting_key' => $this->settingKey($userId),
            'setting_value' => json_encode($preferences, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function settingKey(int $userId): string
    {
        return 'user_preferences.' . $userId;
    }

    private function isAllowedKey(string $key): bool
    {
        return in_array($key, [
            'otelSecurityMobileMenuHidden',
            'otelSecurityAdminCardOrder',
            'hotelSecurity.visitColumns.hidden',
            'hotelSecurity.dashboardBlocks.hidden',
            'hotelSecurity.dashboard.template',
            'hotelSecurity.dashboard.order',
            'hotelSecurity.dashboard.zoom',
            'hotelSecurity.dashboard.viewPanelOpen',
            'hotelSecurity.sectionFilter.dashboard-inside-columns.open',
            'hotelSecurity.sectionFilter.admin-records-filter.open',
            'hotelSecurity.sectionFilter.admin-suggestions-filter.open',
            'hotelSecurity.filterForm.admin-records',
            'hotelSecurity.filterForm.admin-suggestions',
        ], true);
    }
}
