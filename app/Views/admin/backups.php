<?php

use App\Core\Csrf;

$jobs = $jobs ?? [];
$logs = $logs ?? [];
$frequencyLabels = [
    'hourly' => 'Saatlik',
    'daily' => 'Günlük',
    'weekly' => 'Haftalık',
    'monthly' => 'Aylık',
];
?>
<section class="admin-layout single">
  <section class="wide">
    <div class="section-head">
      <div>
        <p class="eyebrow">Yedekleme</p>
        <h1>Otomatik ve Manuel Yedek</h1>
        <p class="muted">Günlük otomatik yedek için planı aktif bırakın, mail alıcısını yazın ve sunucuda cron işini çalıştırın.</p>
      </div>
    </div>

    <div class="setup-stack">
      <?php foreach ($jobs as $job): ?>
        <form class="panel-card" method="post" action="<?= e(route('/admin/backups')) ?>">
          <?= Csrf::field() ?>
          <input type="hidden" name="job_id" value="<?= e($job['id']) ?>">

          <div class="section-head compact">
            <div>
              <p class="eyebrow">Plan</p>
              <h1><?= e($job['name']) ?></h1>
              <p class="muted">Sonraki çalışma: <?= e($job['next_run_at'] ?? '-') ?> · Son çalışma: <?= e($job['last_run_at'] ?? '-') ?></p>
            </div>
          </div>

          <div class="split-fields">
            <label>
              Sıklık
              <select name="frequency">
                <?php foreach ($frequencyLabels as $frequency => $label): ?>
                  <option value="<?= e($frequency) ?>" <?= ($job['frequency'] ?? 'daily') === $frequency ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label>
              Çalışma saati
              <input name="run_time" type="time" value="<?= e(substr((string) ($job['run_time'] ?? '23:30:00'), 0, 5)) ?>">
            </label>
          </div>

          <label>
            Saklama süresi (gün)
            <input name="retention_days" type="number" min="1" value="<?= e($job['retention_days'] ?? 30) ?>">
          </label>

          <div class="split-fields">
            <label>
              Mail alıcı adı
              <input name="mail_recipient_name" value="<?= e($job['mail_recipient_name'] ?? '') ?>" placeholder="Örn. Genel Müdür">
            </label>
            <label>
              Mail alıcı e-posta
              <input name="mail_recipient_email" type="email" value="<?= e($job['mail_recipient_email'] ?? '') ?>" placeholder="mail@otel.com">
            </label>
          </div>

          <label class="switch-line">
            <input name="is_active" type="checkbox" <?= (int) ($job['is_active'] ?? 0) === 1 ? 'checked' : '' ?>>
            Otomatik yedekleme aktif
          </label>
          <label class="switch-line">
            <input name="mail_enabled" type="checkbox" <?= (int) ($job['mail_enabled'] ?? 0) === 1 ? 'checked' : '' ?>>
            Yedek tamamlanınca mail gönder
          </label>
          <label class="switch-line">
            <input name="include_database" type="checkbox" <?= (int) ($job['include_database'] ?? 0) === 1 ? 'checked' : '' ?>>
            Canlı veritabanı dump dosyasını yedeğe ekle
          </label>
          <label class="switch-line">
            <input name="include_uploads" type="checkbox" <?= (int) ($job['include_uploads'] ?? 0) === 1 ? 'checked' : '' ?>>
            Yüklenen dosyaları yedeğe ekle
          </label>

          <div class="form-actions">
            <button class="primary-action" name="action" value="save" type="submit">Planı Kaydet</button>
            <button class="dark-button" name="action" value="run" type="submit">Şimdi Yedek Al</button>
          </div>
        </form>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel-card wide">
    <div class="section-head">
      <div>
        <p class="eyebrow">Geçmiş</p>
        <h1>Yedek Logları</h1>
      </div>
    </div>

    <div class="admin-table">
      <div class="admin-head six">
        <span>Dosya</span>
        <span>Durum</span>
        <span>Boyut</span>
        <span>Yol</span>
        <span>Tarih</span>
        <span>İşlem</span>
      </div>
      <?php foreach ($logs as $log): ?>
        <article class="admin-row six">
          <span>
            <strong><?= e($log['file_name']) ?></strong>
            <small><?= e($log['job_name'] ?? '-') ?></small>
          </span>
          <span class="state-chip <?= $log['status'] === 'success' ? 'ok' : ($log['status'] === 'failed' ? 'risk' : 'muted') ?>"><?= e($log['status']) ?></span>
          <span><?= e($log['file_size_bytes'] ? round(((int) $log['file_size_bytes']) / 1024, 1) . ' KB' : '-') ?></span>
          <span><small><?= e($log['file_path']) ?></small></span>
          <span><?= e($log['started_at']) ?></span>
          <span class="row-actions">
            <?php if ($log['status'] === 'success' && is_file((string) $log['file_path'])): ?>
              <a class="dark-button small-action" href="<?= e(route('/admin/backups/download', ['log_id' => $log['id']])) ?>">İndir</a>
            <?php else: ?>
              <span class="state-chip muted">Yok</span>
            <?php endif; ?>
          </span>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</section>
