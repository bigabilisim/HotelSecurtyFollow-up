<?php

use App\Core\Csrf;

$categories = $categories ?? [];
$editingCategory = $editingCategory ?? null;
$isEditing = is_array($editingCategory);
$requiresApproval = $isEditing ? (bool) $editingCategory['requires_department_approval'] : true;
$notificationsEnabled = $isEditing ? (bool) $editingCategory['is_notification_enabled'] : true;
$quickAccess = $isEditing ? (bool) ($editingCategory['is_quick_access'] ?? false) : false;
$quickAccessOrder = $editingCategory['quick_access_order'] ?? '';
?>
<section class="admin-layout">
  <form class="panel-card" method="post" action="<?= e(route('/admin/categories')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= e($editingCategory['id'] ?? '') ?>">
    <p class="eyebrow">Tanım</p>
    <h1><?= $isEditing ? 'Kategori Düzenle' : 'Kategori Ekle' ?></h1>
    <?php if ($isEditing): ?>
      <p class="muted">Seçili kayıt: <?= e($editingCategory['name']) ?></p>
    <?php endif; ?>

    <div class="split-fields">
      <label>
        Kategori adı
        <input name="name" placeholder="Örn. VIP" value="<?= e($editingCategory['name'] ?? '') ?>" required>
      </label>
      <label>
        Kod
        <input name="code" placeholder="VIP" value="<?= e($editingCategory['code'] ?? '') ?>">
      </label>
    </div>

    <div class="split-fields">
      <label>
        Renk
        <input name="color" type="color" value="<?= e($editingCategory['color'] ?? '#0f766e') ?>">
      </label>
      <label>
        Durum
        <select name="status">
          <option value="active" <?= ($editingCategory['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option>
          <option value="passive" <?= ($editingCategory['status'] ?? '') === 'passive' ? 'selected' : '' ?>>Pasif</option>
        </select>
      </label>
    </div>

    <div class="split-fields">
      <label>
        Maksimum süre (dk)
        <input name="max_duration_minutes" type="number" min="0" placeholder="Boş: süre yok" value="<?= e($editingCategory['max_duration_minutes'] ?? '') ?>">
      </label>
      <label>
        Uyarı zamanı (dk)
        <input name="warning_before_minutes" type="number" min="0" value="<?= e($editingCategory['warning_before_minutes'] ?? 10) ?>">
      </label>
    </div>

    <label class="switch-line">
      <input name="requires_department_approval" type="checkbox" <?= $requiresApproval ? 'checked' : '' ?>>
      Süre aşımında departman amirine sor
    </label>

    <label class="switch-line">
      <input name="is_notification_enabled" type="checkbox" <?= $notificationsEnabled ? 'checked' : '' ?>>
      Bu kategori için bildirim kuralları çalışsın
    </label>

    <div class="split-fields">
      <label class="switch-line">
        <input name="is_quick_access" type="checkbox" <?= $quickAccess ? 'checked' : '' ?>>
        Hızlı seçim kutusunda göster
      </label>
      <label>
        Hızlı seçim sırası
        <select name="quick_access_order">
          <option value="">Seçiniz</option>
          <?php for ($order = 1; $order <= 4; $order++): ?>
            <option value="<?= e($order) ?>" <?= (int) $quickAccessOrder === $order ? 'selected' : '' ?>><?= e($order) ?>. kutu</option>
          <?php endfor; ?>
        </select>
      </label>
    </div>

    <p class="muted">N+ eskalasyon zinciri kategoriye bağlı değildir; Yönetim ekranındaki global zincir tüm kategoriler için geçerlidir.</p>

    <div class="form-actions">
      <button class="primary-action" type="submit"><?= $isEditing ? 'Kategori Güncelle' : 'Kategori Kaydet' ?></button>
      <?php if ($isEditing): ?>
        <a class="ghost-link" href="<?= e(route('/admin/categories')) ?>">Yeni Kayıt</a>
      <?php endif; ?>
    </div>
  </form>

  <section class="panel-card wide">
    <div class="section-head">
      <div>
        <p class="eyebrow">Liste</p>
        <h1>Kategoriler</h1>
      </div>
    </div>

    <div class="admin-table">
      <div class="admin-head seven">
        <span>Kategori</span>
        <span>Kod</span>
        <span>Süre</span>
        <span>Amir Sorusu</span>
        <span>Hızlı</span>
        <span>Durum</span>
        <span>İşlem</span>
      </div>

      <?php foreach ($categories as $category): ?>
        <article class="admin-row seven">
          <span>
            <i class="color-dot" style="background: <?= e($category['color']) ?>"></i>
            <?= e($category['name']) ?>
          </span>
          <span><?= e($category['code']) ?></span>
          <span><?= $category['max_duration_minutes'] ? e($category['max_duration_minutes']) . ' dk' : 'Süre yok' ?></span>
          <span><?= $category['requires_department_approval'] ? 'Evet' : 'Hayır' ?></span>
          <span><?= !empty($category['is_quick_access']) ? e((string) ($category['quick_access_order'] ?: '-')) . '. kutu' : '-' ?></span>
          <span class="state-chip <?= $category['status'] === 'active' ? 'ok' : 'muted' ?>"><?= e($category['status']) ?></span>
          <span class="row-actions">
            <a class="dark-button small-action" href="<?= e(route('/admin/categories', ['edit_id' => $category['id']])) ?>">Düzenle</a>
            <form method="post" action="<?= e(route('/admin/categories')) ?>" onsubmit="return confirm('Bu kategoriyi silmek istiyor musunuz?');">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= e($category['id']) ?>">
              <button class="danger-button small-action" type="submit">Sil</button>
            </form>
          </span>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</section>
