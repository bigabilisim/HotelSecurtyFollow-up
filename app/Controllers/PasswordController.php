<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Models\NotificationChannel;
use App\Models\PasswordResetToken;
use App\Models\Settings;
use App\Models\User;
use App\Services\Notifications\Senders\MailSender;

final class PasswordController
{
    public function forgot(): string
    {
        return view('auth/forgot-password', [
            'title' => 'Şifremi Unuttum',
            'settings' => (new Settings())->all(),
        ]);
    }

    public function sendResetLink(): never
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            flash('error', 'Güvenlik doğrulaması başarısız oldu.');
            redirect('/password/forgot');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Geçerli bir e-posta adresi yazın.');
            redirect('/password/forgot');
        }

        if (!$this->isMailChannelReady()) {
            flash('error', 'Şifre sıfırlama mail servisi şu anda hazır değil. Lütfen yöneticinizden Mail kanal ayarlarını kontrol etmesini isteyin.');
            redirect('/password/forgot');
        }

        $user = (new User())->findActiveByEmail($email);
        if ($user) {
            try {
                $tokens = new PasswordResetToken();
                $tokens->cleanupExpired();
                $token = $tokens->create((int) $user['id'], (string) $user['email']);
                $this->sendResetMail($user, $token);
            } catch (\Throwable $error) {
                error_log('Password reset mail error: ' . $error->getMessage());
            }
        }

        flash('success', 'E-posta sistemde kayıtlıysa şifre sıfırlama bağlantısı gönderildi.');
        redirect('/password/forgot');
    }

    public function reset(): string
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        $reset = (new PasswordResetToken())->findValid($token);

        return view('auth/reset-password', [
            'title' => 'Yeni Şifre',
            'settings' => (new Settings())->all(),
            'token' => $token,
            'reset' => $reset,
        ]);
    }

    public function update(): never
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            flash('error', 'Güvenlik doğrulaması başarısız oldu.');
            redirect('/password/forgot');
        }

        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        $resetTokens = new PasswordResetToken();
        $reset = $resetTokens->findValid($token);

        if (!$reset) {
            flash('error', 'Şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş.');
            redirect('/password/forgot');
        }

        if (strlen($password) < 6) {
            flash('error', 'Yeni şifre en az 6 karakter olmalıdır.');
            redirect('/password/reset', ['token' => $token]);
        }

        if ($password !== $confirmation) {
            flash('error', 'Şifre tekrarı eşleşmiyor.');
            redirect('/password/reset', ['token' => $token]);
        }

        (new User())->updatePassword((int) $reset['user_id'], $password);
        $resetTokens->markUsed((int) $reset['id']);

        flash('success', 'Şifreniz güncellendi. Yeni şifrenizle giriş yapabilirsiniz.');
        redirect('/login');
    }

    private function sendResetMail(array $user, string $token): bool
    {
        $channel = (new NotificationChannel())->findByCode('mail');
        if (!$channel || (int) ($channel['is_enabled'] ?? 0) !== 1) {
            error_log('Password reset mail skipped: Mail channel is inactive or missing.');
            return false;
        }

        $config = json_decode((string) ($channel['config_json'] ?? '{}'), true);
        if (!is_array($config) || empty($config['configured'])) {
            error_log('Password reset mail skipped: Mail channel is not configured.');
            return false;
        }

        $resetUrl = $this->absoluteRoute('/password/reset', ['token' => $token]);
        $name = trim((string) ($user['full_name'] ?? ''));
        $message = implode("\n\n", [
            'Merhaba ' . ($name !== '' ? $name : 'Kullanıcı') . ',',
            'Otel Güvenlik Sistemi için şifre sıfırlama talebi aldık.',
            'Yeni şifrenizi belirlemek için aşağıdaki bağlantıyı açın:',
            $resetUrl,
            'Bu bağlantı 60 dakika geçerlidir ve yalnızca bir kez kullanılabilir.',
            'Bu talebi siz yapmadıysanız bu mesajı dikkate almayın.',
        ]);

        $result = (new MailSender())->send([
            'recipient_address' => (string) $user['email'],
            'subject' => 'Otel Güvenlik Sistemi Şifre Sıfırlama',
            'message' => $message,
        ], $config);

        $sent = ($result['status'] ?? 'failed') === 'sent';
        if (!$sent) {
            error_log('Password reset mail failed: ' . (string) ($result['error'] ?? 'Unknown mail sender error.'));
        }

        return $sent;
    }

    private function isMailChannelReady(): bool
    {
        $channel = (new NotificationChannel())->findByCode('mail');
        if (!$channel || (int) ($channel['is_enabled'] ?? 0) !== 1) {
            return false;
        }

        $config = json_decode((string) ($channel['config_json'] ?? '{}'), true);

        return is_array($config) && !empty($config['configured']);
    }

    private function absoluteRoute(string $path, array $params = []): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $scheme = 'http';

        if (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        ) {
            $scheme = 'https';
        }

        if ($host === '') {
            $appUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
            $host = (string) parse_url($appUrl, PHP_URL_HOST);
            $scheme = (string) (parse_url($appUrl, PHP_URL_SCHEME) ?: $scheme);
        }

        $query = http_build_query(array_merge(['route' => $path], $params));

        return $scheme . '://' . $host . '/index.php?' . $query;
    }
}
