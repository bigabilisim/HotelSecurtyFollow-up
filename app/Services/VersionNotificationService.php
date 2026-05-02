<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Settings;
use App\Services\Notifications\NotificationService;
use PDO;

final class VersionNotificationService
{
    public function preview(bool $allowUnpublished = false): array
    {
        return $this->build(false, false, $allowUnpublished);
    }

    public function notify(bool $force = false, bool $allowUnpublished = false): array
    {
        return $this->build(true, $force, $allowUnpublished);
    }

    private function build(bool $send, bool $force, bool $allowUnpublished): array
    {
        $entry = $this->currentEntry();
        if (!$entry) {
            return [
                'status' => 'missing_version',
                'version' => null,
                'recipient_count' => 0,
                'queued_count' => 0,
                'message' => 'Güncel sürüm bilgisi bulunamadı.',
            ];
        }

        $version = (string) ($entry['version'] ?? '');
        $publishedAt = trim((string) ($entry['published_at'] ?? ''));
        $recipients = $this->recipients();
        $lastSentVersion = $this->lastSentVersion();

        if ($publishedAt === '' && !$allowUnpublished) {
            return [
                'status' => 'not_published',
                'version' => $version,
                'recipient_count' => count($recipients),
                'queued_count' => 0,
                'message' => 'Sürüm henüz yayın tarihi almadı; mail gönderimi atlandı.',
            ];
        }

        if (!$force && $lastSentVersion === $version) {
            return [
                'status' => 'already_sent',
                'version' => $version,
                'recipient_count' => count($recipients),
                'queued_count' => 0,
                'message' => 'Bu sürüm için daha önce mail bildirimi gönderilmiş.',
            ];
        }

        if (!$send) {
            return [
                'status' => 'preview',
                'version' => $version,
                'recipient_count' => count($recipients),
                'queued_count' => 0,
                'message' => 'Ön izleme tamamlandı; mail kuyruğuna kayıt eklenmedi.',
            ];
        }

        $queuedCount = 0;
        $notificationService = new NotificationService();
        foreach ($recipients as $recipient) {
            $queuedCount += $notificationService->queueMail(
                (string) $recipient['email'],
                (string) $recipient['full_name'],
                'Otel Güvenlik Sistemi ' . $version . ' Sürüm Güncellemesi',
                $this->message($entry, (string) $recipient['full_name'])
            );
        }

        (new Settings())->setMany([
            'version_notifications.last_sent_version' => $version,
            'version_notifications.last_sent_at' => date('c'),
            'version_notifications.last_recipient_count' => (string) count($recipients),
            'version_notifications.last_queued_count' => (string) $queuedCount,
        ]);

        return [
            'status' => 'queued',
            'version' => $version,
            'recipient_count' => count($recipients),
            'queued_count' => $queuedCount,
            'message' => 'Sürüm bildirimleri mail kuyruğuna eklendi.',
        ];
    }

    private function currentEntry(): ?array
    {
        $versionConfig = config('versions', []);
        $current = (string) ($versionConfig['current'] ?? '');
        $entries = is_array($versionConfig['entries'] ?? null) ? $versionConfig['entries'] : [];

        foreach ($entries as $entry) {
            if ((string) ($entry['version'] ?? '') === $current) {
                return $entry;
            }
        }

        return $entries[0] ?? null;
    }

    private function recipients(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, full_name, email
             FROM users
             WHERE status = "active"
               AND deleted_at IS NULL
               AND email IS NOT NULL
               AND email <> ""
             ORDER BY full_name'
        );

        $seen = [];
        $recipients = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $email = strtolower(trim((string) $row['email']));
            if ($email === '' || isset($seen[$email])) {
                continue;
            }

            $seen[$email] = true;
            $recipients[] = $row;
        }

        return $recipients;
    }

    private function lastSentVersion(): string
    {
        return (string) ((new Settings())->all()['version_notifications.last_sent_version'] ?? '');
    }

    private function message(array $entry, string $recipientName): string
    {
        $version = (string) ($entry['version'] ?? '');
        $publishedAt = trim((string) ($entry['published_at'] ?? ''));
        $publishedText = $publishedAt !== '' ? date('d.m.Y', strtotime($publishedAt) ?: time()) : 'Test';
        $items = is_array($entry['items'] ?? null) ? $entry['items'] : [];
        $lines = [
            'Merhaba ' . ($recipientName !== '' ? $recipientName : 'Kullanıcı') . ',',
            '',
            'Otel Güvenlik Sistemi ' . $version . ' sürümü yayınlandı.',
            'Yayın tarihi: ' . $publishedText,
            '',
            (string) ($entry['title'] ?? 'Sürüm Güncellemesi'),
            (string) ($entry['summary'] ?? ''),
            '',
            'Yenilikler:',
        ];

        foreach ($items as $item) {
            $lines[] = '- ' . (string) $item;
        }

        $lines[] = '';
        $lines[] = 'Detayları giriş ekranındaki sürüm kutusundan da görebilirsiniz.';

        return implode("\n", $lines);
    }
}
