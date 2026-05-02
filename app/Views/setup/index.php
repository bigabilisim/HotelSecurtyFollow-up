<?php

use App\Core\Csrf;

$hasUsers = $hasUsers ?? false;
$databaseError = $databaseError ?? null;
$dbDefaults = $dbDefaults ?? [];
?>
<section class="setup-grid">
  <article class="setup-copy">
    <p class="eyebrow">Canlı kurulum</p>
    <h1><?= $hasUsers ? 'Sistem Kurulu' : 'SQL ve Admin Bilgileri' ?></h1>
    <p>
      Canlıya geçiş için yalnızca MariaDB bağlantısını ve ilk admin hesabını girin.
      Sihirbaz tabloları kurar, başlangıç verilerini yükler ve sistemi açar.
    </p>
    <ul>
      <li>Veritabanı yoksa yetki varsa otomatik oluşturulur.</li>
      <li>`database/schema.sql` ve `database/seed.sql` otomatik çalıştırılır.</li>
      <li>İlk admin kullanıcısı oluşturulup panele yönlendirilir.</li>
    </ul>
  </article>

  <div class="setup-stack">
    <?php if ($hasUsers): ?>
      <section class="panel-card">
        <p class="eyebrow">Hazır</p>
        <h1>Kurulum Tamamlanmış</h1>
        <p class="muted">Bu sistemde aktif kullanıcı bulundu. Canlı paneli kullanmaya devam edebilirsiniz.</p>
        <div class="form-actions">
          <a class="primary-link" href="<?= e(route('/dashboard')) ?>">Panele Git</a>
          <a class="ghost-link" href="<?= e(route('/login')) ?>">Giriş Ekranı</a>
        </div>
      </section>
    <?php else: ?>
      <form class="panel-card" method="post" action="<?= e(route('/setup')) ?>">
        <?= Csrf::field() ?>

        <?php if ($databaseError): ?>
          <div class="flash error">Veritabanı bağlantısı henüz hazır değil. SQL bilgilerini girerek kurulumu tamamlayın.</div>
        <?php endif; ?>

        <div class="form-section">
          <h2>SQL Bilgileri</h2>
          <div class="split-fields">
            <label>
              SQL host
              <input name="db_host" value="<?= e($dbDefaults['host'] ?? '127.0.0.1') ?>" required>
            </label>
            <label>
              SQL port
              <input name="db_port" value="<?= e($dbDefaults['port'] ?? '3306') ?>" required>
            </label>
          </div>

          <label>
            Veritabanı adı
            <input name="db_database" value="<?= e($dbDefaults['database'] ?? 'hotel_security') ?>" required>
          </label>

          <div class="split-fields">
            <label>
              SQL kullanıcı adı
              <input name="db_username" value="<?= e($dbDefaults['username'] ?? 'root') ?>" required>
            </label>
            <label>
              SQL şifre
              <input name="db_password" type="password" autocomplete="new-password">
            </label>
          </div>
        </div>

        <div class="form-section">
          <h2>İlk Admin Girişi</h2>
          <div class="split-fields">
            <label>
              Ad soyad
              <input name="admin_name" value="Sistem Yöneticisi" required>
            </label>
            <label>
              Kullanıcı adı
              <input name="admin_username" value="admin" required>
            </label>
          </div>

          <label>
            Admin e-posta
            <input name="admin_email" type="email" placeholder="admin@otel.com">
          </label>

          <div class="split-fields">
            <label>
              Şifre
              <input name="admin_password" type="password" minlength="4" required>
            </label>
            <label>
              Şifre tekrar
              <input name="admin_password_repeat" type="password" minlength="4" required>
            </label>
          </div>
        </div>

        <button class="primary-action" type="submit">Sistemi Kur ve Aç</button>
      </form>
    <?php endif; ?>
  </div>
</section>
