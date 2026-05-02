<?php

use App\Core\Csrf;

$templates = $templates ?? [];
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
?>
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
            <span>Mail içinde okunacak açıklama metni. Satır satır yazabilirsiniz.</span>
            <textarea data-template-input name="templates[<?= e($template['event_type']) ?>][body]" rows="5"><?= e($template['body']) ?></textarea>
          </label>
        </div>
        <div class="mail-preview">
          <span>Mail Ön İzleme</span>
          <strong data-preview-subject></strong>
          <p data-preview-body></p>
        </div>
      </details>
    <?php endforeach; ?>

    <div class="form-actions">
      <button class="primary-action" type="submit">Şablonları Kaydet</button>
      <a class="ghost-link" href="<?= e(route('/admin')) ?>">Yönetim</a>
    </div>
  </form>
</section>

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
        bodyPreview.textContent = render(body.value);
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
