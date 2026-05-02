<?php

declare(strict_types=1);

namespace App\Services\Notifications\Senders;

use App\Services\Notifications\SimpleHttpClient;

final class TelegramSender implements NotificationSenderInterface
{
    private SimpleHttpClient $http;

    public function __construct(SimpleHttpClient $http)
    {
        $this->http = $http;
    }

    public function send(array $notification, array $config): array
    {
        if (($config['configured'] ?? true) === false) {
            return $this->skipped('Telegram kanalı henüz yapılandırılmadı.');
        }

        $botToken = trim((string) ($config['bot_token'] ?? ''));
        if ($botToken === '') {
            return $this->skipped('Telegram bot token eksik.');
        }

        $payload = [
            'chat_id' => (string) $notification['recipient_address'],
            'text' => (string) $notification['message'],
        ];

        if (!empty($config['parse_mode'])) {
            $payload['parse_mode'] = (string) $config['parse_mode'];
        }

        if ($this->hasActions($notification)) {
            $payload['reply_markup'] = [
                'inline_keyboard' => [[
                    [
                        'text' => 'Evet',
                        'url' => (string) $notification['action_yes_url'],
                    ],
                    [
                        'text' => 'Hayır',
                        'url' => (string) $notification['action_no_url'],
                    ],
                ]],
            ];
        }

        $response = $this->http->postJson('https://api.telegram.org/bot' . $botToken . '/sendMessage', $payload);

        if (!$response['ok']) {
            return [
                'status' => 'failed',
                'provider_message_id' => null,
                'error' => 'Telegram gönderimi başarısız. HTTP: ' . $response['status_code'] . ' ' . (string) $response['body'],
            ];
        }

        $body = json_decode((string) $response['body'], true);
        $messageId = $body['result']['message_id'] ?? null;

        return [
            'status' => 'sent',
            'provider_message_id' => $messageId ? (string) $messageId : null,
            'error' => null,
        ];
    }

    private function skipped(string $message): array
    {
        return ['status' => 'skipped', 'provider_message_id' => null, 'error' => $message];
    }

    private function hasActions(array $notification): bool
    {
        return trim((string) ($notification['action_yes_url'] ?? '')) !== ''
            && trim((string) ($notification['action_no_url'] ?? '')) !== '';
    }
}
