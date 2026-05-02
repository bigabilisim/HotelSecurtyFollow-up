<?php

use App\Core\Csrf;

$visitors = $visitors ?? [];
$editingVisitor = $editingVisitor ?? null;
$search = $search ?? '';
$isEditing = is_array($editingVisitor);
?>
<section class="admin-layout">
  <div class="setup-stack">
    <form class="panel-card" method="post" action="<?= e(route('/admin/visitors')) ?>">
      <?= Csrf::field() ?>
      <input type="hidden" name="id" value="<?= e($editingVisitor['id'] ?? '') ?>">
      <p class="eyebrow">Kişi Kartı</p>
      <h1><?= $isEditing ? 'Kişi Düzenle' : 'Kişi Ekle' ?></h1>
      <p class="muted">Girişlerde tekrar gelen kişilerin otomatik dolan temel bilgileri burada tutulur.</p>

      <label>
        Ad soyad
        <input name="full_name" value="<?= e($editingVisitor['full_name'] ?? '') ?>" required>
      </label>

      <div class="split-fields">
        <label>
          Telefon
          <input name="phone" value="<?= e($editingVisitor['phone'] ?? '') ?>">
        </label>
        <label>
          Plaka
          <input name="vehicle_plate" data-plate-format value="<?= e($editingVisitor['vehicle_plate'] ?? '') ?>" maxlength="11">
        </label>
      </div>

      <label>
        Firma / Kurum
        <input name="company" value="<?= e($editingVisitor['company'] ?? '') ?>">
      </label>

      <label>
        Not
        <textarea name="note" rows="4"><?= e($editingVisitor['note'] ?? '') ?></textarea>
      </label>

      <div class="form-actions">
        <button class="primary-action" type="submit"><?= $isEditing ? 'Değişiklikleri Kaydet' : 'Kişi Kaydet' ?></button>
        <?php if ($isEditing): ?>
          <a class="ghost-link" href="<?= e(route('/admin/visitors')) ?>">Yeni Kayıt</a>
        <?php endif; ?>
      </div>
    </form>

    <form class="panel-card" method="get" action="/index.php">
      <input type="hidden" name="route" value="/admin/visitors">
      <p class="eyebrow">Arama</p>
      <h1>Kişi Ara</h1>
      <label>
        Arama
        <input name="search" value="<?= e($search) ?>" placeholder="Ad, telefon, firma, plaka">
      </label>
      <div class="form-actions">
        <button class="primary-action" type="submit">Ara</button>
        <a class="ghost-link" href="<?= e(route('/admin/visitors')) ?>">Temizle</a>
      </div>
    </form>
  </div>

  <section class="panel-card wide">
    <div class="section-head">
      <div>
        <p class="eyebrow">Liste</p>
        <h1>Kayıtlı Kişiler</h1>
      </div>
    </div>

    <div class="admin-table">
      <div class="admin-head seven">
        <span>Kişi</span>
        <span>Firma</span>
        <span>Telefon</span>
        <span>Plaka</span>
        <span>Son Kategori</span>
        <span>Geliş</span>
        <span>İşlem</span>
      </div>

      <?php foreach ($visitors as $visitor): ?>
        <article class="admin-row seven">
          <span>
            <strong><?= e($visitor['full_name']) ?></strong>
            <small><?= e($visitor['note'] ?: '-') ?></small>
          </span>
          <span><?= e($visitor['company'] ?: '-') ?></span>
          <span><?= e($visitor['phone'] ?: '-') ?></span>
          <span><?= e($visitor['vehicle_plate'] ?: '-') ?></span>
          <span>
            <strong><?= e($visitor['last_category_name'] ?? '-') ?></strong>
            <small><?= e($visitor['last_department_name'] ?? '-') ?></small>
          </span>
          <span>
            <strong><?= e((int) ($visitor['visit_count'] ?? 0)) ?> kez</strong>
            <small><?= e($visitor['last_entry_at'] ?? '-') ?></small>
          </span>
          <span class="row-actions">
            <a class="dark-button small-action" href="<?= e(route('/admin/visitors', ['edit_id' => $visitor['id']])) ?>">Düzenle</a>
            <form method="post" action="<?= e(route('/admin/watchlist/quick-add')) ?>" onsubmit="return confirm('Bu kişiyi kara listeye almak istiyor musunuz?');">
              <?= Csrf::field() ?>
              <input type="hidden" name="return_route" value="/admin/visitors">
              <input type="hidden" name="search" value="<?= e($search) ?>">
              <input type="hidden" name="full_name" value="<?= e($visitor['full_name']) ?>">
              <button class="danger-button small-action" type="submit">Kara Listeye Al</button>
            </form>
            <form method="post" action="<?= e(route('/admin/visitors')) ?>" onsubmit="return confirm('Bu kişi kaydını silmek istiyor musunuz?');">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= e($visitor['id']) ?>">
              <button class="danger-button small-action" type="submit">Sil</button>
            </form>
          </span>
        </article>
      <?php endforeach; ?>

      <?php if (!$visitors): ?>
        <article class="empty-state">Kayıtlı kişi bulunamadı.</article>
      <?php endif; ?>
    </div>
  </section>
</section>
