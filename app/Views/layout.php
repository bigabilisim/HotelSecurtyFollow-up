<?php

use App\Core\Auth;
use App\Core\Csrf;
use App\Models\UserPreference;

$settings = $settings ?? [];
$appName = $settings['app.name'] ?? config('app.name');
$domain = $settings['app.domain'] ?? parse_url((string) config('app.url'), PHP_URL_HOST);
$logoText = $settings['app.logo_text'] ?? 'OG';
$appCssVersion = is_file(BASE_PATH . '/public/assets/app.css') ? (string) filemtime(BASE_PATH . '/public/assets/app.css') : '1';
$pwaJsVersion = is_file(BASE_PATH . '/public/assets/pwa.js') ? (string) filemtime(BASE_PATH . '/public/assets/pwa.js') : '1';
try {
    $user = Auth::user();
} catch (Throwable) {
    $user = null;
}
$currentRoute = (string) ($_GET['route'] ?? parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/dashboard'), PHP_URL_PATH));
$currentRoute = $currentRoute !== '' && str_starts_with($currentRoute, '/') ? rtrim($currentRoute, '/') : '/dashboard';
$currentRoute = $currentRoute === '' ? '/dashboard' : $currentRoute;
$canOpenAdmin = $user ? Auth::canAny([
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
]) : false;
$canOpenSetup = $user ? Auth::can('settings.manage') : false;
$securityAlertType = flash('security_alert_type');
$securityAlertTitle = flash('security_alert_title');
$securityAlertMessage = flash('security_alert_message');
$securityAlertMatch = flash('security_alert_match');
$securityAlertReason = flash('security_alert_reason');
$securityAlertAction = flash('security_alert_action');
$mobileNotificationsEnabled = $user && !empty($user['mobile_notification_enabled']);
$userPreferences = [];
if ($user) {
    try {
        $userPreferences = (new UserPreference())->all((int) $user['id']);
    } catch (Throwable) {
        $userPreferences = [];
    }
}
?>
<!doctype html>
<html lang="tr">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#21302b">
    <meta name="color-scheme" content="light">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="<?= e($appName) ?>">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= e(($title ?? 'Panel') . ' | ' . $appName) ?></title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="stylesheet" href="/assets/app.css?v=<?= e($appCssVersion) ?>">
    <script defer src="/assets/pwa.js?v=<?= e($pwaJsVersion) ?>"></script>
  </head>
  <body>
    <main
      class="app-shell<?= $user ? ' app-shell-authenticated' : ' app-shell-public' ?>"
      <?php if ($user): ?>
        data-csrf="<?= e(Csrf::token()) ?>"
        data-user-preferences-url="<?= e(route('/account/preferences')) ?>"
        data-user-preferences="<?= e(json_encode($userPreferences, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
      <?php endif; ?>
      <?php if ($mobileNotificationsEnabled): ?>
        data-mobile-notifications="1"
        data-mobile-notification-url="<?= e(route('/mobile-notifications/poll')) ?>"
        data-web-push-config-url="<?= e(route('/mobile-notifications/web-push-config')) ?>"
        data-web-push-subscribe-url="<?= e(route('/mobile-notifications/subscribe')) ?>"
        data-web-push-unsubscribe-url="<?= e(route('/mobile-notifications/unsubscribe')) ?>"
      <?php endif; ?>
    >
      <header class="topbar">
        <a class="brand" href="<?= e(route('/dashboard')) ?>">
            <span class="brand-logo"><?= e(substr($logoText, 0, 4)) ?></span>
          <span>
            <small><?= e($domain ?: 'security.hotel.local') ?></small>
            <strong><?= e($appName) ?></strong>
          </span>
        </a>

        <?php if ($user): ?>
          <nav class="topnav" id="main-navigation" data-mobile-nav aria-label="Ana menü">
            <a href="<?= e(route('/dashboard')) ?>">Canlı Panel</a>
            <?php if ($currentRoute === '/dashboard' && Auth::can('dashboard.view_settings')): ?>
              <button
                class="dashboard-view-toggle"
                type="button"
                data-dashboard-view-toggle
                aria-controls="dashboard-view-controls"
                aria-expanded="false"
              >Görünüm Ayarı</button>
            <?php endif; ?>
            <button type="button" data-pwa-install>Uygulamayı Kur</button>
            <button type="button" data-suggestion-open>Öneri Yap</button>
            <a href="<?= e(route('/account/password')) ?>">Şifre Değiştir</a>
            <a href="<?= e(route('/guide')) ?>">Kılavuz</a>
            <?php if ($canOpenAdmin): ?>
              <a href="<?= e(route('/admin')) ?>">Yönetim</a>
            <?php endif; ?>
            <?php if ($canOpenSetup): ?>
              <a href="<?= e(route('/setup')) ?>">Kurulum</a>
            <?php endif; ?>
            <form method="post" action="<?= e(route('/logout')) ?>">
              <?= Csrf::field() ?>
              <button type="submit">Çıkış</button>
            </form>
          </nav>
          <button
            class="mobile-menu-toggle"
            type="button"
            data-mobile-menu-toggle
            aria-controls="main-navigation"
            aria-expanded="true"
          >Menüyü Gizle</button>
        <?php endif; ?>
      </header>

      <?php if ($user): ?>
        <section class="suggestion-overlay" data-suggestion-modal hidden aria-hidden="true">
          <form class="suggestion-dialog" method="post" action="<?= e(route('/suggestions')) ?>" role="dialog" aria-modal="true" aria-labelledby="suggestion-title">
            <?= Csrf::field() ?>
            <input type="hidden" name="return_route" value="<?= e($currentRoute) ?>">
            <input type="hidden" name="page_route" value="<?= e($currentRoute) ?>">

            <div class="suggestion-head">
              <div>
                <p class="eyebrow">Kullanıcı önerisi</p>
                <h1 id="suggestion-title">Önerinizi Paylaşın</h1>
                <p class="muted">İş akışını kolaylaştıracak fikirleri, hata bildirimlerini veya eğitim ihtiyaçlarını buradan iletebilirsiniz.</p>
              </div>
              <button class="suggestion-close" type="button" data-suggestion-close aria-label="Pencereyi kapat">Kapat</button>
            </div>

            <div class="split-fields">
              <label>
                Tür
                <select name="suggestion_type">
                  <option value="improvement">İyileştirme önerisi</option>
                  <option value="bug">Hata bildirimi</option>
                  <option value="feature">Yeni özellik isteği</option>
                  <option value="support">Destek / eğitim ihtiyacı</option>
                </select>
              </label>
              <label>
                Öncelik
                <select name="priority">
                  <option value="normal">Normal</option>
                  <option value="high">Önemli</option>
                </select>
              </label>
            </div>

            <label>
              Kısa başlık
              <input name="title" maxlength="160" required placeholder="Örn. Çıkış ekranında ek bilgi görünsün">
            </label>

            <label>
              Açıklama
              <textarea name="message" rows="5" required placeholder="Ne olmasını istiyorsunuz? Hangi ekranda, hangi işlem sırasında ihtiyaç duyuldu?"></textarea>
            </label>

            <div class="suggestion-actions">
              <button class="primary-action" type="submit">Öneriyi Gönder</button>
              <button class="ghost-link" type="button" data-suggestion-close>Vazgeç</button>
            </div>
          </form>
        </section>
      <?php endif; ?>

      <?php if ($message = flash('success')): ?>
        <div class="flash success"><?= e($message) ?></div>
      <?php endif; ?>

      <?php if ($message = flash('error')): ?>
        <div class="flash error"><?= e($message) ?></div>
      <?php endif; ?>

      <?php if ($securityAlertType && $securityAlertMessage): ?>
        <section
          class="security-alert-overlay"
          data-security-alert
          role="dialog"
          aria-modal="true"
          aria-labelledby="security-alert-title"
        >
          <div class="security-alert-dialog <?= e($securityAlertType === 'blacklist' ? 'blacklist' : 'warning') ?>">
            <div class="security-alert-mark"><?= $securityAlertType === 'blacklist' ? '!' : 'i' ?></div>
            <div class="security-alert-content">
              <p class="eyebrow"><?= $securityAlertType === 'blacklist' ? 'Giriş engellendi' : 'Güvenlik uyarısı' ?></p>
              <h1 id="security-alert-title"><?= e($securityAlertTitle ?: 'Güvenlik Uyarısı') ?></h1>
              <p class="security-alert-message"><?= e($securityAlertMessage) ?></p>

              <dl>
                <?php if ($securityAlertMatch): ?>
                  <div>
                    <dt>Eşleşme</dt>
                    <dd><?= e($securityAlertMatch) ?></dd>
                  </div>
                <?php endif; ?>
                <?php if ($securityAlertReason): ?>
                  <div>
                    <dt>Sebep</dt>
                    <dd><?= e($securityAlertReason) ?></dd>
                  </div>
                <?php endif; ?>
                <?php if ($securityAlertAction): ?>
                  <div>
                    <dt>Aksiyon</dt>
                    <dd><?= e($securityAlertAction) ?></dd>
                  </div>
                <?php endif; ?>
              </dl>

              <button class="primary-action security-alert-close" type="button" data-security-alert-close>Okudum</button>
            </div>
          </div>
        </section>
      <?php endif; ?>

      <?= $content ?>
    </main>
  </body>
</html>
