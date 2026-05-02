<?php

use App\Core\Csrf;

$versionInfo = $versionInfo ?? [];
$currentVersion = (string) ($versionInfo['current'] ?? 'V1.1');
$versionEntries = is_array($versionInfo['entries'] ?? null) ? $versionInfo['entries'] : [];
$recentVersions = array_slice($versionEntries, 0, 5);
$versionReleaseLabel = static function (array $entry): string {
    $publishedRaw = (string) ($entry['published_at'] ?? '');
    $publishedTimestamp = $publishedRaw !== '' ? strtotime($publishedRaw) : false;
    $publishedDate = $publishedTimestamp ? date('d.m.Y', $publishedTimestamp) : $publishedRaw;

    return $publishedDate !== '' ? 'Yayın: ' . $publishedDate : 'Test';
};
?>
<section class="auth-wrap">
  <form class="auth-card" method="post" action="<?= e(route('/login')) ?>">
    <?= Csrf::field() ?>
    <p class="eyebrow">Oturum</p>
    <h1>Güvenlik Paneli Girişi</h1>
    <p class="muted">Kullanıcı adı ve şifrenizle devam edin.</p>

    <label>
      Kullanıcı adı
      <input name="username" autocomplete="username" required autofocus>
    </label>

    <label>
      Şifre
      <input name="password" type="password" autocomplete="current-password" required>
    </label>

    <button class="primary-action" type="submit">Giriş Yap</button>
    <div class="auth-link-row">
      <a class="dark-button auth-guide-button" href="<?= e(route('/password/forgot')) ?>">
        <svg class="fish-icon" aria-hidden="true" viewBox="0 0 24 24" focusable="false">
          <path d="M3.5 12s3.2-5 8.3-5c3.1 0 5.2 1.8 7 5-1.8 3.2-3.9 5-7 5-5.1 0-8.3-5-8.3-5Z"></path>
          <path d="M18.8 12 22 8.8v6.4L18.8 12Z"></path>
          <path d="M10.8 8.2c1.1 1.1 1.1 6.5 0 7.6"></path>
          <circle cx="8.1" cy="10.6" r="0.6"></circle>
        </svg>
        <span>Şifremi Unuttum</span>
      </a>
      <a class="primary-link auth-guide-button" href="<?= e(route('/guide')) ?>">Kullanım Kılavuzu</a>
    </div>

    <?php if ($recentVersions): ?>
      <aside class="version-card" aria-label="Sürüm yenilikleri">
        <div class="version-card-head">
          <span>Son Sürümler</span>
          <strong>Güncel sürüm: <?= e($currentVersion) ?></strong>
        </div>

        <div class="version-list">
          <?php foreach ($recentVersions as $entry): ?>
            <article class="version-list-item">
              <div>
                <span><?= e($entry['version'] ?? 'Sürüm') ?> · <?= e($versionReleaseLabel($entry)) ?></span>
                <strong><?= e($entry['title'] ?? 'Sürüm Yenilikleri') ?></strong>
              </div>
              <p><?= e($entry['summary'] ?? '') ?></p>
              <?php if (!empty($entry['items']) && is_array($entry['items'])): ?>
                <details>
                  <summary>Detay</summary>
                  <ul>
                    <?php foreach (array_slice($entry['items'], 0, 5) as $item): ?>
                      <li><?= e($item) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </details>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>

        <a class="ghost-link version-history-link" href="<?= e(route('/versions')) ?>">Tüm sürüm geçmişini göster</a>
      </aside>
    <?php endif; ?>
  </form>
</section>
