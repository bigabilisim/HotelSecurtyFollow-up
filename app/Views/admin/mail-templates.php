<?php

use App\Core\Csrf;

$templates = $templates ?? [];
$settings = $settings ?? [];
$lastTestRecipientEmail = (string) ($settings['test_mail.last_recipient_email'] ?? '');
$lastTestRecipientName = (string) ($settings['test_mail.last_recipient_name'] ?? '');
$descriptions = [
    'entry' => 'Bir kişi otele giriş yaptığında gönderilir.',
    'exit' => 'Bir kişi otelden çıkış yaptığında gönderilir.',
    'timeout_warning' => 'Kategorideki süre dolmadan önce hatırlatma olarak gönderilir.',
    'department_question' => 'Süre dolunca ilgili kişiye “sizinle beraber mi?” sorusu gider.',
    'department_answer_no' => 'Cevap Hayır olduğunda yöneticilere bilgi verir.',
    'department_no_response' => 'Cevap gelmezse eskalasyon bilgilendirmesi yapar.',
    'escalation' => 'Global N+ zincirinde sıradaki amire gönderilir.',
    'daily_report' => 'Gün sonu raporu hazırlandığında gönderilir.',
    'weekly_report' => 'Haftalık rapor hazırlandığında gönderilir.',
    'monthly_report' => 'Ay sonu raporu hazırlandığında gönderilir.',
];
$placeholders = [
    '{visitor_name}' => 'Ziyaretçi adı',
    '{visitor_phone}' => 'Telefon',
    '{company}' => 'Firma',
    '{vehicle_plate}' => 'Plaka',
    '{category_name}' => 'Kategori',
    '{department_name}' => 'Departman',
    '{host_name}' => 'Görüşülecek kişi',
    '{purpose}' => 'Geliş amacı',
    '{entry_at}' => 'Giriş saati',
    '{exit_at}' => 'Çıkış saati',
    '{elapsed_minutes}' => 'İçerideki dakika',
    '{question_text}' => 'Sistem sorusu',
    '{subject}' => 'Varsayılan konu',
    '{message}' => 'Varsayılan mesaj',
    '{report_name}' => 'Rapor adı',
    '{report_summary}' => 'Rapor özeti',
    '{period_start}' => 'Rapor başlangıcı',
    '{period_end}' => 'Rapor bitişi',
    '{file_path}' => 'Rapor dosyası',
];
$grapesCssVersion = is_file(BASE_PATH . '/public/assets/vendor/grapesjs/grapes.min.css') ? (string) filemtime(BASE_PATH . '/public/assets/vendor/grapesjs/grapes.min.css') : '1';
$grapesJsVersion = is_file(BASE_PATH . '/public/assets/vendor/grapesjs/grapes.min.js') ? (string) filemtime(BASE_PATH . '/public/assets/vendor/grapesjs/grapes.min.js') : '1';
$grapesIntegrationVersion = is_file(BASE_PATH . '/public/assets/grapesjs-integration.js') ? (string) filemtime(BASE_PATH . '/public/assets/grapesjs-integration.js') : '1';
?>
<link rel="stylesheet" href="/assets/vendor/grapesjs/grapes.min.css?v=<?= e($grapesCssVersion) ?>">

<section class="admin-layout single mail-template-page">
  <form class="panel-card wide" method="post" action="<?= e(route('/admin/mail-templates')) ?>">
    <?= Csrf::field() ?>
    <div class="section-head">
      <div>
        <p class="eyebrow">Mail</p>
        <h1>Mail Şablonları</h1>
        <p class="muted">Mail konu ve metinlerini buradan sade şekilde düzenleyin. Sistem, süslü parantezli alanları gönderim sırasında gerçek bilgiyle değiştirir.</p>
      </div>
      <button class="primary-action" type="submit">Şablonları Kaydet</button>
    </div>

    <div class="template-guide">
      <div>
        <strong>Kısa kullanım</strong>
        <span>Konu mail başlığıdır. Gövde mailin ana yazısıdır. Her satıra kısa ve net bilgi yazabilirsiniz.</span>
      </div>
      <div>
        <strong>Otomatik alanlar</strong>
        <span>Önce konu veya gövde alanına tıklayın, sonra aşağıdaki değişkenlerden birini seçin. Örneğin <code>{visitor_name}</code> mailde gerçek isim olarak görünür.</span>
      </div>
      <div>
        <strong>Cevap butonları</strong>
        <span>Departman doğrulama maillerinde Evet/Hayır butonlarını sistem kendisi ekler; şablona ayrıca yazmanız gerekmez.</span>
      </div>
    </div>

    <div class="test-mail-panel">
      <div>
        <strong>Test mail alıcısı</strong>
        <span>Şablonları kaydetmeden önce seçtiğiniz şablonu örnek verilerle bu adrese gönderebilirsiniz. Son kullanılan adres otomatik kalır.</span>
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

    <div class="token-list" aria-label="Şablon alanları">
      <?php foreach ($placeholders as $placeholder => $placeholderLabel): ?>
        <button type="button" data-template-token="<?= e($placeholder) ?>">
          <strong><?= e($placeholderLabel) ?></strong>
          <small><?= e($placeholder) ?></small>
        </button>
      <?php endforeach; ?>
    </div>

    <?php foreach ($templates as $template): ?>
      <details class="form-section template-editor" data-template-section open>
        <summary>
          <span>
            <strong><?= e($template['label']) ?></strong>
            <small><?= e($descriptions[$template['event_type']] ?? 'Mail gönderiminde kullanılır.') ?></small>
          </span>
        </summary>
        <div class="template-fields">
          <label>
            Konu
            <span>Mail kutusunda görünen kısa başlık.</span>
            <input data-template-input name="templates[<?= e($template['event_type']) ?>][subject]" value="<?= e($template['subject']) ?>">
          </label>
          <label>
            Gövde
            <span>Mail içinde okunacak açıklama metni. İsterseniz görsel editörle HTML tasarlayabilirsiniz.</span>
            <textarea
              id="mail-template-body-<?= e($template['event_type']) ?>"
              data-template-input
              name="templates[<?= e($template['event_type']) ?>][body]"
              rows="5"
            ><?= e($template['body']) ?></textarea>
            <textarea
              id="mail-template-css-<?= e($template['event_type']) ?>"
              hidden
              name="templates[<?= e($template['event_type']) ?>][css]"
            ><?= e($template['css'] ?? '') ?></textarea>
            <button
              class="dark-button visual-editor-button"
              type="button"
              data-grapesjs-open
              data-grapesjs-title="<?= e($template['label']) ?> Görsel Mail Editörü"
              data-grapesjs-source="mail-template-body-<?= e($template['event_type']) ?>"
              data-grapesjs-css="mail-template-css-<?= e($template['event_type']) ?>"
            >Görsel Editör</button>
          </label>
        </div>
        <div class="mail-preview">
          <span>Mail Ön İzleme</span>
          <strong data-preview-subject></strong>
          <div class="mail-preview-body" data-preview-body></div>
        </div>
        <div class="template-test-actions">
          <button
            class="ghost-link small-action"
            type="submit"
            name="test_template"
            value="<?= e($template['event_type']) ?>"
          >Bu Şablonu Test Mail Gönder</button>
        </div>
      </details>
    <?php endforeach; ?>

    <div class="form-actions">
      <button class="primary-action" type="submit">Şablonları Kaydet</button>
      <a class="ghost-link" href="<?= e(route('/admin')) ?>">Yönetim</a>
    </div>
  </form>
</section>

<section class="visual-editor-overlay" data-grapesjs-modal hidden aria-hidden="true">
  <div class="visual-editor-dialog" role="dialog" aria-modal="true" aria-labelledby="visual-editor-title">
    <div class="visual-editor-head">
      <div>
        <p class="eyebrow">GrapesJS</p>
        <h1 id="visual-editor-title" data-grapesjs-title>Görsel Editör</h1>
        <p class="muted">Blokları sürükleyin, metni düzenleyin ve değişken butonlarıyla otomatik alan ekleyin.</p>
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
    const samples = {
      "{visitor_name}": "Ahmet Yilmaz",
      "{visitor_phone}": "0555 000 00 00",
      "{company}": "ABC Turizm",
      "{vehicle_plate}": "34 ABC 123",
      "{category_name}": "Ziyaretci",
      "{department_name}": "On Buro",
      "{host_name}": "Mehmet Bey",
      "{purpose}": "Gorusme",
      "{entry_at}": "01.05.2026 14:30",
      "{exit_at}": "01.05.2026 15:10",
      "{elapsed_minutes}": "40",
      "{question_text}": "Ahmet Yilmaz halen sizinle beraber mi?",
      "{message}": "N+1 eskalasyon bildirimi.",
      "{report_name}": "Gun sonu raporu",
      "{report_summary}": "Toplam 18 giris, 16 cikis.",
      "{period_start}": "01.05.2026 00:00",
      "{period_end}": "01.05.2026 23:59",
      "{file_path}": "storage/reports/daily.html"
    };

    function render(value) {
      let output = String(value || "");
      Object.entries(samples).forEach(([key, sample]) => {
        output = output.split(key).join(sample);
      });
      return output;
    }

    function updatePreview(section) {
      const subject = section.querySelector("input[data-template-input]");
      const body = section.querySelector("textarea[data-template-input]");
      const subjectPreview = section.querySelector("[data-preview-subject]");
      const bodyPreview = section.querySelector("[data-preview-body]");

      if (subjectPreview && subject) {
        subjectPreview.textContent = render(subject.value);
      }

      if (bodyPreview && body) {
        const rendered = render(body.value);
        if (/<[a-z][\s\S]*>/i.test(rendered)) {
          bodyPreview.innerHTML = rendered;
        } else {
          bodyPreview.textContent = rendered;
        }
      }
    }

    document.querySelectorAll("[data-template-section]").forEach((section) => {
      section.querySelectorAll("[data-template-input]").forEach((field) => {
        field.addEventListener("input", () => updatePreview(section));
      });
      updatePreview(section);
    });

    document.querySelectorAll("[data-template-token]").forEach((button) => {
      button.addEventListener("click", () => {
        const token = button.dataset.templateToken || "";
        if (window.hotelTemplateVisualEditors && window.hotelTemplateVisualEditors.insertToken(token)) {
          return;
        }
        const active = document.activeElement;
        const field = active && active.matches("[data-template-input]")
          ? active
          : document.querySelector("[data-template-section] textarea[data-template-input]");

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
