<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Core\Database;
use PDO;

final class NotificationQueue
{
    private const MAX_ATTEMPTS = 3;

    public function process(int $limit = 20): array
    {
        $limit = max(1, min($limit, 200));
        return $this->processLogs($this->pendingLogs($limit));
    }

    /**
     * @param array<int> $ids
     */
    public function processIds(array $ids): array
    {
        return $this->processLogs($this->logsByIds($ids));
    }

    private function processLogs(array $logs): array
    {
        $summary = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($logs as $log) {
            $summary['processed']++;

            try {
                if ((int) ($log['channel_enabled'] ?? 1) !== 1) {
                    $result = [
                        'status' => 'skipped',
                        'provider_message_id' => null,
                        'error' => 'Bildirim kanalı pasif.',
                    ];
                } else {
                    $sender = NotificationSenderFactory::make((string) $log['channel_code']);
                    $result = $sender->send($log, $this->decodeConfig($log['channel_config_json'] ?? null));
                }
            } catch (\Throwable $error) {
                $result = [
                    'status' => 'failed',
                    'provider_message_id' => null,
                    'error' => $error->getMessage(),
                ];
            }

            $status = in_array($result['status'] ?? '', ['sent', 'failed', 'skipped'], true)
                ? $result['status']
                : 'failed';

            $this->markResult(
                (int) $log['id'],
                $status,
                $result['provider_message_id'] ?? null,
                $result['error'] ?? null
            );

            $summary[$status]++;
        }

        return $summary;
    }

    /**
     * @param array<int> $ids
     */
    private function logsByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            'SELECT
                nl.*,
                nc.code AS channel_code,
                nc.name AS channel_name,
                nc.is_enabled AS channel_enabled,
                nc.config_json AS channel_config_json
             FROM notification_logs nl
             INNER JOIN notification_channels nc ON nc.id = nl.channel_id
             WHERE nl.id IN (' . $placeholders . ')
               AND nl.status IN ("queued", "failed")
               AND nl.attempt_count < ?
             ORDER BY nl.queued_at ASC, nl.id ASC'
        );

        foreach ($ids as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->bindValue(count($ids) + 1, self::MAX_ATTEMPTS, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function pendingLogs(int $limit): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT
                nl.*,
                nc.code AS channel_code,
                nc.name AS channel_name,
                nc.is_enabled AS channel_enabled,
                nc.config_json AS channel_config_json
             FROM notification_logs nl
             INNER JOIN notification_channels nc ON nc.id = nl.channel_id
             WHERE nl.status IN ("queued", "failed")
               AND nl.attempt_count < :max_attempts
             ORDER BY nl.queued_at ASC, nl.id ASC
             LIMIT ' . $limit
        );
        $stmt->bindValue(':max_attempts', self::MAX_ATTEMPTS, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function markResult(int $logId, string $status, ?string $providerMessageId, ?string $error): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE notification_logs
             SET status = :status,
                 provider_message_id = :provider_message_id,
                 error_message = :error_message,
                 attempt_count = attempt_count + 1,
                 sent_at = CASE WHEN :sent_status = "sent" THEN NOW() ELSE sent_at END
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $logId,
            'status' => $status,
            'sent_status' => $status,
            'provider_message_id' => $providerMessageId,
            'error_message' => $error,
        ]);
    }

    private function decodeConfig(?string $json): array
    {
        if (!$json) {
            return [];
        }

        $config = json_decode($json, true);
        return is_array($config) ? $config : [];
    }
}
