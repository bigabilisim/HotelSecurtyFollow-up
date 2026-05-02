<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class Watchlist
{
    private static bool $tableEnsured = false;

    public function all(): array
    {
        $this->ensureTable();

        $stmt = Database::connection()->query(
            'SELECT *
             FROM watchlist_entries
             WHERE deleted_at IS NULL
             ORDER BY is_active DESC, list_type, match_type, match_value'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $this->ensureTable();

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM watchlist_entries
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        return $entry ?: null;
    }

    public function findActive(string $listType, string $matchType, string $matchValue): ?array
    {
        $this->ensureTable();

        $listType = in_array($listType, ['blacklist', 'warning'], true) ? $listType : 'warning';
        $matchType = in_array($matchType, ['name', 'phone', 'plate'], true) ? $matchType : 'name';
        $normalizedValue = $this->normalize($matchType, $matchValue);

        if ($normalizedValue === '') {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM watchlist_entries
             WHERE deleted_at IS NULL
               AND is_active = 1
               AND list_type = :list_type
               AND match_type = :match_type
               AND normalized_value = :normalized_value
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'list_type' => $listType,
            'match_type' => $matchType,
            'normalized_value' => $normalizedValue,
        ]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        return $entry ?: null;
    }

    public function save(array $data, ?int $userId): int
    {
        $this->ensureTable();

        $id = (int) ($data['id'] ?? 0);
        $listType = in_array(($data['list_type'] ?? ''), ['blacklist', 'warning'], true) ? $data['list_type'] : 'warning';
        $matchType = in_array(($data['match_type'] ?? ''), ['name', 'phone', 'plate'], true) ? $data['match_type'] : 'name';
        $matchValue = trim((string) ($data['match_value'] ?? ''));

        if ($matchValue === '') {
            throw new \RuntimeException('Eşleşecek değer zorunludur.');
        }

        $payload = [
            'list_type' => $listType,
            'match_type' => $matchType,
            'match_value' => $matchValue,
            'normalized_value' => $this->normalize($matchType, $matchValue),
            'reason' => trim((string) ($data['reason'] ?? '')) ?: null,
            'action_note' => trim((string) ($data['action_note'] ?? '')) ?: null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];

        if ($id > 0) {
            $stmt = Database::connection()->prepare(
                'UPDATE watchlist_entries
                 SET list_type = :list_type,
                     match_type = :match_type,
                     match_value = :match_value,
                     normalized_value = :normalized_value,
                     reason = :reason,
                     action_note = :action_note,
                     is_active = :is_active
                 WHERE id = :id
                   AND deleted_at IS NULL'
            );
            $stmt->execute($payload + ['id' => $id]);

            return $id;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO watchlist_entries (
                list_type,
                match_type,
                match_value,
                normalized_value,
                reason,
                action_note,
                is_active,
                created_by
             ) VALUES (
                :list_type,
                :match_type,
                :match_value,
                :normalized_value,
                :reason,
                :action_note,
                :is_active,
                :created_by
             )'
        );
        $stmt->execute($payload + ['created_by' => $userId]);

        return (int) Database::connection()->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $this->ensureTable();

        $stmt = Database::connection()->prepare(
            'UPDATE watchlist_entries
             SET deleted_at = NOW(), is_active = 0
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function matches(array $visitorData): array
    {
        $this->ensureTable();

        $values = [
            'name' => $this->normalize('name', (string) ($visitorData['full_name'] ?? '')),
            'phone' => $this->normalize('phone', (string) ($visitorData['phone'] ?? '')),
            'plate' => $this->normalize('plate', (string) ($visitorData['vehicle_plate'] ?? '')),
        ];
        $matches = [];

        foreach ($values as $type => $normalized) {
            if ($normalized === '') {
                continue;
            }

            $stmt = Database::connection()->prepare(
                'SELECT *
                 FROM watchlist_entries
                 WHERE deleted_at IS NULL
                   AND is_active = 1
                   AND match_type = :match_type
                   AND normalized_value = :normalized_value
                 ORDER BY list_type, id'
            );
            $stmt->execute([
                'match_type' => $type,
                'normalized_value' => $normalized,
            ]);
            $matches = array_merge($matches, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        return $matches;
    }

    public function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS watchlist_entries (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                list_type ENUM("blacklist", "warning") NOT NULL DEFAULT "warning",
                match_type ENUM("name", "phone", "plate") NOT NULL DEFAULT "name",
                match_value VARCHAR(180) NOT NULL,
                normalized_value VARCHAR(180) NOT NULL,
                reason TEXT NULL,
                action_note VARCHAR(255) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL,
                PRIMARY KEY (id),
                KEY idx_watchlist_lookup (match_type, normalized_value),
                KEY idx_watchlist_type (list_type, is_active),
                KEY idx_watchlist_deleted (deleted_at)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::$tableEnsured = true;
    }

    private function normalize(string $type, string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = strtr($value, [
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
        ]);

        if ($type === 'phone' || $type === 'plate') {
            return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $value) ?? '');
        }

        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
    }
}
