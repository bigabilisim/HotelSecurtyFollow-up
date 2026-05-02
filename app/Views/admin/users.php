<?php

use App\Core\Csrf;

$users = $users ?? [];
$roles = $roles ?? [];
$departments = $departments ?? [];
$panelPermissions = $panelPermissions ?? [];
$editingUser = $editingUser ?? null;
$currentUserId = (int) ($currentUserId ?? 0);
$isEditing = is_array($editingUser);
$selectedRoleIds = array_map('intval', $editingUser['role_ids'] ?? []);
$selectedPanelPermissions = array_filter(
    $editingUser['panel_permissions'] ?? [],
    static fn ($isAllowed): bool => (bool) $isAllowed
);
?>
<section class="admin-layout">
  <div class="setup-stack">
    <form class="panel-card" method="post" action="<?= e(route('/admin/users')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="<?= e($editingUser['id'] ?? '') ?>">
      <p class="eyebrow">Yetki</p>
      <h1><?= $isEditing ? 'Kullanıcı Düzenle' : 'Kullanıcı Ekle' ?></h1>
      <?php if ($isEditing): ?>
        <p class="muted">Seçili kayıt: <?= e($editingUser['full_name']) ?></p>
      <?php endif; ?>

      <div class="split-fields">
        <label>
          Ad soyad
          <input name="full_name" value="<?= e($editingUser['full_name'] ?? '') ?>" required>
        </label>
        <label>
          Kullanıcı adı
          <input name="username" value="<?= e($editingUser['username'] ?? '') ?>" required>
        </label>
      </div>

      <div class="split-fields">
        <label>
          E-posta
          <input name="email" type="email" value="<?= e($editingUser['email'] ?? '') ?>">
        </label>
        <label>
          Telefon
          <input name="phone" value="<?= e($editingUser['phone'] ?? '') ?>">
        </label>
      </div>

      <div class="split-fields">
        <label>
          Departman
          <select name="department_id">
            <option value="">Seçiniz</option>
            <?php foreach ($departments as $department): ?>
              <option value="<?= e($department['id']) ?>" <?= (int) ($editingUser['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Durum
          <select name="status">
            <option value="active" <?= ($editingUser['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option>
            <option value="passive" <?= ($editingUser['status'] ?? '') === 'passive' ? 'selected' : '' ?>>Pasif</option>
            <option value="locked" <?= ($editingUser['status'] ?? '') === 'locked' ? 'selected' : '' ?>>Kilitli</option>
          </select>
        </label>
      </div>

      <label>
        Şifre <?= $isEditing ? '(boş bırakılırsa değişmez)' : '' ?>
        <input name="password" type="password" minlength="4" <?= $isEditing ? '' : 'required' ?>>
      </label>

      <fieldset class="check-list">
        <legend>Roller</legend>
        <?php foreach ($roles as $role): ?>
          <label class="switch-line">
            <input name="role_ids[]" type="checkbox" value="<?= e($role['id']) ?>" <?= in_array((int) $role['id'], $selectedRoleIds, true) ? 'checked' : '' ?>>
            <?= e($role['name']) ?>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <fieldset class="check-list">
        <legend>Yönetim ekranı panelleri</legend>
        <p class="muted">İşaretli paneller kullanıcıya açılır. İşareti kaldırılan panel menüde görünmez ve linkle girilirse yetkisiz ekranı açılır.</p>
        <?php foreach ($panelPermissions as $permission): ?>
          <label class="switch-line">
            <input
              name="panel_permissions[]"
              type="checkbox"
              value="<?= e($permission['code']) ?>"
              <?= !empty($selectedPanelPermissions[$permission['code']]) ? 'checked' : '' ?>
            >
            <span>
              <?= e($permission['label']) ?>
              <small><?= e($permission['description']) ?></small>
            </span>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <fieldset class="check-list">
        <legend>Telefon / Web Push bildirimi</legend>
        <p class="muted">İşaretli kullanıcı giriş yaptığında cihaz aboneliği alınır. Destekleyen cihazlarda panel kapalı olsa bile yeni giriş bildirimi gönderilir.</p>
        <label class="switch-line">
          <input name="mobile_notification_enabled" type="checkbox" <?= !empty($editingUser['mobile_notification_enabled']) ? 'checked' : '' ?>>
          Web Push bildirimini aktif et
        </label>
        <label class="switch-line">
          <input name="mobile_notification_entry_enabled" type="checkbox" <?= (!$editingUser || !empty($editingUser['mobile_notification_entry_enabled'])) ? 'checked' : '' ?>>
          İçeri giriş kayıtlarında bildirim gönder
        </label>
        <label class="switch-line">
          <input name="mobile_notification_exit_enabled" type="checkbox" <?= !empty($editingUser['mobile_notification_exit_enabled']) ? 'checked' : '' ?>>
          Çıkış kayıtlarında bildirim gönder
        </label>
      </fieldset>

      <fieldset class="check-list">
        <legend>Otomatik rapor gönderimi</legend>
        <p class="muted">İşaretli raporlar zamanı geldiğinde bu kullanıcının e-posta adresine otomatik gönderilir.</p>
        <label class="switch-line">
          <input name="report_daily_enabled" type="checkbox" <?= !empty($editingUser['report_daily_enabled']) ? 'checked' : '' ?>>
          Günlük Rapor Gönder
        </label>
        <label class="switch-line">
          <input name="report_weekly_enabled" type="checkbox" <?= !empty($editingUser['report_weekly_enabled']) ? 'checked' : '' ?>>
          Haftalık Rapor Gönder
        </label>
        <label class="switch-line">
          <input name="report_monthly_enabled" type="checkbox" <?= !empty($editingUser['report_monthly_enabled']) ? 'checked' : '' ?>>
          Aylık Rapor Gönder
        </label>
      </fieldset>

      <div class="form-actions">
        <button class="primary-action" type="submit"><?= $isEditing ? 'Kullanıcı Güncelle' : 'Kullanıcı Kaydet' ?></button>
        <?php if ($isEditing): ?>
          <a class="ghost-link" href="<?= e(route('/admin/users')) ?>">Yeni Kayıt</a>
        <?php endif; ?>
      </div>
    </form>

    <form class="panel-card" method="post" action="<?= e(route('/admin/users/import')) ?>" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <p class="eyebrow">Excel Import</p>
      <h1>Toplu Kullanıcı Aktar</h1>
      <p class="muted">CSV veya XLSX dosyası yükleyin. Yeni kullanıcıda şifre zorunlu; mevcut kullanıcıda boş bırakılırsa değişmez.</p>

      <label>
        Dosya
        <input name="user_import" type="file" accept=".csv,.xlsx,.txt" required>
      </label>

      <div class="form-actions">
        <button class="primary-action" type="submit">İçe Aktar</button>
        <a class="ghost-link" href="<?= e(route('/admin/users/import-template')) ?>">Örnek Dosya İndir</a>
      </div>
    </form>
  </div>

  <section class="panel-card wide">
    <div class="section-head">
      <div>
        <p class="eyebrow">Liste</p>
        <h1>Kullanıcılar</h1>
      </div>
    </div>

    <div class="admin-table">
      <div class="admin-head seven">
        <span>Kullanıcı</span>
        <span>Departman</span>
        <span>Roller</span>
        <span>Raporlar</span>
        <span>İletişim</span>
        <span>Durum</span>
        <span>İşlem</span>
      </div>

      <?php foreach ($users as $user): ?>
        <?php
          $reportFlags = [];
          if (!empty($user['report_daily_enabled'])) {
              $reportFlags[] = 'Günlük';
          }
          if (!empty($user['report_weekly_enabled'])) {
              $reportFlags[] = 'Haftalık';
          }
          if (!empty($user['report_monthly_enabled'])) {
              $reportFlags[] = 'Aylık';
          }
          if (!empty($user['mobile_notification_enabled'])) {
              $pushFlags = [];
              if (!empty($user['mobile_notification_entry_enabled'])) {
                  $pushFlags[] = 'giriş';
              }
              if (!empty($user['mobile_notification_exit_enabled'])) {
                  $pushFlags[] = 'çıkış';
              }
              $reportFlags[] = 'Web Push' . ($pushFlags ? ' (' . implode('/', $pushFlags) . ')' : '');
          }
        ?>
        <article class="admin-row seven">
          <span>
            <strong><?= e($user['full_name']) ?></strong>
            <small>@<?= e($user['username']) ?></small>
          </span>
          <span><?= e($user['department_name'] ?? '-') ?></span>
          <span><?= e($user['role_names'] ?? '-') ?></span>
          <span class="report-flag-list">
            <?php if ($reportFlags): ?>
              <?php foreach ($reportFlags as $flag): ?>
                <small><?= e($flag) ?></small>
              <?php endforeach; ?>
            <?php else: ?>
              <small class="muted">Kapalı</small>
            <?php endif; ?>
          </span>
          <span><?= e(trim(($user['email'] ?? '') . ' ' . ($user['phone'] ? '| ' . $user['phone'] : '')) ?: '-') ?></span>
          <span class="state-chip <?= $user['status'] === 'active' ? 'ok' : 'muted' ?>"><?= e($user['status']) ?></span>
          <span class="row-actions">
            <a class="dark-button small-action" href="<?= e(route('/admin/users', ['edit_id' => $user['id']])) ?>">Düzenle</a>
            <?php if ((int) $user['id'] !== $currentUserId): ?>
              <form method="post" action="<?= e(route('/admin/users')) ?>" onsubmit="return confirm('Bu kullanıcıyı silmek istiyor musunuz?');">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= e($user['id']) ?>">
                <button class="danger-button small-action" type="submit">Sil</button>
              </form>
            <?php else: ?>
              <small class="muted">Aktif kullanıcı</small>
            <?php endif; ?>
          </span>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</section>
