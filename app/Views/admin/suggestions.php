<?php

use App\Core\Csrf;

$suggestions = $suggestions ?? [];
$typeOptions = $typeOptions ?? [];
$priorityOptions = $priorityOptions ?? [];
$statusOptions = $statusOptions ?? [];
$filters = $filters ?? ['status' => '', 'type' => ''];
$statusClasses = [
    'new' => 'warning',
    'reviewing' => 'ok',
    'done' => 'muted',
    'rejected' => 'risk',
];
?>
<section class="admin-layout single">
  <form class="panel-card wide" method="get" action="/index.php">
    <input type="hidden" name="route" value="/admin/suggestions">
    <div class="section-head">
      <div>
        <p class="eyebrow">Geri bildirim</p>
        <h1>Kullanıcı Önerileri</h1>
        <p class="muted">Üst menüdeki Öneri Yap penceresinden gelen fikirleri burada takip edebilirsiniz.</p>
      </div>
      <a class="dark-button" href="<?= e(route('/admin')) ?>">Yönetime Dön</a>
    </div>

    <div class="split-fields">
      <label>
        Durum
        <select name="status">
          <option value="">Tümü</option>
          <?php foreach ($statusOptions as $code => $label): ?>
            <option value="<?= e($code) ?>" <?= ($filters['status'] ?? '') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Tür
        <select name="type">
          <option value="">Tümü</option>
          <?php foreach ($typeOptions as $code => $label): ?>
            <option value="<?= e($code) ?>" <?= ($filters['type'] ?? '') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <div class="form-actions">
      <button class="primary-action" type="submit">Filtrele</button>
      <a class="ghost-link" href="<?= e(route('/admin/suggestions')) ?>">Temizle</a>
    </div>
  </form>

  <section class="panel-card wide">
    <div class="section-head">
      <div>
        <p class="eyebrow">Liste</p>
        <h1>Gelen Öneriler</h1>
      </div>
    </div>

    <div class="admin-table suggestions-table">
      <div class="admin-head seven">
        <span>Öneri</span>
        <span>Tür</span>
        <span>Öncelik</span>
        <span>Kullanıcı</span>
        <span>Sayfa</span>
        <span>Durum</span>
        <span>İşlem</span>
      </div>

      <?php foreach ($suggestions as $suggestion): ?>
        <article class="admin-row seven">
          <span>
            <strong><?= e($suggestion['title']) ?></strong>
            <small><?= e($suggestion['message']) ?></small>
            <small><?= e(date('d.m.Y H:i', strtotime((string) $suggestion['created_at']))) ?></small>
          </span>
          <span><?= e($typeOptions[$suggestion['suggestion_type']] ?? $suggestion['suggestion_type']) ?></span>
          <span class="state-chip <?= $suggestion['priority'] === 'high' ? 'warning' : 'muted' ?>"><?= e($priorityOptions[$suggestion['priority']] ?? $suggestion['priority']) ?></span>
          <span>
            <strong><?= e($suggestion['user_name'] ?: '-') ?></strong>
            <small><?= e($suggestion['user_email'] ?: '-') ?></small>
          </span>
          <span><small><?= e($suggestion['page_route'] ?: '-') ?></small></span>
          <span class="state-chip <?= e($statusClasses[$suggestion['status']] ?? 'muted') ?>"><?= e($statusOptions[$suggestion['status']] ?? $suggestion['status']) ?></span>
          <span class="row-actions">
            <form method="post" action="<?= e(route('/admin/suggestions')) ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="status">
              <input type="hidden" name="id" value="<?= e($suggestion['id']) ?>">
              <select name="status" aria-label="Öneri durumu">
                <?php foreach ($statusOptions as $code => $label): ?>
                  <option value="<?= e($code) ?>" <?= $suggestion['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="primary-action small-action" type="submit">Kaydet</button>
            </form>
            <form method="post" action="<?= e(route('/admin/suggestions')) ?>" onsubmit="return confirm('Bu öneriyi silmek istiyor musunuz?');">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= e($suggestion['id']) ?>">
              <button class="danger-button small-action" type="submit">Sil</button>
            </form>
          </span>
        </article>
      <?php endforeach; ?>

      <?php if (!$suggestions): ?>
        <article class="empty-state">Henüz kullanıcı önerisi yok.</article>
      <?php endif; ?>
    </div>
  </section>
</section>
