<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\DepartmentVerification;

final class DepartmentVerificationController
{
    public function respond(): string
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        $answer = (string) ($_GET['answer'] ?? '');

        if ($token === '' || !in_array($answer, ['yes', 'no'], true)) {
            http_response_code(400);

            return view('verifications/respond', [
                'title' => 'Cevap Geçersiz',
                'ok' => false,
                'message' => 'Doğrulama bağlantısı geçersiz.',
            ]);
        }

        $result = (new DepartmentVerification())->answerByToken($token, $answer);
        if (!$result['ok']) {
            http_response_code(409);
        }

        return view('verifications/respond', [
            'title' => $result['ok'] ? 'Cevap Kaydedildi' : 'Cevap Kaydedilemedi',
            'ok' => $result['ok'],
            'message' => $result['message'],
        ]);
    }

    public function answer(): never
    {
        Auth::requireLogin();

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            flash('error', 'Güvenlik doğrulaması başarısız oldu.');
            redirect('/dashboard');
        }

        $verificationId = (int) ($_POST['verification_id'] ?? 0);
        $answer = (string) ($_POST['answer'] ?? '');

        if ($verificationId <= 0 || !in_array($answer, ['yes', 'no'], true)) {
            flash('error', 'Departman cevabı geçersiz.');
            redirect('/dashboard');
        }

        $saved = (new DepartmentVerification())->answer($verificationId, (int) Auth::id(), $answer);
        flash($saved ? 'success' : 'error', $saved ? 'Departman cevabı kaydedildi.' : 'Departman sorusu bulunamadı veya daha önce cevaplandı.');
        redirect('/dashboard');
    }
}
