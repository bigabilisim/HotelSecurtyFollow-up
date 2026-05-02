<?php

use App\Core\Csrf;

$insideVisits = $insideVisits ?? [];
$categories = $categories ?? [];
$departments = $departments ?? [];
$statusLabels = $statusLabels ?? [];
$maskName = $maskName ?? static fn (?string $value): string => trim((string) $value);
?>
<div class="visit-table">
  <div class="visit-head">
    <span>Kişi</span>
    <span>Departman</span>
    <span>Süre</span>
    <span>Durum</span>
    <span>İşlem</span>
  </div>

  <?php if (!$insideVisits): ?>
    <article class="empty-state">İçeride görünen ziyaretçi yok.</article>
  <?php endif; ?>

  <?php foreach ($insideVisits as $visit): ?>
    <?php
      $status = $statusLabels[$visit['status']] ?? ['Normal', 'ok'];
      $elapsed = (int) $visit['elapsed_minutes'];
      $limit = $visit['max_duration_minutes_snapshot'] ? (int) $visit['max_duration_minutes_snapshot'] : null;
      $remaining = $limit ? max(0, $limit - $elapsed) : null;
      $progress = $limit ? min(100, max(0, (int) round(($elapsed / max(1, $limit)) * 100))) : 0;
      $entryAt = strtotime((string) $visit['entry_at']);
    ?>
    <article
      class="visit-row js-duration <?= $limit ? 'countdown-active' : '' ?>"
      data-entry-at="<?= e($entryAt ? date(DATE_ATOM, $entryAt) : '') ?>"
      data-limit-minutes="<?= e($limit ?? '') ?>"
    >
      <div class="person-cell">
        <span class="category-badge" style="background: <?= e($visit['category_color']) ?>">
          <?= e(substr($visit['category_name'], 0, 3)) ?>
        </span>
        <div>
          <strong><?= e($maskName($visit['full_name'])) ?></strong>
          <small><?= e(trim(($visit['company'] ?? '') . ' ' . ($visit['vehicle_plate'] ? '| ' . $visit['vehicle_plate'] : ''))) ?></small>
        </div>
      </div>
      <span><?= e($visit['department_name'] ?? '-') ?></span>
      <span class="countdown-cell">
        <strong data-countdown-label>
          <?= $limit ? e((string) $remaining) . ' dk kaldı' : e($elapsed) . ' dk' ?>
        </strong>
        <small data-elapsed-label><?= e($elapsed) ?><?= $limit ? ' / ' . e($limit) : '' ?> dk içeride</small>
        <?php if ($limit): ?>
          <span class="countdown-track" aria-hidden="true">
            <i data-countdown-bar style="width: <?= e($progress) ?>%"></i>
          </span>
        <?php endif; ?>
      </span>
      <span class="state-chip <?= e($status[1]) ?>"><?= e($status[0]) ?></span>
      <span class="visit-actions">
        <button class="ghost-button small-action" type="button" data-detail-toggle aria-expanded="false">Detay</button>
        <button class="primary-action small-action" type="button" data-edit-toggle aria-expanded="false">Düzenle</button>
        <form method="post" action="<?= e(route('/visits/exit')) ?>" onsubmit="return confirm('Bu ziyaretçi için çıkış kaydı oluşturulsun mu?');">
          <?= Csrf::field() ?>
          <input type="hidden" name="visit_id" value="<?= e($visit['id']) ?>">
          <button class="dark-button small-action" type="submit">Çıkış</button>
        </form>
      </span>
      <div class="visit-detail-panel" hidden>
        <dl>
          <div>
            <dt>Ad Soyad</dt>
            <dd><?= e($visit['full_name']) ?></dd>
          </div>
          <div>
            <dt>Telefon</dt>
            <dd><?= e($visit['phone'] ?: '-') ?></dd>
          </div>
          <div>
            <dt>Görüşeceği kişi</dt>
            <dd><?= e($visit['host_name'] ?: '-') ?></dd>
          </div>
          <div>
            <dt>Randevu</dt>
            <dd><?= ($visit['appointment_status'] ?? 'walk_in') === 'appointment' ? 'Randevulu' : 'Randevusuz' ?></dd>
          </div>
          <div>
            <dt>Amaç</dt>
            <dd><?= e($visit['purpose'] ?: '-') ?></dd>
          </div>
          <div>
            <dt>Not</dt>
            <dd><?= e(($visit['entry_note'] ?: $visit['visitor_note']) ?: '-') ?></dd>
          </div>
        </dl>
        <form class="visit-edit-form" method="post" action="<?= e(route('/visits/update')) ?>">
          <?= Csrf::field() ?>
          <input type="hidden" name="visit_id" value="<?= e($visit['id']) ?>">

          <div class="section-head compact">
            <div>
              <p class="eyebrow">Kayıt Düzenleme</p>
              <h2>Giriş Bilgileri</h2>
            </div>
            <button class="primary-action small-action" type="submit">Kaydet</button>
          </div>

          <div class="split-fields">
            <label>
              Ad Soyad
              <input name="full_name" value="<?= e($visit['full_name']) ?>" required data-edit-first>
            </label>
            <label>
              Kategori
              <select name="category_id" required>
                <?php foreach ($categories as $category): ?>
                  <option value="<?= e($category['id']) ?>" <?= (int) $visit['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                    <?= e($category['name']) ?><?= $category['max_duration_minutes'] ? ' - ' . e($category['max_duration_minutes']) . ' dk' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <div class="split-fields">
            <label>
              Telefon
              <input name="phone" value="<?= e($visit['phone'] ?? '') ?>">
            </label>
            <label>
              Plaka
              <input name="vehicle_plate" value="<?= e($visit['vehicle_plate'] ?? '') ?>" data-plate-format maxlength="11">
            </label>
          </div>

          <div class="split-fields">
            <label>
              Firma / Kurum
              <input name="company" value="<?= e($visit['company'] ?? '') ?>">
            </label>
            <label>
              Geldiği Departman
              <select name="department_id">
                <option value="">Seçiniz</option>
                <?php foreach ($departments as $department): ?>
                  <option value="<?= e($department['id']) ?>" <?= (int) ($visit['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>>
                    <?= e($department['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
          </div>

          <div class="split-fields">
            <label>
              Görüşeceği kişi
              <input name="host_name" value="<?= e($visit['host_name'] ?? '') ?>">
            </label>
            <label class="switch-line edit-switch">
              <input name="has_appointment" type="checkbox" <?= ($visit['appointment_status'] ?? 'walk_in') === 'appointment' ? 'checked' : '' ?>>
              Randevulu giriş
            </label>
          </div>

          <label>
            Amaç / Not
            <textarea name="note" rows="3"><?= e(($visit['entry_note'] ?: $visit['visitor_note']) ?: '') ?></textarea>
          </label>
          <input type="hidden" name="purpose" value="<?= e($visit['purpose'] ?: 'Ziyaret') ?>">
        </form>
      </div>
    </article>
  <?php endforeach; ?>
</div>
