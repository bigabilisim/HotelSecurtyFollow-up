<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\LoginIpBlock;
use App\Models\User;
use App\Services\FailedLoginAlertService;

final class AuthController
{
    public function login(): string
    {
        if (!(new User())->hasAnyUsers()) {
            redirect('/setup');
        }

        if (Auth::check()) {
            redirect('/dashboard');
        }

        return view('auth/login', [
            'title' => 'Giriş',
            'versionInfo' => config('versions', []),
        ]);
    }

    public function authenticate(): never
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            flash('error', 'Güvenlik doğrulaması başarısız oldu.');
            redirect('/login');
        }

        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $ipBlocks = new LoginIpBlock();
        $ipAddress = $ipBlocks->currentIp();

        $activeBlock = $ipBlocks->activeBlock($ipAddress);
        if ($activeBlock !== null) {
            flash('error', $activeBlock['status'] === 'permanent'
                ? 'Bu IP adresi güvenlik nedeniyle kalıcı olarak bloklandı. Sadece admin panelinden açılabilir.'
                : 'Bu IP adresi çok fazla hatalı şifre denemesi nedeniyle geçici olarak bloklandı. Kalan süre: yaklaşık ' . max(1, (int) $activeBlock['remaining_minutes']) . ' dakika.'
            );
            redirect('/login');
        }

        if (Auth::attempt($username, $password)) {
            $ipBlocks->recordSuccess($ipAddress);
            flash('success', 'Giriş başarılı.');
            redirect('/dashboard');
        }

        (new FailedLoginAlertService())->send($username);
        $failure = $ipBlocks->recordFailure($ipAddress, $username);

        flash('error', (string) ($failure['message'] ?? '') ?: 'Kullanıcı adı veya şifre hatalı.');
        redirect('/login');
    }

    public function logout(): never
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            flash('error', 'Güvenlik doğrulaması başarısız oldu.');
            redirect('/dashboard');
        }

        Auth::logout();
        redirect('/login');
    }
}
