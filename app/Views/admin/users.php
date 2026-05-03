<?php

use App\Core\Csrf;

$users = $users ?? [];
$roles = $roles ?? [];
$departments = $departments ?? [];
$panelPermissions = $panelPermissions ?? [];
$permissionPresets = $permissionPresets ?? [];
$editingUser = $editingUser ?? null;
$currentUserId = (int) ($currentUserId ?? 0);
$isEditing = is_array($editingUser);
$selectedRoleIds = array_map('intval', $editingUser['role_ids'] ?? []);
$defaultPermissionCodes = [
    'dashboard.view',
    'dashboard.block.door',
    'dashboard.block.inside',
    'dashboard.block.activity',
    'dashboard.block.stats',
    'dashboard.view_settings',
    'visits.create_entry',
    'visits.create_exit',
    'visits.view_all',
];
$selectedPanelPermissions = array_filter(
    $editingUser['panel_permissions'] ?? [],
    static fn ($isAllowed): bool => (bool) $isAllowed
);
$selectedPanelPermissionCodes = $isEditing ? array_keys($selectedPanelPermissions) : $defaultPermissionCodes;
$groupedPanelPermissions = [];
foreach ($panelPermissions as $permission) {
    $groupedPanelPermissions[$permission['group'] ?? 'Diğer'][] = $permission;
}
$permissionGroupDescriptions = [
    'Canlı Panel Bölümleri' => 'Kullanıcının canlı ekranda hangi panelleri göreceğini belirler.',
    'Operasyon Akışı' => 'Giriş, çıkış, kayıt görüntüleme ve amir onayı gibi günlük görevler.',
    'Tanımlar ve Yönetim' => 'Kategori, departman, kullanıcı, kayıtlı kişi ve kara liste yönetimi.',
    'Bildirim, Rapor ve Sistem' => 'Mail, Telegram, WhatsApp, rapor, yedek ve sistem ayarları.',
];
$permissionGroupOrder = ['Canlı Panel Bölümleri', 'Operasyon Akışı', 'Tanımlar ve Yönetim', 'Bildirim, Rapor ve Sistem'];
uksort($groupedPanelPermissions, static function (string $left, string $right) use ($permissionGroupOrder): int {
    $leftIndex = array_search($left, $permissionGroupOrder, true);
    $rightIndex = array_search($right, $permissionGroupOrder, true);
    $leftIndex = $leftIndex === false ? 99 : $leftIndex;
    $rightIndex = $rightIndex === false ? 99 : $rightIndex;

    return $leftIndex <=> $rightIndex ?: strcasecmp($left, $right);
});
$selectedPermissionCount = count($selectedPanelPermissionCodes);
$totalPermissionCount = count($panelPermissions);
$selectedUserId = $isEditing ? (int) ($editingUser['id'] ?? 0) : 0;
$selectedPickerUser = null;
foreach ($users as $pickerUser) {
    if ((int) ($pickerUser['id'] ?? 0) === $selectedUserId) {
        $selectedPickerUser = $pickerUser;
        break;
    }
}
$selectedPickerUser = $selectedPickerUser ?: ($isEditing ? $editingUser : null);
$buildUserFeatureFlags = static function (array $user): array {
    $flags = [];
    if (!empty($user['report_daily_enabled'])) {
        $flags[] = 'Günlük rapor';
    }
    if (!empty($user['report_weekly_enabled'])) {
        $flags[] = 'Haftalık rapor';
    }
    if (!empty($user['report_monthly_enabled'])) {
        $flags[] = 'Aylık rapor';
    }
    if (!empty($user['mobile_notification_enabled'])) {
        $pushFlags = [];
        if (!empty($user['mobile_notification_entry_enabled'])) {
            $pushFlags[] = 'giriş';
        }
        if (!empty($user['mobile_notification_exit_enabled'])) {
            $pushFlags[] = 'çıkış';
        }
        if (!empty($user['mobile_notification_department_enabled'])) {
            $pushFlags[] = 'departman';
        }
        $flags[] = 'Web Push' . ($pushFlags ? ' (' . implode('/', $pushFlags) . ')' : '');
    }

    return $flags;
};
$selectedUserFeatureFlags = $selectedPickerUser ? $buildUserFeatureFlags($selectedPickerUser) : [];
?>
<section class="admin-layout users-admin-layout">
  <div class="setup-stack users-editor-stack">
    <form class="panel-card users-editor-form" method="post" action="<?= e(route('/admin/users')) ?>">
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

      <section class="permission-designer" data-permission-workspace>
        <div class="permission-designer-head">
          <div>
            <p class="eyebrow">Yetkilendirme</p>
            <h2>Panel Kullanımı ve Ekran Yetkileri</h2>
            <p class="muted">Önce hazır bir profil seçin, sonra kullanıcının göreceği panel bölümlerini ve işlem yetkilerini açıp kapatın.</p>
          </div>
          <strong class="permission-summary-pill" data-permission-summary><?= e($selectedPermissionCount) ?> / <?= e($totalPermissionCount) ?> açık</strong>
        </div>

        <?php if ($permissionPresets): ?>
          <div class="permission-preset-grid" aria-label="Hazır yetki profilleri">
            <?php foreach ($permissionPresets as $preset): ?>
              <button
                class="permission-preset"
                type="button"
                data-permission-preset="<?= e(implode(',', $preset['codes'])) ?>"
              >
                <span>Yetki Profili</span>
                <strong><?= e($preset['label']) ?></strong>
                <small><?= e($preset['description']) ?></small>
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="permission-role-card">
          <div>
            <strong>Rol etiketi</strong>
            <span>Rol kullanıcının görev adını gösterir. Asıl erişim aşağıdaki açık/kapalı yetkilerle belirlenir.</span>
          </div>
          <div class="role-chip-grid">
            <?php foreach ($roles as $role): ?>
              <label class="role-chip">
                <input name="role_ids[]" type="checkbox" value="<?= e($role['id']) ?>" <?= in_array((int) $role['id'], $selectedRoleIds, true) ? 'checked' : '' ?>>
                <span><?= e($role['name']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="permission-group-stack">
          <?php foreach ($groupedPanelPermissions as $groupName => $permissions): ?>
            <section class="permission-group-card <?= $groupName === 'Canlı Panel Bölümleri' ? 'featured' : '' ?>">
              <div class="permission-group-head">
                <div>
                  <strong><?= e($groupName) ?></strong>
                  <small><?= e($permissionGroupDescriptions[$groupName] ?? 'Bu bölümdeki yetkileri kullanıcı ihtiyacına göre açıp kapatın.') ?></small>
                </div>
                <button class="ghost-link small-action" type="button" data-permission-group-toggle>Hepsini Aç/Kapat</button>
              </div>
              <div class="permission-card-grid <?= $groupName === 'Canlı Panel Bölümleri' ? 'panel-permission-grid' : '' ?>">
                <?php foreach ($permissions as $permission): ?>
                  <?php $isChecked = in_array($permission['code'], $selectedPanelPermissionCodes, true); ?>
                  <label class="permission-card <?= $permission['code'] === 'visits.department_verify' ? 'important-permission' : '' ?> <?= $isChecked ? 'is-enabled' : '' ?>">
                    <input
                      name="panel_permissions[]"
                      type="checkbox"
                      value="<?= e($permission['code']) ?>"
                      data-permission-code="<?= e($permission['code']) ?>"
                      <?= $isChecked ? 'checked' : '' ?>
                    >
                    <span class="permission-card-toggle" aria-hidden="true"></span>
                    <span class="permission-card-copy">
                      <strong><?= e($permission['label']) ?></strong>
                      <small><?= e($permission['description']) ?></small>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            </section>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="permission-group-card user-option-card notification-option-card">
        <div class="permission-group-head">
          <div>
            <strong>Telefon / Web Push bildirimi</strong>
            <small>İşaretli kullanıcı giriş yaptığında cihaz aboneliği alınır. Destekleyen cihazlarda panel kapalı olsa bile bildirim gönderilir.</small>
          </div>
        </div>
        <div class="permission-card-grid user-option-grid">
          <label class="permission-card user-setting-card <?= !empty($editingUser['mobile_notification_enabled']) ? 'is-enabled' : '' ?>">
            <input name="mobile_notification_enabled" type="checkbox" <?= !empty($editingUser['mobile_notification_enabled']) ? 'checked' : '' ?>>
            <span class="permission-card-toggle" aria-hidden="true"></span>
            <span class="permission-card-copy">
              <strong>Web Push aktif</strong>
              <small>Bu kullanıcının telefon veya tarayıcı bildirim aboneliği alınır.</small>
            </span>
          </label>
          <label class="permission-card user-setting-card <?= (!$editingUser || !empty($editingUser['mobile_notification_entry_enabled'])) ? 'is-enabled' : '' ?>">
            <input name="mobile_notification_entry_enabled" type="checkbox" <?= (!$editingUser || !empty($editingUser['mobile_notification_entry_enabled'])) ? 'checked' : '' ?>>
            <span class="permission-card-toggle" aria-hidden="true"></span>
            <span class="permission-card-copy">
              <strong>Giriş bildirimi</strong>
              <small>Yeni içeri giriş kaydı oluştuğunda bildirim gönderilir.</small>
            </span>
          </label>
          <label class="permission-card user-setting-card <?= !empty($editingUser['mobile_notification_exit_enabled']) ? 'is-enabled' : '' ?>">
            <input name="mobile_notification_exit_enabled" type="checkbox" <?= !empty($editingUser['mobile_notification_exit_enabled']) ? 'checked' : '' ?>>
            <span class="permission-card-toggle" aria-hidden="true"></span>
            <span class="permission-card-copy">
              <strong>Çıkış bildirimi</strong>
              <small>Çıkış kaydı oluşturulduğunda kullanıcıya bildirim gider.</small>
            </span>
          </label>
          <label class="permission-card user-setting-card <?= (!$editingUser || !empty($editingUser['mobile_notification_department_enabled'])) ? 'is-enabled' : '' ?>">
            <input name="mobile_notification_department_enabled" type="checkbox" <?= (!$editingUser || !empty($editingUser['mobile_notification_department_enabled'])) ? 'checked' : '' ?>>
            <span class="permission-card-toggle" aria-hidden="true"></span>
            <span class="permission-card-copy">
              <strong>Departman onayı</strong>
              <small>Amir onay sorularında telefon/Web Push bildirimi gönderilir.</small>
            </span>
          </label>
        </div>
      </section>

      <section class="permission-group-card user-option-card report-option-card">
        <div class="permission-group-head">
          <div>
            <strong>Otomatik rapor gönderimi</strong>
            <small>İşaretli raporlar zamanı geldiğinde bu kullanıcının e-posta adresine otomatik gönderilir.</small>
          </div>
        </div>
        <div class="permission-card-grid user-option-grid">
          <label class="permission-card user-setting-card <?= !empty($editingUser['report_daily_enabled']) ? 'is-enabled' : '' ?>">
            <input name="report_daily_enabled" type="checkbox" <?= !empty($editingUser['report_daily_enabled']) ? 'checked' : '' ?>>
            <span class="permission-card-toggle" aria-hidden="true"></span>
            <span class="permission-card-copy">
              <strong>Günlük rapor</strong>
              <small>Gün sonu raporu otomatik e-posta olarak gönderilir.</small>
            </span>
          </label>
          <label class="permission-card user-setting-card <?= !empty($editingUser['report_weekly_enabled']) ? 'is-enabled' : '' ?>">
            <input name="report_weekly_enabled" type="checkbox" <?= !empty($editingUser['report_weekly_enabled']) ? 'checked' : '' ?>>
            <span class="permission-card-toggle" aria-hidden="true"></span>
            <span class="permission-card-copy">
              <strong>Haftalık rapor</strong>
              <small>Haftalık özet rapor otomatik gönderim listesine alınır.</small>
            </span>
          </label>
          <label class="permission-card user-setting-card <?= !empty($editingUser['report_monthly_enabled']) ? 'is-enabled' : '' ?>">
            <input name="report_monthly_enabled" type="checkbox" <?= !empty($editingUser['report_monthly_enabled']) ? 'checked' : '' ?>>
            <span class="permission-card-toggle" aria-hidden="true"></span>
            <span class="permission-card-copy">
              <strong>Aylık rapor</strong>
              <small>Ay sonu raporu otomatik e-posta olarak gönderilir.</small>
            </span>
          </label>
        </div>
      </section>

      <div class="form-actions">
        <button class="primary-action" type="submit"><?= $isEditing ? 'Kullanıcı Güncelle' : 'Kullanıcı Kaydet' ?></button>
        <?php if ($isEditing): ?>
          <a class="ghost-link" href="<?= e(route('/admin/users')) ?>">Yeni Kayıt</a>
        <?php endif; ?>
      </div>
    </form>

  </div>

  <section class="panel-card wide users-admin-list">
    <div class="section-head">
      <div>
        <h1>Kullanıcı Seç</h1>
        <p class="muted">Kullanıcıyı listeden seçin; bilgileri ve yetkileri sağ tarafta düzenleyin.</p>
      </div>
      <a class="ghost-link small-action" href="<?= e(route('/admin/users')) ?>">Yeni</a>
    </div>

    <form class="user-picker-form" method="get" action="/index.php">
      <input type="hidden" name="route" value="/admin/users">
      <label>
        Kullanıcı
        <select name="edit_id" onchange="this.form.submit()">
          <option value="">Yeni kullanıcı oluştur</option>
          <?php foreach ($users as $user): ?>
            <option value="<?= e($user['id']) ?>" <?= $selectedUserId === (int) $user['id'] ? 'selected' : '' ?>>
              <?= e($user['full_name']) ?> - @<?= e($user['username']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>

    <?php if ($selectedPickerUser): ?>
      <article class="user-selected-summary">
        <div class="user-selected-head">
          <span class="user-picker-avatar"><?= e(function_exists('mb_substr') ? mb_substr((string) $selectedPickerUser['full_name'], 0, 1, 'UTF-8') : substr((string) $selectedPickerUser['full_name'], 0, 1)) ?></span>
          <span class="user-picker-main">
            <strong><?= e($selectedPickerUser['full_name']) ?></strong>
            <small>@<?= e($selectedPickerUser['username']) ?> · <?= e($selectedPickerUser['department_name'] ?? 'Departman yok') ?></small>
            <small><?= e($selectedPickerUser['role_names'] ?? 'Rol yok') ?></small>
          </span>
          <span class="state-chip <?= ($selectedPickerUser['status'] ?? '') === 'active' ? 'ok' : 'muted' ?>"><?= e($selectedPickerUser['status'] ?? '-') ?></span>
        </div>

        <div class="user-picker-meta">
          <?php if ($selectedUserFeatureFlags): ?>
            <?php foreach ($selectedUserFeatureFlags as $flag): ?>
              <small><?= e($flag) ?></small>
            <?php endforeach; ?>
          <?php else: ?>
            <small class="muted">Rapor/Web Push kapalı</small>
          <?php endif; ?>
        </div>

        <div class="user-picker-actions">
          <small><?= e(trim(($selectedPickerUser['email'] ?? '') . ' ' . (!empty($selectedPickerUser['phone']) ? '| ' . $selectedPickerUser['phone'] : '')) ?: '-') ?></small>
          <?php if ((int) ($selectedPickerUser['id'] ?? 0) !== $currentUserId): ?>
            <form method="post" action="<?= e(route('/admin/users')) ?>" onsubmit="return confirm('Bu kullanıcıyı silmek istiyor musunuz?');">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= e($selectedPickerUser['id']) ?>">
              <button class="danger-button small-action" type="submit">Sil</button>
            </form>
          <?php else: ?>
            <small class="muted">Aktif kullanıcı</small>
          <?php endif; ?>
        </div>
      </article>
    <?php endif; ?>

    <form class="user-import-card" method="post" action="<?= e(route('/admin/users/import')) ?>" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <p class="eyebrow">Excel Import</p>
      <h2>Toplu Kullanıcı Aktar</h2>
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
  </section>
</section>
