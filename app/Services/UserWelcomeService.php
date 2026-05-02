<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Notifications\NotificationQueue;
use App\Services\Notifications\NotificationService;

final class UserWelcomeService
{
    public function send(array $user, string $plainPassword, bool $processQueue = true): array
    {
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '') {
            return [
                'queued' => 0,
                'sent' => 0,
                'failed' => 0,
                'message' => 'Kullanıcının e-posta adresi olmadığı için giriş bilgisi maili gönderilmedi.',
            ];
        }

        $queued = (new NotificationService())->queueMail(
            $email,
            (string) ($user['full_name'] ?? ''),
            'Otel Güvenlik Sistemi Giriş Bilgileriniz',
            $this->message($user, $plainPassword)
        );

        $queueSummary = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        if ($queued > 0 && $processQueue) {
            $queueSummary = (new NotificationQueue())->process(10);
        }

        return [
            'queued' => $queued,
            'sent' => (int) ($queueSummary['sent'] ?? 0),
            'failed' => (int) ($queueSummary['failed'] ?? 0),
            'message' => $queued > 0
                ? 'Giriş bilgileri mail kuyruğuna eklendi.'
                : 'Giriş bilgileri mail kuyruğuna eklenemedi.',
        ];
    }

    private function message(array $user, string $plainPassword): string
    {
        $name = trim((string) ($user['full_name'] ?? ''));
        $username = trim((string) ($user['username'] ?? ''));
        $loginUrl = $this->absoluteUrl('/login');
        $passwordUrl = $this->absoluteUrl('/account/password');

        return implode("\n", [
            'Merhaba ' . ($name !== '' ? $name : 'Kullanıcı') . ',',
            '',
            'Otel Güvenlik Sistemi kullanıcı hesabınız oluşturuldu.',
            '',
            'Giriş adresi: ' . $loginUrl,
            'Kullanıcı adı: ' . $username,
            'Geçici şifre: ' . $plainPassword,
            '',
            'İlk girişten sonra güvenliğiniz için şifrenizi değiştirmenizi öneririz.',
            'Şifre değiştirme ekranı: ' . $passwordUrl,
            '',
            'Bu bilgileri yalnızca sizin kullanmanız gerekir. Şifrenizi kimseyle paylaşmayın.',
        ]);
    }

    private function absoluteUrl(string $route): string
    {
        return rtrim((string) config('app.url', ''), '/') . route($route);
    }
}
