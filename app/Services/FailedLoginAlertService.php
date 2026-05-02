<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Settings;
use App\Services\Notifications\NotificationQueue;
use App\Services\Notifications\NotificationService;

final class FailedLoginAlertService
{
    public function send(string $attemptedUsername): void
    {
        try {
            $settings = (new Settings())->all();
            if ((string) ($settings['security.failed_login_alert_enabled'] ?? '0') !== '1') {
                return;
            }

            $email = trim((string) ($settings['security.failed_login_alert_email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }

            $logId = (new NotificationService())->queueMailLog(
                $email,
                'Güvenlik Uyarıları',
                'Hatalı giriş denemesi - Otel Güvenlik Sistemi',
                $this->message($attemptedUsername)
            );

            if ($logId !== null) {
                (new NotificationQueue())->processIds([$logId]);
            }
        } catch (\Throwable) {
            // Login akışı güvenlik uyarısı gönderilemedi diye kesilmemeli.
        }
    }

    private function message(string $attemptedUsername): string
    {
        $attemptedUsername = trim($attemptedUsername);
        $attemptedUsername = $attemptedUsername !== '' ? $attemptedUsername : '(boş bırakıldı)';

        return implode("\n", [
            'Otel Güvenlik Sistemi giriş ekranında hatalı kullanıcı adı veya şifre denemesi oluştu.',
            '',
            'Denenen kullanıcı adı/e-posta: ' . $attemptedUsername,
            'Tarih: ' . date('d.m.Y H:i:s'),
            'IP adresi: ' . $this->serverValue('REMOTE_ADDR', 'Bilinmiyor'),
            'Cihaz/Tarayıcı: ' . $this->shortServerValue('HTTP_USER_AGENT', 'Bilinmiyor', 240),
            'Giriş sayfası: ' . $this->loginUrl(),
            '',
            'Bu bildirim admin panelindeki hatalı şifre uyarı ayarına göre otomatik gönderildi.',
        ]);
    }

    private function serverValue(string $key, string $default): string
    {
        $value = trim((string) ($_SERVER[$key] ?? ''));

        return $value !== '' ? $value : $default;
    }

    private function shortServerValue(string $key, string $default, int $maxLength): string
    {
        $value = $this->serverValue($key, $default);

        return strlen($value) > $maxLength ? substr($value, 0, $maxLength - 3) . '...' : $value;
    }

    private function loginUrl(): string
    {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return route('/login');
        }

        $proto = trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($proto === '') {
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        }

        return $proto . '://' . $host . route('/login');
    }
}
