<?php

use App\Core\Auth;
use App\Core\Csrf;

$filters = $filters ?? [];
$records = $records ?? [];
$categories = $categories ?? [];
$departments = $departments ?? [];
$statusLabels = $statusLabels ?? [];
$total = (int) ($total ?? 0);
$pdfParams = array_filter($filters, fn ($value): bool => $value !== '' && $value !== 0 && $value !== null);
$hasRecordFilters = (bool) $pdfParams;
$canQuickAddWatchlist = Auth::can('watchlist.manage');
$movementLabels = [
    'entry' => ['Giriş yaptı', 'ok'],
    'exit' => ['Çıkış yaptı', 'muted'],
];
?>
<section class="admin-layout records-page">
  <div class="setup-stack">
    <form
      class="panel-card section-filter-card"
      method="get"
      action="/index.php"
      data-section-filter="admin-records-filter"
      data-persist-filter-form="admin-records"
    >
      <input type="hidden" name="route" value="/admin/records">
      <div class="section-filter-card-head">
        <div>
          <p class="eyebrow">Kayıtlar</p>
          <h1>Kayıt Filtrele</h1>
          <p class="muted">Giriş çıkış kayıtlarını tarih, kategori, departman, durum ve arama metnine göre süzün.</p>
        </div>
        <button
          class="filter-menu-toggle <?= $hasRecordFilters ? 'has-active-filter' : '' ?>"
          type="button"
          data-section-filter-toggle
          aria-controls="admin-records-filter-panel"
          aria-expanded="false"
          title="Filtreleri göster"
        >
          <span class="filter-glyph" aria-hidden="true"><i></i></span>
          <span data-filter-label>Filtre</span>
        </button>
      </div>

      <div class="section-filter-panel embedded" id="admin-records-filter-panel" data-section-filter-panel hidden>
        <div class="split-fields">
          <label>
            Başlangıç
            <input name="date_from" type="date" value="<?= e($filters['date_from'] ?? '') ?>">
          </label>
          <label>
            Bitiş
            <input name="date_to" type="date" value="<?= e($filters['date_to'] ?? '') ?>">
          </label>
        </div>

        <label>
          Kategori
          <select name="category_id">
            <option value="">Tümü</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= e($category['id']) ?>" <?= (int) ($filters['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>
          Departman
          <select name="department_id">
            <option value="">Tümü</option>
            <?php foreach ($departments as $department): ?>
              <option value="<?= e($department['id']) ?>" <?= (int) ($filters['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>
          Durum
          <select name="status">
            <?php foreach ($statusLabels as $status => $label): ?>
              <option value="<?= e($status) ?>" <?= (string) ($filters['status'] ?? '') === (string) $status ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>
          Arama
          <input name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Ad, telefon, firma, plaka, not">
        </label>

        <div class="form-actions">
          <button class="primary-action" type="submit">Filtrele</button>
          <a class="ghost-link" href="<?= e(route('/admin/records')) ?>" data-clear-persisted-filter="admin-records">Temizle</a>
        </div>
      </div>
    </form>

    <form class="panel-card" method="post" action="<?= e(route('/admin/records/send-pdf')) ?>">
      <?= Csrf::field() ?>
      <?php foreach ($filters as $key => $value): ?>
        <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
      <?php endforeach; ?>
      <p class="eyebrow">PDF Gönder</p>
      <h1>Mail ile Paylaş</h1>
      <p class="muted">Mevcut filtrelerle PDF oluşturulur ve alıcıya ekli dosya olarak gönderim kuyruğuna alınır.</p>

      <label>
        Alıcı adı
        <input name="recipient_name" placeholder="Örn. Genel Müdür">
      </label>
      <label>
        Alıcı e-posta
        <input name="recipient_email" type="email" required placeholder="mail@otel.com">
      </label>
      <label>
        Konu
        <input name="subject" value="Otel Güvenlik Kayıtları PDF">
      </label>
      <label>
        Mesaj
        <textarea name="message" rows="4">Filtrelenen giriş çıkış kayıtları PDF olarak ekte gönderilmiştir.</textarea>
      </label>

      <div class="form-actions">
        <button class="primary-action" type="submit">PDF Mail Gönder</button>
        <a class="ghost-link" href="<?= e(route('/admin/records/pdf', $pdfParams)) ?>">PDF İndir</a>
      </div>
    </form>
  </div>

  <section class="panel-card wide">
    <div class="section-head">
      <div>
        <p class="eyebrow">Liste</p>
        <h1>Giriş Çıkış Kayıtları</h1>
        <p class="muted"><?= e($total) ?> kayıt bulundu. Ekranda en fazla 250 kayıt gösterilir; PDF en fazla 1000 kayıt içerir.</p>
      </div>
      <a class="primary-link" href="<?= e(route('/admin/records/pdf', $pdfParams)) ?>">PDF İndir</a>
    </div>

    <div class="records-table">
      <div class="record-head">
        <span>Ziyaretçi</span>
        <span>Hareket</span>
        <span>Giriş / Çıkış</span>
        <span>Kategori</span>
        <span>Departman</span>
        <span>Plaka</span>
        <span>Not</span>
        <span>İşlem</span>
      </div>

      <?php foreach ($records as $record): ?>
        <?php
          $movement = $movementLabels[$record['movement_type'] ?? 'entry'] ?? ['Hareket', 'muted'];
          $movementAt = strtotime((string) ($record['movement_at'] ?? ''));
          $movementSmall = ($record['movement_type'] ?? 'entry') === 'exit'
              ? 'Çıkış saati · ' . (int) ($record['elapsed_minutes'] ?? 0) . ' dk içeride'
              : 'Giriş saati';
        ?>
        <article class="record-row">
          <span>
            <strong><?= e($record['full_name']) ?></strong>
            <small><?= e(trim(($record['phone'] ?? '') . ' ' . ($record['company'] ? '| ' . $record['company'] : '')) ?: '-') ?></small>
          </span>
          <span>
            <span class="state-chip <?= e($movement[1]) ?>"><?= e($movement[0]) ?></span>
            <small><?= e($statusLabels[$record['status']] ?? $record['status']) ?></small>
          </span>
          <span>
            <strong><?= e($movementAt ? date('d.m.Y H:i', $movementAt) : '-') ?></strong>
            <small>
              <?= e($movementSmall) ?> ·
              <?= ($record['appointment_status'] ?? 'walk_in') === 'appointment' ? 'Randevulu' : 'Randevusuz' ?>
            </small>
          </span>
          <span><?= e($record['category_name']) ?></span>
          <span><?= e($record['department_name'] ?? '-') ?></span>
          <span><?= e($record['vehicle_plate'] ?: '-') ?></span>
          <span><small><?= e(trim((string) (($record['movement_note'] ?? '') ?: ($record['entry_note'] ?? '') ?: ($record['purpose'] ?? '') ?: ($record['exit_note'] ?? ''))) ?: '-') ?></small></span>
          <span class="row-actions">
            <?php if ($canQuickAddWatchlist): ?>
              <form method="post" action="<?= e(route('/admin/watchlist/quick-add')) ?>" onsubmit="return confirm('Bu kişiyi kara listeye almak istiyor musunuz?');">
                <?= Csrf::field() ?>
                <input type="hidden" name="return_route" value="/admin/records">
                <?php foreach ($filters as $key => $value): ?>
                  <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="full_name" value="<?= e($record['full_name']) ?>">
                <button class="danger-button small-action" type="submit">Kara Listeye Al</button>
              </form>
            <?php else: ?>
              <small class="muted">Yetki yok</small>
            <?php endif; ?>
          </span>
        </article>
      <?php endforeach; ?>

      <?php if (!$records): ?>
        <div class="empty-state">Bu filtrelerle kayıt bulunamadı.</div>
      <?php endif; ?>
    </div>
  </section>
</section>
