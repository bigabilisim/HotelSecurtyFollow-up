<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\User;

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
}
