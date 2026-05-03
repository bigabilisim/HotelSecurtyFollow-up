<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\User;
use App\Models\UserPreference;

final class AccountController
{
    public function password(): string
    {
        Auth::requireLogin();

        return view('account/password', [
            'title' => 'Şifre Değiştir',
        ]);
    }

    public function updatePassword(): never
    {
        Auth::requireLogin();

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            flash('error', 'Güvenlik doğrulaması başarısız oldu.');
            redirect('/account/password');
        }

        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newPasswordAgain = (string) ($_POST['new_password_again'] ?? '');

        if (strlen($newPassword) < 6) {
            flash('error', 'Yeni şifre en az 6 karakter olmalı.');
            redirect('/account/password');
        }

        if ($newPassword !== $newPasswordAgain) {
            flash('error', 'Yeni şifre tekrarı eşleşmiyor.');
            redirect('/account/password');
        }

        $userModel = new User();
        $user = $userModel->find((int) Auth::id());
        if (!$user || !password_verify($currentPassword, (string) $user['password_hash'])) {
            flash('error', 'Mevcut şifreniz hatalı.');
            redirect('/account/password');
        }

        $userModel->updatePassword((int) $user['id'], $newPassword);
        $_SESSION['user'] = $userModel->find((int) $user['id']);

        flash('success', 'Şifreniz güncellendi.');
        redirect('/account/password');
    }

    public function preferences(): string
    {
        Auth::requireLogin();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        return json_encode([
            'ok' => true,
            'preferences' => (new UserPreference())->all((int) Auth::id()),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function savePreferences(): string
    {
        Auth::requireLogin();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            return json_encode(['ok' => false, 'message' => 'Güvenlik doğrulaması başarısız.'], JSON_UNESCAPED_UNICODE);
        }

        $payload = json_decode((string) ($_POST['preferences'] ?? '{}'), true);
        if (!is_array($payload)) {
            http_response_code(422);
            return json_encode(['ok' => false, 'message' => 'Geçersiz profil verisi.'], JSON_UNESCAPED_UNICODE);
        }

        (new UserPreference())->setMany((int) Auth::id(), $payload);

        return json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    }
}
