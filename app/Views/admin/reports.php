<?php

use App\Core\Csrf;

$schedules = $schedules ?? [];
$logs = $logs ?? [];
$editingSchedule = $editingSchedule ?? null;
$isEditing = is_array($editingSchedule);
$typeLabels = ['daily' => 'Günlük', 'weekly' => 'Haftalık', 'monthly' => 'Aylık'];
$formatLabels = ['html' => 'HTML', 'pdf' => 'PDF', 'xlsx' => 'Excel', 'csv' => 'CSV'];
$logStatusLabels = ['created' => 'Oluşturuldu', 'sent' => 'Kuyruğa alındı', 'failed' => 'Hata'];
?>
<section class="admin-layout">
  <form class="panel-card" method="post" action="<?= e(route('/admin/reports')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="schedule_id" value="<?= e($editingSchedule['id'] ?? '') ?>">
    <p class="eyebrow">Rapor Planı</p>
    <h1><?= $isEditing ? 'Rapor Düzenle' : 'Rapor Ekle' ?></h1>
    <p class="muted">Raporun adını, türünü, çalışma saatini ve aktif durumunu buradan yönetin.</p>

    <label>
      Rapor adı
      <input name="name" value="<?= e($editingSchedule['name'] ?? '') ?>" required>
    </label>

    <div class="split-fields">
      <label>
        Tür
        <select name="report_type">
          <?php foreach ($typeLabels as $type => $label): ?>
            <option value="<?= e($type) ?>" <?= ($editingSchedule['report_type'] ?? 'daily') === $type ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Saat
        <input name="run_time" type="time" value="<?= e(substr((string) ($editingSchedule['run_time'] ?? '23:30:00'), 0, 5)) ?>">
      </label>
    </div>

    <div class="split-fields">
      <label>
        Ay günü
        <input name="day_of_month" type="number" min="1" max="28" value="<?= e($editingSchedule['day_of_month'] ?? '') ?>" placeholder="Aylık için">
      </label>
      <label>
        Format
        <select name="output_format">
          <?php foreach ($formatLabels as $format => $label): ?>
            <option value="<?= e($format) ?>" <?= ($editingSchedule['output_format'] ?? 'html') === $format ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <label class="switch-line">
      <input name="is_active" type="checkbox" <?= (int) ($editingSchedule['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
      Aktif
    </label>

    <div class="form-actions">
      <button class="primary-action" type="submit"><?= $isEditing ? 'Değişiklikleri Kaydet' : 'Rapor Kaydet' ?></button>
      <?php if ($isEditing): ?>
        <a class="ghost-link" href="<?= e(route('/admin/reports')) ?>">Yeni Kayıt</a>
      <?php endif; ?>
    </div>
  </form>

  <section class="panel-card wide">
    <div class="section-head">
      <div>
        <p class="eyebrow">Planlar</p>
        <h1>Günlük / Haftalık / Aylık</h1>
      </div>
    </div>

    <div class="admin-table">
      <div class="admin-head seven">
        <span>Plan</span>
        <span>Tür</span>
        <span>Saat</span>
        <span>Ay Günü</span>
        <span>Sonraki</span>
        <span>Durum</span>
        <span>İşlem</span>
      </div>
      <?php foreach ($schedules as $schedule): ?>
        <article class="admin-row seven">
          <span>
            <strong><?= e($schedule['name']) ?></strong>
            <small><?= e($formatLabels[$schedule['output_format']] ?? $schedule['output_format']) ?></small>
          </span>
          <span><?= e($typeLabels[$schedule['report_type']] ?? $schedule['report_type']) ?></span>
          <span><?= e(substr((string) $schedule['run_time'], 0, 5)) ?></span>
          <span><?= e($schedule['day_of_month'] ?: '-') ?></span>
          <span><small><?= e($schedule['next_run_at'] ?? '-') ?></small></span>
          <span class="state-chip <?= $schedule['is_active'] ? 'ok' : 'muted' ?>"><?= $schedule['is_active'] ? 'Aktif' : 'Pasif' ?></span>
          <span class="row-actions">
            <a class="dark-button small-action" href="<?= e(route('/admin/reports', ['edit_id' => $schedule['id']])) ?>">Düzenle</a>
            <form method="post" action="<?= e(route('/admin/reports')) ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="run">
              <input type="hidden" name="schedule_id" value="<?= e($schedule['id']) ?>">
              <button class="primary-action small-action" type="submit">Çalıştır</button>
            </form>
            <form method="post" action="<?= e(route('/admin/reports')) ?>" onsubmit="return confirm('Bu rapor planını silmek istiyor musunuz?');">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="schedule_id" value="<?= e($schedule['id']) ?>">
              <button class="danger-button small-action" type="submit">Sil</button>
            </form>
          </span>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</section>

<section class="admin-layout single">
  <section class="panel-card wide">
    <div class="section-head">
      <div>
        <p class="eyebrow">Geçmiş</p>
        <h1>Rapor Logları</h1>
      </div>
    </div>

    <div class="admin-table">
      <div class="admin-head five">
        <span>Rapor</span>
        <span>Dönem</span>
        <span>Durum</span>
        <span>Dosya</span>
        <span>Tarih</span>
      </div>
      <?php foreach ($logs as $log): ?>
        <article class="admin-row five">
          <span><strong><?= e($log['schedule_name'] ?? $log['report_type']) ?></strong></span>
          <span><?= e($log['period_start']) ?> / <?= e($log['period_end']) ?></span>
          <span class="state-chip <?= $log['status'] === 'failed' ? 'risk' : ($log['status'] === 'sent' ? 'warning' : 'ok') ?>"><?= e($logStatusLabels[$log['status']] ?? $log['status']) ?></span>
          <span><small><?= e($log['file_path'] ?? '-') ?></small></span>
          <span><?= e($log['generated_at']) ?></span>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</section>
