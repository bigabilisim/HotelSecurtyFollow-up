<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\MobileNotification;
use App\Models\PushSubscription as PushSubscriptionModel;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

final class WebPushService
{
    private MobileNotification $mobileNotifications;
    private PushSubscriptionModel $subscriptions;

    public function __construct()
    {
        $this->mobileNotifications = new MobileNotification();
        $this->subscriptions = new PushSubscriptionModel();
    }

    public function sendQueuedForVisit(int $visitId, ?string $eventType = null): array
    {
        $logs = $this->mobileNotifications->queuedLogsForVisit($visitId, $eventType);
        if (!$logs) {
            return ['attempted' => 0, 'sent' => 0, 'failed' => 0, 'expired' => 0, 'delivered_logs' => 0];
        }

        return $this->sendLogs($logs);
    }

    public function sendQueuedLogIds(array $logIds): array
    {
        $logs = $this->mobileNotifications->queuedLogsByIds($logIds);
        if (!$logs) {
            return ['attempted' => 0, 'sent' => 0, 'failed' => 0, 'expired' => 0, 'delivered_logs' => 0];
        }

        return $this->sendLogs($logs);
    }

    private function sendLogs(array $logs): array
    {
        $userIds = array_map(static fn (array $row): int => (int) $row['user_id'], $logs);
        $subscriptions = $this->subscriptions->activeForUsers($userIds);
        if (!$subscriptions) {
            return ['attempted' => 0, 'sent' => 0, 'failed' => 0, 'expired' => 0, 'delivered_logs' => 0];
        }

        $logsByUser = [];
        foreach ($logs as $log) {
            $logsByUser[(int) $log['user_id']][] = $log;
        }

        $webPush = new WebPush(
            $this->subscriptions->auth(),
            ['TTL' => 300, 'urgency' => 'high', 'batchSize' => 100, 'requestConcurrency' => 25],
            8
        );
        $queuedTargets = [];
        $attempted = 0;

        foreach ($subscriptions as $subscriptionRow) {
            $userLogs = $logsByUser[(int) $subscriptionRow['user_id']] ?? [];
            foreach ($userLogs as $log) {
                $payload = $this->payload($log);
                $subscription = Subscription::create([
                    'endpoint' => (string) $subscriptionRow['endpoint'],
                    'publicKey' => (string) $subscriptionRow['public_key'],
                    'authToken' => (string) $subscriptionRow['auth_token'],
                    'contentEncoding' => (string) ($subscriptionRow['content_encoding'] ?: 'aes128gcm'),
                ]);

                $webPush->queueNotification($subscription, $payload, ['TTL' => 300, 'urgency' => 'high']);
                $queuedTargets[] = [
                    'subscription_id' => (int) $subscriptionRow['id'],
                    'log_id' => (int) $log['id'],
                ];
                $attempted++;
            }
        }

        $sent = 0;
        $failed = 0;
        $expired = 0;
        $deliveredLogIds = [];
        $reportIndex = 0;

        foreach ($webPush->flush() as $report) {
            $target = $queuedTargets[$reportIndex] ?? null;
            $reportIndex++;
            if (!$target) {
                continue;
            }

            if ($report->isSuccess()) {
                $this->subscriptions->markSuccess((int) $target['subscription_id']);
                $deliveredLogIds[] = (int) $target['log_id'];
                $sent++;
                continue;
            }

            $isExpired = $report->isSubscriptionExpired();
            $this->subscriptions->markError((int) $target['subscription_id'], $report->getReason(), $isExpired);
            $failed++;
            $expired += $isExpired ? 1 : 0;
        }

        $deliveredLogIds = array_values(array_unique($deliveredLogIds));
        $this->mobileNotifications->markDeliveredByLogIds($deliveredLogIds);

        return [
            'attempted' => $attempted,
            'sent' => $sent,
            'failed' => $failed,
            'expired' => $expired,
            'delivered_logs' => count($deliveredLogIds),
        ];
    }

    private function payload(array $log): string
    {
        $payload = [
            'id' => (int) $log['id'],
            'title' => (string) ($log['title'] ?: 'Otel Güvenlik'),
            'message' => (string) $log['message'],
            'url' => (string) ($log['target_url'] ?: '/index.php?route=%2Fdashboard'),
            'created_at' => (string) $log['created_at'],
        ];

        $actionUrls = $this->departmentActionUrls($log);
        if ($actionUrls) {
            $payload['actions'] = [
                ['action' => 'yes', 'title' => 'Evet'],
                ['action' => 'no', 'title' => 'Hayır'],
            ];
            $payload['action_urls'] = $actionUrls;
            $payload['require_interaction'] = true;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function departmentActionUrls(array $log): array
    {
        $eventType = (string) ($log['event_type'] ?? '');
        if (!in_array($eventType, ['department_question', 'department_reminder', 'escalation'], true)) {
            return [];
        }

        $visitId = (int) ($log['visit_id'] ?? 0);
        $userId = (int) ($log['user_id'] ?? 0);
        if ($visitId <= 0 || $userId <= 0) {
            return [];
        }

        $this->ensureResponseTokenColumn();

        $stmt = Database::connection()->prepare(
            'SELECT dv.id, dv.response_token
             FROM department_verifications dv
             LEFT JOIN departments d ON d.id = dv.department_id
             WHERE dv.visit_id = :visit_id
               AND dv.answer IS NULL
               AND (
                    dv.sent_to_user_id = :sent_to_user_id
                    OR (dv.escalation_level = 0 AND d.manager_user_id = :manager_user_id)
               )
             ORDER BY dv.sent_at DESC, dv.id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'visit_id' => $visitId,
            'sent_to_user_id' => $userId,
            'manager_user_id' => $userId,
        ]);
        $verification = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$verification) {
            return [];
        }

        $token = trim((string) ($verification['response_token'] ?? ''));
        if ($token === '') {
            $token = bin2hex(random_bytes(24));
            $updateStmt = Database::connection()->prepare(
                'UPDATE department_verifications
                 SET response_token = :response_token
                 WHERE id = :id
                   AND (response_token IS NULL OR response_token = "")'
            );
            $updateStmt->execute([
                'id' => (int) $verification['id'],
                'response_token' => $token,
            ]);
        }

        return [
            'yes' => $this->absoluteRoute('/verifications/respond', [
                'token' => $token,
                'answer' => 'yes',
            ]),
            'no' => $this->absoluteRoute('/verifications/respond', [
                'token' => $token,
                'answer' => 'no',
            ]),
        ];
    }

    private function absoluteRoute(string $path, array $params = []): string
    {
        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');

        if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
        }

        return $baseUrl . \route($path, $params);
    }

    private function ensureResponseTokenColumn(): void
    {
        $pdo = Database::connection();
        $columns = $pdo->query('SHOW COLUMNS FROM department_verifications')->fetchAll(\PDO::FETCH_COLUMN);
        if (!in_array('response_token', $columns, true)) {
            $pdo->exec('ALTER TABLE department_verifications ADD COLUMN response_token VARCHAR(64) NULL AFTER question_text');
        }
    }
}
