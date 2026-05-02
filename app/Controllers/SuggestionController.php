<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\UserSuggestion;
use App\Services\Notifications\NotificationQueue;
use App\Services\Notifications\NotificationService;

final class SuggestionController
{
    private const SUGGESTION_MAIL_RECIPIENT = 'info@bigabilisim.com';

    public function store(): never
    {
        Auth::requireLogin();

        $returnRoute = $this->returnRoute((string) ($_POST['return_route'] ?? '/dashboard'));

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            flash('error', 'Güvenlik doğrulaması başarısız oldu.');
            redirect($returnRoute);
        }

        $payload = [
            'suggestion_type' => $_POST['suggestion_type'] ?? 'improvement',
            'priority' => $_POST['priority'] ?? 'normal',
            'title' => $_POST['title'] ?? '',
            'message' => $_POST['message'] ?? '',
            'page_route' => $_POST['page_route'] ?? $returnRoute,
        ];
        $user = Auth::user() ?? [];

        try {
            $suggestionId = (new UserSuggestion())->create($payload, $user);
        } catch (\Throwable $error) {
            flash('error', $error->getMessage());
            redirect($returnRoute);
        }

        $this->notifySuggestionByMail($suggestionId, $payload, $user);

        flash('success', 'Öneriniz alındı. Teşekkürler, yönetim panelinde değerlendirilecek.');
        redirect($returnRoute);
    }

    private function returnRoute(string $route): string
    {
        $route = trim($route);

        if ($route === '' || !str_starts_with($route, '/')) {
            return '/dashboard';
        }

        if (str_contains($route, "\n") || str_contains($route, "\r")) {
            return '/dashboard';
        }

        return $route;
    }

    private function notifySuggestionByMail(int $suggestionId, array $payload, array $user): void
    {
        try {
            $title = trim((string) ($payload['title'] ?? ''));
            $subject = $this->truncate('Otel Güvenlik Kullanıcı Önerisi: ' . ($title !== '' ? $title : 'Yeni öneri'), 180);
            $userName = trim((string) ($user['full_name'] ?? ''));
            $userEmail = trim((string) ($user['email'] ?? ''));
            $sender = $userName !== '' ? $userName : 'Kullanıcı';

            if ($userEmail !== '') {
                $sender .= ' <' . $userEmail . '>';
            }

            $message = implode("\n", [
                'Yeni kullanıcı önerisi alındı.',
                '',
                'Öneri ID: #' . $suggestionId,
                'Konu: ' . ($title !== '' ? $title : '-'),
                'Tür: ' . $this->suggestionTypeLabel((string) ($payload['suggestion_type'] ?? '')),
                'Öncelik: ' . $this->priorityLabel((string) ($payload['priority'] ?? '')),
                'Gönderen: ' . $sender,
                'Sayfa: ' . ((string) ($payload['page_route'] ?? '') ?: '-'),
                'Tarih: ' . date('d.m.Y H:i:s'),
                '',
                'Açıklama:',
                trim((string) ($payload['message'] ?? '')) ?: '-',
            ]);

            $queued = (new NotificationService())->queueMail(
                self::SUGGESTION_MAIL_RECIPIENT,
                'Biga Bilişim',
                $subject,
                $message
            );

            if ($queued > 0) {
                (new NotificationQueue())->process(50);
            }
        } catch (\Throwable $error) {
            error_log('Suggestion notification mail error: ' . $error->getMessage());
        }
    }

    private function suggestionTypeLabel(string $type): string
    {
        return [
            'improvement' => 'İyileştirme',
            'bug' => 'Hata Bildirimi',
            'feature' => 'Yeni Özellik',
            'support' => 'Destek / Eğitim',
        ][$type] ?? 'İyileştirme';
    }

    private function priorityLabel(string $priority): string
    {
        return [
            'normal' => 'Normal',
            'high' => 'Önemli',
        ][$priority] ?? 'Normal';
    }

    private function truncate(string $value, int $length): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $length, 'UTF-8');
        }

        return substr($value, 0, $length);
    }
}
