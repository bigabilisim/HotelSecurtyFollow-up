<?php

declare(strict_types=1);

namespace App\Services\Notifications;

final class SimpleHttpClient
{
    public function postJson(string $url, array $payload, array $headers = [], int $timeout = 15): array
    {
        $headers[] = 'Content-Type: application/json';

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'ignore_errors' => true,
                'timeout' => $timeout,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $statusCode = $this->statusCode($http_response_header ?? []);

        if ($body === false) {
            return [
                'ok' => false,
                'status_code' => $statusCode,
                'body' => null,
                'error' => 'HTTP isteği gönderilemedi.',
            ];
        }

        return [
            'ok' => $statusCode >= 200 && $statusCode < 300,
            'status_code' => $statusCode,
            'body' => $body,
            'error' => null,
        ];
    }

    private function statusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) {
                return (int) $match[1];
            }
        }

        return 0;
    }
}
