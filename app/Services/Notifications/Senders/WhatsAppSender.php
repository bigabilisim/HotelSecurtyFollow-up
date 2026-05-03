<?php

declare(strict_types=1);

namespace App\Services\Notifications\Senders;

use App\Services\Notifications\SimpleHttpClient;

final class WhatsAppSender implements NotificationSenderInterface
{
    private SimpleHttpClient $http;

    public function __construct(SimpleHttpClient $http)
    {
        $this->http = $http;
    }

    public function send(array $notification, array $config): array
    {
        if (($config['configured'] ?? true) === false) {
            return $this->skipped('WhatsApp kanalı henüz yapılandırılmadı.');
        }

        $endpoint = $this->endpoint($config);
        if ($endpoint === '') {
            return $this->skipped('WhatsApp endpoint veya sağlayıcı ayarı eksik.');
        }

        $headers = [];
        $token = $config['access_token'] ?? $config['token'] ?? null;
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $payload = $this->payload($notification, $config);
        $response = $this->http->postJson($endpoint, $payload, $headers);

        if (!$response['ok']) {
            return [
                'status' => 'failed',
                'provider_message_id' => null,
                'error' => 'WhatsApp gönderimi başarısız. HTTP: ' . $response['status_code'] . ' ' . (string) $response['body'],
            ];
        }

        $body = json_decode((string) $response['body'], true);
        $messageId = $body['messages'][0]['id'] ?? $body['id'] ?? null;

        return [
            'status' => 'sent',
            'provider_message_id' => $messageId ? (string) $messageId : null,
            'error' => null,
        ];
    }

    private function endpoint(array $config): string
    {
        if (!empty($config['endpoint'])) {
            return strtr((string) $config['endpoint'], [
                '{api_version}' => (string) ($config['api_version'] ?? ''),
                '{phone_number_id}' => (string) ($config['phone_number_id'] ?? ''),
            ]);
        }

        if (!empty($config['api_version']) && !empty($config['phone_number_id'])) {
            return 'https://graph.facebook.com/' . $config['api_version'] . '/' . $config['phone_number_id'] . '/messages';
        }

        return '';
    }

    private function payload(array $notification, array $config): array
    {
        if (($config['provider'] ?? '') === 'meta_cloud' || !empty($config['phone_number_id'])) {
            return [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $this->normalizeRecipient((string) $notification['recipient_address']),
                'type' => 'text',
                'text' => [
                    'body' => $this->messageWithActions($notification),
                    'preview_url' => false,
                ],
            ];
        }

        return [
            'to' => (string) $notification['recipient_address'],
            'subject' => (string) ($notification['subject'] ?? ''),
            'message' => $this->messageWithActions($notification),
        ];
    }

    private function messageWithActions(array $notification): string
    {
        $message = (string) $notification['message'];

        if (!$this->hasActions($notification)) {
            return $message;
        }

        return $message
            . "\n\nEvet: " . (string) $notification['action_yes_url']
            . "\nHayır: " . (string) $notification['action_no_url'];
    }

    private function hasActions(array $notification): bool
    {
        return trim((string) ($notification['action_yes_url'] ?? '')) !== ''
            && trim((string) ($notification['action_no_url'] ?? '')) !== '';
    }

    private function normalizeRecipient(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?: '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '90' . substr($digits, 1);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '5')) {
            return '90' . $digits;
        }

        return $digits !== '' ? $digits : trim($value);
    }

    private function skipped(string $message): array
    {
        return ['status' => 'skipped', 'provider_message_id' => null, 'error' => $message];
    }
}
