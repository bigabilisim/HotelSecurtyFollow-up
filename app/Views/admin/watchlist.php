<?php

use App\Core\Csrf;

$entries = $entries ?? [];
$editingEntry = $editingEntry ?? null;
$isEditing = is_array($editingEntry);
$listTypes = ['warning' => 'Uyarı Listesi', 'blacklist' => 'Kara Liste'];
$matchTypes = ['name' => 'Ad soyad', 'phone' => 'Telefon', 'plate' => 'Plaka'];
?>
<section class="admin-layout">
  <form class="panel-card" method="post" action="<?= e(route('/admin/watchlist')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= e($editingEntry['id'] ?? '') ?>">
    <p class="eyebrow">Güvenlik</p>
    <h1><?= $isEditing ? 'Liste Kaydı Düzenle' : 'Liste Kaydı Ekle' ?></h1>
    <p class="muted">Kara liste giriş kaydını durdurur. Uyarı listesi kayda izin verir ama güvenliğe görünür uyarı verir.</p>

    <div class="split-fields">
      <label>
        Liste türü
        <select name="list_type">
          <?php foreach ($listTypes as $type => $label): ?>
            <option value="<?= e($type) ?>" <?= ($editingEntry['list_type'] ?? 'warning') === $type ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Eşleşme türü
        <select name="match_type">
          <?php foreach ($matchTypes as $type => $label): ?>
            <option value="<?= e($type) ?>" <?= ($editingEntry['match_type'] ?? 'name') === $type ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <label>
      Eşleşecek değer
      <input name="match_value" value="<?= e($editingEntry['match_value'] ?? '') ?>" placeholder="Ad, telefon veya plaka" required>
    </label>

    <label>
      Sebep
      <textarea name="reason" rows="3"><?= e($editingEntry['reason'] ?? '') ?></textarea>
    </label>

    <label>
      Güvenlik aksiyon notu
      <input name="action_note" value="<?= e($editingEntry['action_note'] ?? '') ?>" placeholder="Örn. Yöneticiye haber ver">
    </label>

    <label class="switch-line">
      <input name="is_active" type="checkbox" <?= (int) ($editingEntry['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
      Aktif
    </label>

    <div class="form-actions">
      <button class="primary-action" type="submit"><?= $isEditing ? 'Değişiklikleri Kaydet' : 'Liste Kaydet' ?></button>
      <?php if ($isEditing): ?>
        <a class="ghost-link" href="<?= e(route('/admin/watchlist')) ?>">Yeni Kayıt</a>
      <?php endif; ?>
    </div>
  </form>

  <section class="panel-card wide">
    <div class="section-head">
      <div>
        <p class="eyebrow">Liste</p>
        <h1>Kara / Uyarı Listesi</h1>
      </div>
    </div>

    <div class="admin-table">
      <div class="admin-head seven">
        <span>Tür</span>
        <span>Eşleşme</span>
        <span>Değer</span>
        <span>Sebep</span>
        <span>Aksiyon</span>
        <span>Durum</span>
        <span>İşlem</span>
      </div>

      <?php foreach ($entries as $entry): ?>
        <article class="admin-row seven">
          <span class="state-chip <?= $entry['list_type'] === 'blacklist' ? 'risk' : 'warning' ?>"><?= e($listTypes[$entry['list_type']] ?? $entry['list_type']) ?></span>
          <span><?= e($matchTypes[$entry['match_type']] ?? $entry['match_type']) ?></span>
          <span><strong><?= e($entry['match_value']) ?></strong></span>
          <span><small><?= e($entry['reason'] ?: '-') ?></small></span>
          <span><small><?= e($entry['action_note'] ?: '-') ?></small></span>
          <span class="state-chip <?= (int) $entry['is_active'] === 1 ? 'ok' : 'muted' ?>"><?= (int) $entry['is_active'] === 1 ? 'Aktif' : 'Pasif' ?></span>
          <span class="row-actions">
            <a class="dark-button small-action" href="<?= e(route('/admin/watchlist', ['edit_id' => $entry['id']])) ?>">Düzenle</a>
            <form method="post" action="<?= e(route('/admin/watchlist')) ?>" onsubmit="return confirm('Bu liste kaydını silmek istiyor musunuz?');">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= e($entry['id']) ?>">
              <button class="danger-button small-action" type="submit">Sil</button>
            </form>
          </span>
        </article>
      <?php endforeach; ?>

      <?php if (!$entries): ?>
        <article class="empty-state">Kara veya uyarı listesi kaydı yok.</article>
      <?php endif; ?>
    </div>
  </section>
</section>
