<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\AccountController;
use App\Controllers\AdminController;
use App\Controllers\DashboardController;
use App\Controllers\DepartmentVerificationController;
use App\Controllers\MobileNotificationController;
use App\Controllers\PasswordController;
use App\Controllers\PwaController;
use App\Controllers\ReservationlessReviewController;
use App\Controllers\SetupController;
use App\Controllers\SuggestionController;
use App\Controllers\VersionController;
use App\Controllers\VisitController;
use App\Core\Auth;
use App\Models\User;

require dirname(__DIR__) . '/app/bootstrap.php';

$route = $_GET['route'] ?? parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$route = $route === '/index.php' ? '/' : rtrim($route, '/');
$route = $route === '' ? '/' : $route;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$routeMethod = $method === 'HEAD' ? 'GET' : $method;

try {
    if ($route === '/' && $method === 'GET') {
        try {
            $hasUsers = (new User())->hasAnyUsers();
        } catch (Throwable) {
            $hasUsers = false;
        }

        if (!$hasUsers) {
            redirect('/setup');
        }

        redirect('/dashboard');
    }

    $routes = [
        'GET' => [
            '/setup' => [SetupController::class, 'index'],
            '/manifest.webmanifest' => [PwaController::class, 'manifest'],
            '/login' => [AuthController::class, 'login'],
            '/versions' => [VersionController::class, 'history'],
            '/password/forgot' => [PasswordController::class, 'forgot'],
            '/password/reset' => [PasswordController::class, 'reset'],
            '/account/password' => [AccountController::class, 'password'],
            '/account/preferences' => [AccountController::class, 'preferences'],
            '/mobile-notifications/web-push-config' => [MobileNotificationController::class, 'webPushConfig'],
            '/reservationless/review' => [ReservationlessReviewController::class, 'show'],
            '/verifications/respond' => [DepartmentVerificationController::class, 'respond'],
            '/dashboard' => [DashboardController::class, 'index'],
            '/guide' => [DashboardController::class, 'guide'],
            '/admin' => [AdminController::class, 'index'],
            '/admin/guide' => [AdminController::class, 'guide'],
            '/admin/suggestions' => [AdminController::class, 'suggestions'],
            '/admin/ip-blocks' => [AdminController::class, 'ipBlocks'],
            '/admin/categories' => [AdminController::class, 'categories'],
            '/admin/departments' => [AdminController::class, 'departments'],
            '/admin/users' => [AdminController::class, 'users'],
            '/admin/users/import-template' => [AdminController::class, 'userImportTemplate'],
            '/admin/visitors' => [AdminController::class, 'visitors'],
            '/admin/watchlist' => [AdminController::class, 'watchlist'],
            '/admin/mail-templates' => [AdminController::class, 'mailTemplates'],
            '/admin/notification-channels' => [AdminController::class, 'notificationChannels'],
            '/admin/notification-rules' => [AdminController::class, 'notificationRules'],
            '/admin/records' => [AdminController::class, 'visitRecords'],
            '/admin/records/pdf' => [AdminController::class, 'downloadVisitRecordsPdf'],
            '/admin/reports' => [AdminController::class, 'reports'],
            '/admin/backups' => [AdminController::class, 'backups'],
            '/admin/backups/download' => [AdminController::class, 'downloadBackup'],
        ],
        'POST' => [
            '/setup' => [SetupController::class, 'store'],
            '/login' => [AuthController::class, 'authenticate'],
            '/password/forgot' => [PasswordController::class, 'sendResetLink'],
            '/password/reset' => [PasswordController::class, 'update'],
            '/account/password' => [AccountController::class, 'updatePassword'],
            '/account/preferences' => [AccountController::class, 'savePreferences'],
            '/reservationless/review' => [ReservationlessReviewController::class, 'submit'],
            '/logout' => [AuthController::class, 'logout'],
            '/suggestions' => [SuggestionController::class, 'store'],
            '/mobile-notifications/poll' => [MobileNotificationController::class, 'poll'],
            '/mobile-notifications/subscribe' => [MobileNotificationController::class, 'subscribe'],
            '/mobile-notifications/unsubscribe' => [MobileNotificationController::class, 'unsubscribe'],
            '/dashboard/heartbeat' => [DashboardController::class, 'heartbeat'],
            '/visits/entry' => [VisitController::class, 'entry'],
            '/visits/update' => [VisitController::class, 'update'],
            '/visits/exit' => [VisitController::class, 'exit'],
            '/verifications/answer' => [DepartmentVerificationController::class, 'answer'],
            '/admin/categories' => [AdminController::class, 'storeCategory'],
            '/admin/suggestions' => [AdminController::class, 'storeSuggestion'],
            '/admin/ip-blocks' => [AdminController::class, 'releaseIpBlock'],
            '/admin/escalation-settings' => [AdminController::class, 'storeEscalationSettings'],
            '/admin/security-settings' => [AdminController::class, 'storeSecuritySettings'],
            '/admin/departments' => [AdminController::class, 'storeDepartment'],
            '/admin/users' => [AdminController::class, 'storeUser'],
            '/admin/users/import' => [AdminController::class, 'importUsers'],
            '/admin/visitors' => [AdminController::class, 'storeVisitor'],
            '/admin/watchlist' => [AdminController::class, 'storeWatchlist'],
            '/admin/watchlist/quick-add' => [AdminController::class, 'quickAddWatchlist'],
            '/admin/mail-templates' => [AdminController::class, 'storeMailTemplates'],
            '/admin/notification-channels' => [AdminController::class, 'storeNotificationChannel'],
            '/admin/notification-rules' => [AdminController::class, 'storeNotificationRule'],
            '/admin/records/send-pdf' => [AdminController::class, 'sendVisitRecordsPdf'],
            '/admin/reports' => [AdminController::class, 'storeReport'],
            '/admin/backups' => [AdminController::class, 'storeBackup'],
        ],
    ];
    $adminAccessPermissions = [
        'categories.manage',
        'departments.manage',
        'users.manage',
        'visitors.manage',
        'watchlist.manage',
        'notifications.manage',
        'notification_rules.manage',
        'mail_templates.manage',
        'notification_channels.manage',
        'reports.view',
        'reports.manage',
        'backups.manage',
        'settings.manage',
        'suggestions.manage',
    ];
    $routePermissions = [
        'GET' => [
            '/dashboard' => 'dashboard.view',
            '/account/password' => 'dashboard.view',
            '/account/preferences' => 'dashboard.view',
            '/mobile-notifications/web-push-config' => 'dashboard.view',
            '/admin' => $adminAccessPermissions,
            '/admin/guide' => $adminAccessPermissions,
            '/admin/suggestions' => 'suggestions.manage',
            '/admin/ip-blocks' => 'settings.manage',
            '/admin/categories' => 'categories.manage',
            '/admin/departments' => 'departments.manage',
            '/admin/users' => 'users.manage',
            '/admin/users/import-template' => 'users.manage',
            '/admin/visitors' => 'visitors.manage',
            '/admin/watchlist' => 'watchlist.manage',
            '/admin/mail-templates' => 'mail_templates.manage',
            '/admin/notification-channels' => 'notification_channels.manage',
            '/admin/notification-rules' => 'notification_rules.manage',
            '/admin/records' => 'reports.view',
            '/admin/records/pdf' => 'reports.view',
            '/admin/reports' => 'reports.manage',
            '/admin/backups' => 'backups.manage',
            '/admin/backups/download' => 'backups.manage',
        ],
        'POST' => [
            '/dashboard/heartbeat' => 'dashboard.view',
            '/account/password' => 'dashboard.view',
            '/account/preferences' => 'dashboard.view',
            '/mobile-notifications/poll' => 'dashboard.view',
            '/mobile-notifications/subscribe' => 'dashboard.view',
            '/mobile-notifications/unsubscribe' => 'dashboard.view',
            '/visits/entry' => 'visits.create_entry',
            '/visits/update' => 'visits.create_entry',
            '/visits/exit' => 'visits.create_exit',
            '/verifications/answer' => 'visits.department_verify',
            '/admin/suggestions' => 'suggestions.manage',
            '/admin/ip-blocks' => 'settings.manage',
            '/admin/categories' => 'categories.manage',
            '/admin/escalation-settings' => 'settings.manage',
            '/admin/security-settings' => 'settings.manage',
            '/admin/departments' => 'departments.manage',
            '/admin/users' => 'users.manage',
            '/admin/users/import' => 'users.manage',
            '/admin/visitors' => 'visitors.manage',
            '/admin/watchlist' => 'watchlist.manage',
            '/admin/watchlist/quick-add' => 'watchlist.manage',
            '/admin/mail-templates' => 'mail_templates.manage',
            '/admin/notification-channels' => 'notification_channels.manage',
            '/admin/notification-rules' => 'notification_rules.manage',
            '/admin/records/send-pdf' => 'reports.view',
            '/admin/reports' => 'reports.manage',
            '/admin/backups' => 'backups.manage',
        ],
    ];

    if (!isset($routes[$routeMethod][$route])) {
        http_response_code(404);
        echo view('errors/404', ['title' => 'Sayfa Bulunamadı']);
        exit;
    }

    $requiredPermission = $routePermissions[$routeMethod][$route] ?? null;
    if (is_array($requiredPermission)) {
        Auth::requireAny($requiredPermission);
    } elseif (is_string($requiredPermission)) {
        Auth::requirePermission($requiredPermission);
    }

    [$controller, $action] = $routes[$routeMethod][$route];
    echo (new $controller())->$action();
} catch (Throwable $error) {
    http_response_code(500);

    $debug = (bool) config('app.debug', false);
    echo view('errors/500', [
        'title' => 'Sistem Hatası',
        'message' => $debug ? $error->getMessage() : 'Sistem şu anda isteği işleyemedi.',
    ]);
}
