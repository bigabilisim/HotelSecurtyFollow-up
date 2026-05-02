<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Models\ReservationlessReview;

final class ReservationlessReviewController
{
    public function show(): string
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        $review = $token !== '' ? (new ReservationlessReview())->findByToken($token) : null;

        if (!$review) {
            http_response_code(404);
        }

        return view('reservationless/review', [
            'title' => $review ? 'Rezervasyonsuz Giriş Oda Bilgisi' : 'Bağlantı Geçersiz',
            'review' => $review,
            'token' => $token,
            'result' => null,
        ]);
    }

    public function submit(): string
    {
        $token = trim((string) ($_POST['token'] ?? ''));
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(400);

            return view('reservationless/review', [
                'title' => 'Güvenlik Doğrulaması',
                'review' => null,
                'token' => $token,
                'result' => ['ok' => false, 'message' => 'Güvenlik doğrulaması başarısız oldu.'],
            ]);
        }

        $model = new ReservationlessReview();
        $result = $model->submit(
            $token,
            (string) ($_POST['room_number'] ?? ''),
            (string) ($_POST['manager_note'] ?? '')
        );
        $review = $token !== '' ? $model->findByToken($token) : null;

        if (!$result['ok']) {
            http_response_code(409);
        }

        return view('reservationless/review', [
            'title' => $result['ok'] ? 'Oda Bilgisi Kaydedildi' : 'Oda Bilgisi Kaydedilemedi',
            'review' => $review,
            'token' => $token,
            'result' => $result,
        ]);
    }
}
