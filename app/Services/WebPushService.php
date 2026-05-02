<?php

declare(strict_types=1);

namespace App\Services;

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
        return json_encode([
            'id' => (int) $log['id'],
            'title' => (string) ($log['title'] ?: 'Otel Güvenlik'),
            'message' => (string) $log['message'],
            'url' => (string) ($log['target_url'] ?: '/index.php?route=%2Fdashboard'),
            'created_at' => (string) $log['created_at'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
