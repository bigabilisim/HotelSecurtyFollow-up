<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Dashboard;
use App\Models\Settings;
use App\Services\DashboardWorkLimiter;
use App\Services\Notifications\NotificationQueue;
use App\Services\TimeoutMonitor;

final class DashboardController
{
    public function index(): string
    {
        Auth::requireLogin();

        $dashboard = new Dashboard();
        $liveData = $this->liveData($dashboard);

        return view('dashboard/index', [
            'title' => 'Canlı Panel',
            'settings' => (new Settings())->all(),
            'categories' => $dashboard->categories(),
            'quickCategories' => $dashboard->quickCategories(),
            'departments' => $dashboard->departments(),
            'visitorSuggestions' => $dashboard->visitorSuggestions(),
        ] + $liveData);
    }

    public function guide(): string
    {
        $isLoggedIn = Auth::check();

        return view('admin/guide', [
            'title' => 'V1.12 Kullanım Kılavuzu',
            'settings' => (new Settings())->all(),
            'backRoute' => $isLoggedIn ? '/dashboard' : '/login',
            'backLabel' => $isLoggedIn ? 'Canlı panele dön' : 'Giriş ekranına dön',
        ]);
    }

    public function heartbeat(): string
    {
        Auth::requireLogin();

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            return json_encode(['ok' => false, 'message' => 'CSRF doğrulaması başarısız.'], JSON_UNESCAPED_UNICODE);
        }

        $work = (new DashboardWorkLimiter())->run('dashboard-heartbeat', 20, static function (): array {
            return [
                'timeouts' => (new TimeoutMonitor())->process(100),
                'notifications' => (new NotificationQueue())->process(10),
            ];
        });
        $result = is_array($work['result'] ?? null) ? $work['result'] : [];
        $timeouts = is_array($result['timeouts'] ?? null)
            ? $result['timeouts']
            : ['warnings' => 0, 'overdue' => 0, 'questions' => 0, 'escalations' => 0, 'reminders' => 0];
        $notifications = is_array($result['notifications'] ?? null)
            ? $result['notifications']
            : ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        $dashboard = new Dashboard();
        $signature = $dashboard->liveSignature((int) Auth::id());
        $clientSignature = trim((string) ($_POST['signature'] ?? ''));
        $changed = $clientSignature === '' || $clientSignature !== $signature;
        $fragments = $changed ? $this->liveFragments($this->liveData($dashboard, $signature)) : null;

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        return json_encode([
            'ok' => true,
            'changed' => $changed,
            'work_ran' => !empty($work['ran']),
            'signature' => $signature,
            'html' => $fragments,
            'timeouts' => $timeouts,
            'notifications' => $notifications,
        ], JSON_UNESCAPED_UNICODE);
    }

    private function liveData(Dashboard $dashboard, ?string $signature = null): array
    {
        return array_merge([
            'stats' => $dashboard->stats(),
            'insideVisits' => $dashboard->insideVisits(),
            'recentActivity' => $dashboard->recentActivity(),
            'pendingVerifications' => $dashboard->pendingVerifications((int) Auth::id()),
            'categories' => $dashboard->categories(),
            'departments' => $dashboard->departments(),
            'liveSignature' => $signature ?? $dashboard->liveSignature((int) Auth::id()),
        ], $this->viewHelpers());
    }

    private function liveFragments(array $data): array
    {
        return [
            'stats' => $this->partial('dashboard/partials/stats', $data),
            'verifications' => $this->partial('dashboard/partials/pending-verifications', $data),
            'inside' => $this->partial('dashboard/partials/inside-visits', $data),
            'activity' => $this->partial('dashboard/partials/recent-activity', $data),
        ];
    }

    private function partial(string $template, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require BASE_PATH . '/app/Views/' . $template . '.php';

        return (string) ob_get_clean();
    }

    private function viewHelpers(): array
    {
        $statusLabels = [
            'inside' => ['Normal', 'ok'],
            'overdue' => ['Süre aşıldı', 'overdue'],
            'department_asked' => ['Amir sorusu', 'warning'],
            'department_approved' => ['Amir onayladı', 'ok'],
            'escalated' => ['Yöneticiye gitti', 'risk'],
            'cancelled' => ['İptal', 'muted'],
        ];
        $activityLabels = [
            'entry' => ['Giriş kaydı', 'ok'],
            'exit' => ['Çıkış kaydı', 'muted'],
            'timeout_warning' => ['Süre uyarısı', 'warning'],
            'department_question' => ['Departman sorusu', 'warning'],
            'department_question_reminder' => ['Amir hatırlatma', 'warning'],
            'department_answer_yes' => ['Amir onayı', 'ok'],
            'department_answer_no' => ['Olumsuz cevap', 'risk'],
            'department_no_response' => ['Cevap yok', 'risk'],
            'escalation' => ['Eskalasyon', 'risk'],
            'note' => ['Not', 'muted'],
        ];
        $maskLiveNames = Auth::hasRole('security') && !Auth::canAny([
            'users.manage',
            'reports.view',
            'reports.manage',
            'settings.manage',
            'notifications.manage',
            'categories.manage',
            'departments.manage',
            'backups.manage',
        ]);
        $maskName = static function (?string $value) use ($maskLiveNames): string {
            $name = trim((string) $value);
            if (!$maskLiveNames || $name === '') {
                return $name;
            }

            $parts = preg_split('/\s+/', $name) ?: [];
            $masked = array_map(static function (string $part): string {
                if ($part === '') {
                    return '';
                }

                $length = function_exists('mb_strlen') ? mb_strlen($part, 'UTF-8') : strlen($part);
                $visibleLength = min(3, $length);
                $visible = function_exists('mb_substr')
                    ? mb_substr($part, 0, $visibleLength, 'UTF-8')
                    : substr($part, 0, $visibleLength);

                return $visible . '****';
            }, $parts);

            return trim(implode(' ', array_filter($masked, static fn (string $part): bool => $part !== '')));
        };

        return [
            'statusLabels' => $statusLabels,
            'activityLabels' => $activityLabels,
            'maskName' => $maskName,
            'dashboardBlockAccess' => [
                'door' => Auth::can('dashboard.block.door') && Auth::can('visits.create_entry'),
                'inside' => Auth::can('dashboard.block.inside') && Auth::can('visits.view_all'),
                'activity' => Auth::can('dashboard.block.activity'),
                'stats' => Auth::can('dashboard.block.stats'),
                'verifications' => Auth::can('dashboard.block.verifications') && Auth::can('visits.department_verify'),
            ],
            'canCustomizeDashboard' => Auth::can('dashboard.view_settings'),
        ];
    }
}
