<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\MobileNotification;
use App\Models\PushSubscription;
use App\Models\User;

final class MobileNotificationController
{
    public function webPushConfig(): string
    {
        Auth::requireLogin();

        $user = (new User())->find((int) Auth::id());
        if (!$user || empty($user['mobile_notification_enabled'])) {
            return $this->json([
                'ok' => true,
                'enabled' => false,
                'publicKey' => '',
            ]);
        }

        return $this->json([
            'ok' => true,
            'enabled' => true,
            'publicKey' => (new PushSubscription())->publicKey(),
        ]);
    }

    public function subscribe(): string
    {
        Auth::requireLogin();

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            return $this->json(['ok' => false, 'message' => 'CSRF doğrulaması başarısız.']);
        }

        $user = (new User())->find((int) Auth::id());
        if (!$user || empty($user['mobile_notification_enabled'])) {
            http_response_code(403);
            return $this->json(['ok' => false, 'message' => 'Telefon bildirimi yetkiniz yok.']);
        }

        $subscriptionJson = (string) ($_POST['subscription'] ?? '');
        $subscription = json_decode($subscriptionJson, true);
        if (!is_array($subscription)) {
            http_response_code(422);
            return $this->json(['ok' => false, 'message' => 'Push abonelik bilgisi okunamadı.']);
        }

        try {
            (new PushSubscription())->saveForUser(
                (int) Auth::id(),
                $subscription,
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
            );
        } catch (\Throwable $error) {
            http_response_code(422);
            return $this->json(['ok' => false, 'message' => $error->getMessage()]);
        }

        return $this->json(['ok' => true, 'message' => 'Web Push aboneliği kaydedildi.']);
    }

    public function unsubscribe(): string
    {
        Auth::requireLogin();

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            return $this->json(['ok' => false, 'message' => 'CSRF doğrulaması başarısız.']);
        }

        $endpoint = trim((string) ($_POST['endpoint'] ?? ''));
        if ($endpoint !== '') {
            (new PushSubscription())->removeForUser((int) Auth::id(), $endpoint);
        }

        return $this->json(['ok' => true]);
    }

    public function poll(): string
    {
        Auth::requireLogin();

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            return $this->json(['ok' => false, 'notifications' => []]);
        }

        $user = (new User())->find((int) Auth::id());
        if (!$user || empty($user['mobile_notification_enabled'])) {
            return $this->json(['ok' => true, 'notifications' => []]);
        }

        $notifications = (new MobileNotification())->pullForUser((int) Auth::id());

        return $this->json([
            'ok' => true,
            'notifications' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'event_type' => (string) ($row['event_type'] ?? 'entry'),
                'title' => (string) $row['title'],
                'message' => (string) $row['message'],
                'url' => (string) ($row['target_url'] ?: '/index.php?route=%2Fdashboard'),
                'created_at' => (string) $row['created_at'],
            ], $notifications),
        ]);
    }

    private function json(array $payload): string
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
