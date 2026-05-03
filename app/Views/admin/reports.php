<?php

use App\Core\Csrf;

$schedules = $schedules ?? [];
$logs = $logs ?? [];
$settings = $settings ?? [];
$editingSchedule = $editingSchedule ?? null;
$isEditing = is_array($editingSchedule);
$typeLabels = ['daily' => 'Günlük', 'weekly' => 'Haftalık', 'monthly' => 'Aylık'];
$formatLabels = ['html' => 'HTML', 'pdf' => 'PDF', 'xlsx' => 'Excel', 'csv' => 'CSV'];
$logStatusLabels = ['created' => 'Oluşturuldu', 'sent' => 'Kuyruğa alındı', 'failed' => 'Hata'];
$reportTemplates = $reportTemplates ?? [];
$lastTestRecipientEmail = (string) ($settings['test_mail.last_recipient_email'] ?? '');
$lastTestRecipientName = (string) ($settings['test_mail.last_recipient_name'] ?? '');
$reportPlaceholders = [
    '{report_name}' => 'Rapor adı',
    '{period_start}' => 'Dönem başlangıcı',
    '{period_end}' => 'Dönem bitişi',
    '{generated_at}' => 'Oluşturma tarihi',
    '{total_entries}' => 'Toplam giriş',
    '{total_exits}' => 'Toplam çıkış',
    '{still_inside}' => 'İçeride kalan',
    '{overdue_count}' => 'Süre aşımı',
    '{category_rows}' => 'Kategori tablo satırları',
    '{department_rows}' => 'Departman tablo satırları',
];
$grapesCssVersion = is_file(BASE_PATH . '/public/assets/vendor/grapesjs/grapes.min.css') ? (string) filemtime(BASE_PATH . '/public/assets/vendor/grapesjs/grapes.min.css') : '1';
$grapesJsVersion = is_file(BASE_PATH . '/public/assets/vendor/grapesjs/grapes.min.js') ? (string) filemtime(BASE_PATH . '/public/assets/vendor/grapesjs/grapes.min.js') : '1';
$grapesIntegrationVersion = is_file(BASE_PATH . '/public/assets/grapesjs-integration.js') ? (string) filemtime(BASE_PATH . '/public/assets/grapesjs-integration.js') : '1';
?>
<link rel="stylesheet" href="/assets/vendor/grapesjs/grapes.min.css?v=<?= e($grapesCssVersion) ?>">

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

<section class="admin-layout single report-template-page">
  <form class="panel-card wide" method="post" action="<?= e(route('/admin/reports')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="save_templates">
    <div class="section-head">
      <div>
        <p class="eyebrow">GrapesJS</p>
        <h1>Rapor Tasarımları</h1>
        <p class="muted">HTML raporların görünümünü görsel editörle düzenleyin. PDF çıktısı mevcut sabit PDF motoruyla oluşturulmaya devam eder.</p>
      </div>
      <button class="primary-action" type="submit">Tasarımı Kaydet</button>
    </div>

    <div class="test-mail-panel">
      <div>
        <strong>Rapor test maili</strong>
        <span>Seçtiğiniz rapor tasarımını örnek metriklerle mail gövdesinde ve HTML ek olarak gönderir. Son kullanılan adres otomatik kalır.</span>
      </div>
      <label>
        E-posta
        <input name="test_recipient_email" type="email" value="<?= e($lastTestRecipientEmail) ?>" placeholder="ornek@otel.com">
      </label>
      <label>
        Alıcı adı
        <input name="test_recipient_name" value="<?= e($lastTestRecipientName) ?>" placeholder="Test Alıcısı">
      </label>
    </div>

    <div class="token-list" aria-label="Rapor alanları">
      <?php foreach ($reportPlaceholders as $placeholder => $placeholderLabel): ?>
        <button type="button" data-template-token="<?= e($placeholder) ?>">
          <strong><?= e($placeholderLabel) ?></strong>
          <small><?= e($placeholder) ?></small>
        </button>
      <?php endforeach; ?>
    </div>

    <?php foreach ($reportTemplates as $template): ?>
      <details class="form-section template-editor" open>
        <summary>
          <span>
            <strong><?= e($template['label']) ?></strong>
            <small>Rapor HTML dosyasının başlık, metrik ve tablo alanlarını düzenler.</small>
          </span>
        </summary>
        <label>
          HTML Tasarım
          <span>Görsel editörle düzenleyin veya teknik kullanıcılar için HTML'i doğrudan değiştirin.</span>
          <textarea
            id="report-template-html-<?= e($template['type']) ?>"
            data-template-input
            name="report_templates[<?= e($template['type']) ?>][html]"
            rows="6"
          ><?= e($template['html']) ?></textarea>
          <textarea
            id="report-template-css-<?= e($template['type']) ?>"
            hidden
            name="report_templates[<?= e($template['type']) ?>][css]"
          ><?= e($template['css']) ?></textarea>
          <button
            class="dark-button visual-editor-button"
            type="button"
            data-grapesjs-open
            data-grapesjs-title="<?= e($template['label']) ?> Görsel Rapor Editörü"
            data-grapesjs-source="report-template-html-<?= e($template['type']) ?>"
            data-grapesjs-css="report-template-css-<?= e($template['type']) ?>"
          >Görsel Editör</button>
        </label>
        <div class="template-test-actions">
          <button
            class="ghost-link small-action"
            type="submit"
            name="test_report_template"
            value="<?= e($template['type']) ?>"
          >Bu Raporu Test Mail Gönder</button>
        </div>
      </details>
    <?php endforeach; ?>

    <div class="form-actions">
      <button class="primary-action" type="submit">Tasarımı Kaydet</button>
      <a class="ghost-link" href="<?= e(route('/admin/reports')) ?>">Raporlara Dön</a>
    </div>
  </form>
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

<section class="visual-editor-overlay" data-grapesjs-modal hidden aria-hidden="true">
  <div class="visual-editor-dialog" role="dialog" aria-modal="true" aria-labelledby="visual-editor-title">
    <div class="visual-editor-head">
      <div>
        <p class="eyebrow">GrapesJS</p>
        <h1 id="visual-editor-title" data-grapesjs-title>Görsel Editör</h1>
        <p class="muted">Blokları sürükleyin, metni düzenleyin ve değişken butonlarıyla rapor alanlarını ekleyin.</p>
      </div>
      <button class="ghost-link" type="button" data-grapesjs-close>Kapat</button>
    </div>
    <p class="flash warning" data-grapesjs-notice hidden></p>
    <div class="visual-editor-grid">
      <aside class="visual-editor-side">
        <strong>Bloklar</strong>
        <div data-grapesjs-blocks></div>
      </aside>
      <div class="visual-editor-canvas" data-grapesjs-root></div>
      <aside class="visual-editor-side">
        <strong>Stil</strong>
        <div data-grapesjs-styles></div>
        <div data-grapesjs-traits></div>
        <div data-grapesjs-selectors></div>
        <div data-grapesjs-layers></div>
      </aside>
    </div>
    <div class="visual-editor-actions">
      <button class="ghost-link" type="button" data-grapesjs-sync>Tasarımı Metne Aktar</button>
      <button class="primary-action" type="button" data-grapesjs-apply>Uygula ve Kapat</button>
    </div>
  </div>
</section>

<script src="/assets/vendor/grapesjs/grapes.min.js?v=<?= e($grapesJsVersion) ?>"></script>
<script src="/assets/grapesjs-integration.js?v=<?= e($grapesIntegrationVersion) ?>"></script>
<script>
  (() => {
    document.querySelectorAll("[data-template-token]").forEach((button) => {
      button.addEventListener("click", () => {
        const token = button.dataset.templateToken || "";
        if (window.hotelTemplateVisualEditors && window.hotelTemplateVisualEditors.insertToken(token)) {
          return;
        }

        const active = document.activeElement;
        const field = active && active.matches("[data-template-input]")
          ? active
          : document.querySelector(".report-template-page textarea[data-template-input]");

        if (!field) {
          return;
        }

        const start = field.selectionStart || field.value.length;
        const end = field.selectionEnd || field.value.length;
        field.value = field.value.slice(0, start) + token + field.value.slice(end);
        field.focus();
        field.setSelectionRange(start + token.length, start + token.length);
        field.dispatchEvent(new Event("input", { bubbles: true }));
      });
    });
  })();
</script>
