<?php

declare(strict_types=1);

namespace App\Services\Notifications\Senders;

final class MailSender implements NotificationSenderInterface
{
    public function send(array $notification, array $config): array
    {
        if (($config['configured'] ?? true) === false) {
            return $this->skipped('Mail kanalı henüz yapılandırılmadı.');
        }

        if (trim((string) ($notification['recipient_address'] ?? '')) === '') {
            return $this->skipped('Mail alıcı adresi boş.');
        }

        if (($config['driver'] ?? 'php_mail') === 'smtp') {
            return $this->sendSmtp($notification, $config);
        }

        $headers = [];
        $fromEmail = $config['from_email'] ?? null;
        $fromName = $config['from_name'] ?? config('app.name', 'Otel Güvenlik Sistemi');

        if ($fromEmail) {
            $headers[] = 'From: ' . $this->encodeHeader((string) $fromName) . ' <' . $fromEmail . '>';
        }

        $mime = $this->mimeBody($notification);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: ' . $mime['content_type'];

        $sent = @mail(
            (string) $notification['recipient_address'],
            $this->encodeHeader((string) ($notification['subject'] ?? 'Otel Güvenlik Bildirimi')),
            $mime['body'],
            implode("\r\n", $headers)
        );

        return $sent
            ? ['status' => 'sent', 'provider_message_id' => null, 'error' => null]
            : ['status' => 'failed', 'provider_message_id' => null, 'error' => 'PHP mail() gönderimi başarısız oldu.'];
    }

    private function sendSmtp(array $notification, array $config): array
    {
        $host = trim((string) ($config['smtp_host'] ?? ''));
        $port = (int) ($config['smtp_port'] ?? 587);
        $encryption = (string) ($config['smtp_encryption'] ?? 'tls');
        $username = trim((string) ($config['smtp_username'] ?? ''));
        $password = (string) ($config['smtp_password'] ?? '');
        $timeout = max(5, (int) ($config['smtp_timeout'] ?? 20));
        $fromEmail = trim((string) ($config['from_email'] ?? ''));
        $fromName = trim((string) ($config['from_name'] ?? config('app.name', 'Otel Güvenlik Sistemi')));

        if ($host === '' || $fromEmail === '') {
            return $this->skipped('SMTP host veya gönderen mail adresi eksik.');
        }

        $socketHost = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $socket = @stream_socket_client($socketHost . ':' . $port, $errno, $error, $timeout);

        if (!is_resource($socket)) {
            return ['status' => 'failed', 'provider_message_id' => null, 'error' => 'SMTP bağlantısı kurulamadı: ' . $error];
        }

        stream_set_timeout($socket, $timeout);

        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO ' . $this->hostname(), [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('SMTP TLS başlatılamadı.');
                }
                $this->command($socket, 'EHLO ' . $this->hostname(), [250]);
            }

            if ($username !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($username), [334]);
                $this->command($socket, base64_encode($password), [235]);
            }

            $recipient = (string) $notification['recipient_address'];
            $this->command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);
            fwrite($socket, $this->message($notification, $fromEmail, $fromName) . "\r\n.\r\n");
            $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
            fclose($socket);

            return ['status' => 'sent', 'provider_message_id' => null, 'error' => null];
        } catch (\Throwable $error) {
            if (is_resource($socket)) {
                @fwrite($socket, "QUIT\r\n");
                @fclose($socket);
            }

            return ['status' => 'failed', 'provider_message_id' => null, 'error' => $error->getMessage()];
        }
    }

    /**
     * @param resource $socket
     * @param array<int> $expectedCodes
     */
    private function command($socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $expectedCodes);
    }

    /**
     * @param resource $socket
     * @param array<int> $expectedCodes
     */
    private function expect($socket, array $expectedCodes): string
    {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (preg_match('/^(\d{3})\s/', $line, $match)) {
                $code = (int) $match[1];
                if (!in_array($code, $expectedCodes, true)) {
                    throw new \RuntimeException('SMTP beklenmeyen cevap: ' . trim($response));
                }

                return $response;
            }
        }

        throw new \RuntimeException('SMTP sunucusundan cevap alınamadı.');
    }

    private function message(array $notification, string $fromEmail, string $fromName): string
    {
        $subject = $this->encodeHeader((string) ($notification['subject'] ?? 'Otel Güvenlik Bildirimi'));
        $mime = $this->mimeBody($notification);
        $body = $this->dotStuff($mime['body']);
        $to = (string) $notification['recipient_address'];

        return implode("\r\n", [
            'From: ' . $this->encodeHeader($fromName) . ' <' . $fromEmail . '>',
            'To: <' . $to . '>',
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: ' . $mime['content_type'],
            ...($this->hasAttachment($notification) ? [] : ['Content-Transfer-Encoding: 8bit']),
            '',
            $body,
        ]);
    }

    private function mimeBody(array $notification): array
    {
        $contentType = ($this->hasActions($notification) ? 'text/html' : 'text/plain') . '; charset=UTF-8';
        $body = $this->body($notification);

        if (!$this->hasAttachment($notification)) {
            return [
                'content_type' => $contentType,
                'body' => $body,
            ];
        }

        $boundary = '=_otel_security_' . md5((string) ($notification['id'] ?? '') . microtime(true));
        $path = (string) $notification['attachment_path'];
        $name = trim((string) ($notification['attachment_name'] ?? ''));
        $name = $name !== '' ? $name : basename($path);
        $attachment = chunk_split(base64_encode((string) file_get_contents($path)));

        return [
            'content_type' => 'multipart/mixed; boundary="' . $boundary . '"',
            'body' => implode("\r\n", [
                '--' . $boundary,
                'Content-Type: ' . $contentType,
                'Content-Transfer-Encoding: 8bit',
                '',
                $body,
                '--' . $boundary,
                'Content-Type: ' . $this->attachmentContentType($name) . '; name="' . $this->mimeName($name) . '"',
                'Content-Transfer-Encoding: base64',
                'Content-Disposition: attachment; filename="' . $this->mimeName($name) . '"',
                '',
                $attachment,
                '--' . $boundary . '--',
                '',
            ]),
        ];
    }

    private function body(array $notification): string
    {
        if (!$this->hasActions($notification)) {
            return (string) $notification['message'];
        }

        $message = nl2br(htmlspecialchars((string) $notification['message'], ENT_QUOTES, 'UTF-8'));
        $yesUrl = htmlspecialchars((string) $notification['action_yes_url'], ENT_QUOTES, 'UTF-8');
        $noUrl = htmlspecialchars((string) $notification['action_no_url'], ENT_QUOTES, 'UTF-8');

        return '<!doctype html><html><body style="font-family:Arial,sans-serif;line-height:1.5;color:#1f2926">'
            . '<p>' . $message . '</p>'
            . '<p style="margin-top:18px">'
            . '<a href="' . $yesUrl . '" style="display:inline-block;background:#0f766e;color:#fff;text-decoration:none;padding:11px 18px;border-radius:6px;font-weight:700;margin-right:8px">Evet</a>'
            . '<a href="' . $noUrl . '" style="display:inline-block;background:#b3261e;color:#fff;text-decoration:none;padding:11px 18px;border-radius:6px;font-weight:700">Hayır</a>'
            . '</p>'
            . '</body></html>';
    }

    private function hasActions(array $notification): bool
    {
        return trim((string) ($notification['action_yes_url'] ?? '')) !== ''
            && trim((string) ($notification['action_no_url'] ?? '')) !== '';
    }

    private function hasAttachment(array $notification): bool
    {
        $path = trim((string) ($notification['attachment_path'] ?? ''));

        return $path !== '' && is_file($path) && is_readable($path);
    }

    private function attachmentContentType(string $name): string
    {
        return match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'csv' => 'text/csv',
            default => 'application/octet-stream',
        };
    }

    private function dotStuff(string $message): string
    {
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $lines = explode("\n", $message);

        foreach ($lines as &$line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
        }

        return implode("\r\n", $lines);
    }

    private function skipped(string $message): array
    {
        return ['status' => 'skipped', 'provider_message_id' => null, 'error' => $message];
    }

    private function hostname(): string
    {
        return parse_url((string) config('app.url', 'localhost'), PHP_URL_HOST) ?: 'localhost';
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function mimeName(string $value): string
    {
        return addcslashes($value, "\\\"");
    }
}
