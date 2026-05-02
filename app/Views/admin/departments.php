<?php

use App\Core\Csrf;

$departments = $departments ?? [];
$users = $users ?? [];
$editingDepartment = $editingDepartment ?? null;
$isEditing = is_array($editingDepartment);
?>
<section class="admin-layout">
  <form class="panel-card" method="post" action="<?= e(route('/admin/departments')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= e($editingDepartment['id'] ?? '') ?>">
    <p class="eyebrow">Tanım</p>
    <h1><?= $isEditing ? 'Departman Düzenle' : 'Departman Ekle' ?></h1>
    <?php if ($isEditing): ?>
      <p class="muted">Seçili kayıt: <?= e($editingDepartment['name']) ?></p>
    <?php endif; ?>

    <div class="split-fields">
      <label>
        Departman adı
        <input name="name" placeholder="Örn. Teknik Servis" value="<?= e($editingDepartment['name'] ?? '') ?>" required>
      </label>
      <label>
        Kod
        <input name="code" placeholder="TEKNIK" value="<?= e($editingDepartment['code'] ?? '') ?>">
      </label>
    </div>

    <label>
      Departman amiri
      <select name="manager_user_id">
        <option value="">Seçiniz</option>
        <?php foreach ($users as $user): ?>
          <option value="<?= e($user['id']) ?>" <?= (int) ($editingDepartment['manager_user_id'] ?? 0) === (int) $user['id'] ? 'selected' : '' ?>><?= e($user['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <div class="split-fields">
      <label>
        E-posta
        <input name="email" type="email" placeholder="departman@otel.com" value="<?= e($editingDepartment['email'] ?? '') ?>">
      </label>
      <label>
        Telefon
        <input name="phone" placeholder="0xxx xxx xx xx" value="<?= e($editingDepartment['phone'] ?? '') ?>">
      </label>
    </div>

    <label>
      Durum
      <select name="status">
        <option value="active" <?= ($editingDepartment['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option>
        <option value="passive" <?= ($editingDepartment['status'] ?? '') === 'passive' ? 'selected' : '' ?>>Pasif</option>
      </select>
    </label>

    <div class="form-actions">
      <button class="primary-action" type="submit"><?= $isEditing ? 'Departman Güncelle' : 'Departman Kaydet' ?></button>
      <?php if ($isEditing): ?>
        <a class="ghost-link" href="<?= e(route('/admin/departments')) ?>">Yeni Kayıt</a>
      <?php endif; ?>
    </div>
  </form>

  <section class="panel-card wide">
    <div class="section-head">
      <div>
        <p class="eyebrow">Liste</p>
        <h1>Departmanlar</h1>
      </div>
    </div>

    <div class="admin-table">
      <div class="admin-head six">
        <span>Departman</span>
        <span>Kod</span>
        <span>Amir</span>
        <span>İletişim</span>
        <span>Durum</span>
        <span>İşlem</span>
      </div>

      <?php foreach ($departments as $department): ?>
        <article class="admin-row six">
          <span><?= e($department['name']) ?></span>
          <span><?= e($department['code']) ?></span>
          <span><?= e($department['manager_name'] ?? '-') ?></span>
          <span><?= e(trim(($department['email'] ?? '') . ' ' . ($department['phone'] ? '| ' . $department['phone'] : '')) ?: '-') ?></span>
          <span class="state-chip <?= $department['status'] === 'active' ? 'ok' : 'muted' ?>"><?= e($department['status']) ?></span>
          <span class="row-actions">
            <a class="dark-button small-action" href="<?= e(route('/admin/departments', ['edit_id' => $department['id']])) ?>">Düzenle</a>
            <form method="post" action="<?= e(route('/admin/departments')) ?>" onsubmit="return confirm('Bu departmanı silmek istiyor musunuz?');">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= e($department['id']) ?>">
              <button class="danger-button small-action" type="submit">Sil</button>
            </form>
          </span>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</section>
