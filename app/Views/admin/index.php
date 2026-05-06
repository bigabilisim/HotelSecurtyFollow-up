<?php

use App\Core\Csrf;
use App\Core\Auth;

$settings = $settings ?? [];
$users = $users ?? [];
$unfinishedSuggestionCount = (int) ($unfinishedSuggestionCount ?? 0);
$canCategories = Auth::can('categories.manage');
$canDepartments = Auth::can('departments.manage');
$canUsers = Auth::can('users.manage');
$canVisitors = Auth::can('visitors.manage');
$canWatchlist = Auth::can('watchlist.manage');
$canNotificationRules = Auth::can('notification_rules.manage');
$canMailTemplates = Auth::can('mail_templates.manage');
$canNotificationChannels = Auth::can('notification_channels.manage');
$canReports = Auth::can('reports.manage');
$canRecords = Auth::can('reports.view');
$canBackups = Auth::can('backups.manage');
$canSettings = Auth::can('settings.manage');
$canSuggestions = Auth::can('suggestions.manage');
?>
<section class="admin-hero">
  <div>
    <p class="eyebrow">Yönetim paneli</p>
    <h1>Tanımlar ve Kurallar</h1>
    <p class="muted">Kategori, departman, kullanıcı/yetki ve bildirim kuralı tanımlarını buradan yönetin.</p>
  </div>
  <div class="admin-hero-actions">
    <a class="primary-action" href="<?= e(route('/admin/guide')) ?>">V1.12 Kullanım Kılavuzu</a>
  </div>
</section>

<div class="admin-grid-toolbar" data-admin-sortable-toolbar hidden>
  <button class="ghost-link compact-action" type="button" data-admin-sort-reset>Varsayılan Sıra</button>
</div>

<section class="admin-grid" data-admin-sortable>
  <a class="admin-card guide-card-link" href="<?= e(route('/admin/guide')) ?>">
    <span>Yardım</span>
    <strong>V1.12 Kullanım Kılavuzu</strong>
    <small>Ekran görüntüleriyle giriş, canlı panel, rapor, bildirim, yedekleme ve yetki adımları.</small>
  </a>

  <?php if ($canCategories): ?>
    <a class="admin-card" href="<?= e(route('/admin/categories')) ?>">
      <span>Kategoriler</span>
      <strong>Kategori Tanımlama</strong>
      <small>VIP, tedarikçi, ziyaretçi, denetçi ve süre kuralları.</small>
    </a>
  <?php endif; ?>

  <?php if ($canDepartments): ?>
    <a class="admin-card" href="<?= e(route('/admin/departments')) ?>">
      <span>Departmanlar</span>
      <strong>Departman Yönetimi</strong>
      <small>Departman ve amir bilgileri.</small>
    </a>
  <?php endif; ?>

  <?php if ($canUsers): ?>
    <a class="admin-card" href="<?= e(route('/admin/users')) ?>">
      <span>Kullanıcılar</span>
      <strong>Kullanıcı ve Yetki</strong>
      <small>Panel kullanıcıları ve rol atamaları.</small>
    </a>
  <?php endif; ?>

  <?php if ($canVisitors): ?>
    <a class="admin-card" href="<?= e(route('/admin/visitors')) ?>">
      <span>Kişiler</span>
      <strong>Kayıtlı Kişiler</strong>
      <small>Tekrar gelen kişi kartları, telefon, firma ve plaka bilgileri.</small>
    </a>
  <?php endif; ?>

  <?php if ($canWatchlist): ?>
    <a class="admin-card" href="<?= e(route('/admin/watchlist')) ?>">
      <span>Güvenlik</span>
      <strong>Kara / Uyarı Listesi</strong>
      <small>Ad, telefon veya plaka eşleşmesine göre girişte uyarı veya blok.</small>
    </a>
  <?php endif; ?>

  <?php if ($canSettings): ?>
    <a class="admin-card" href="<?= e(route('/admin/ip-blocks')) ?>">
      <span>Giriş Güvenliği</span>
      <strong>IP Blokları</strong>
      <small>Yanlış şifre denemesiyle banlanan IP adreslerini izle ve aç.</small>
    </a>
  <?php endif; ?>

  <?php if ($canNotificationRules): ?>
    <a class="admin-card" href="<?= e(route('/admin/notification-rules')) ?>">
      <span>Bildirim</span>
      <strong>Bildirim Kuralları</strong>
      <small>Mail, Telegram ve WhatsApp alıcı kuralları.</small>
    </a>
  <?php endif; ?>

  <?php if ($canMailTemplates): ?>
    <a class="admin-card" href="<?= e(route('/admin/mail-templates')) ?>">
      <span>Mail</span>
      <strong>Mail Şablonları</strong>
      <small>Konu ve gövde metinleri.</small>
    </a>
  <?php endif; ?>

  <?php if ($canNotificationChannels): ?>
    <a class="admin-card" href="<?= e(route('/admin/notification-channels')) ?>">
      <span>Kanallar</span>
      <strong>Kanal Ayarları</strong>
      <small>Bot token, endpoint, mail gönderen bilgileri.</small>
    </a>
  <?php endif; ?>

  <?php if ($canReports): ?>
    <a class="admin-card" href="<?= e(route('/admin/reports')) ?>">
      <span>Raporlar</span>
      <strong>Planlı Raporlar</strong>
      <small>Özet rapor oluşturma ve gönderim geçmişi.</small>
    </a>
  <?php endif; ?>

  <?php if ($canRecords): ?>
    <a class="admin-card" href="<?= e(route('/admin/records')) ?>">
      <span>Kayıtlar</span>
      <strong>Kayıt Listesi</strong>
      <small>Giriş çıkış kayıtlarını filtrele, PDF indir veya mail gönder.</small>
    </a>
  <?php endif; ?>

  <?php if ($canBackups): ?>
    <a class="admin-card" href="<?= e(route('/admin/backups')) ?>">
      <span>Yedek</span>
      <strong>Yedekleme</strong>
      <small>Manuel yedek, otomatik plan ve geri yükleme staging.</small>
    </a>
  <?php endif; ?>

  <?php if ($canSuggestions): ?>
    <a class="admin-card feedback-alert-card <?= $unfinishedSuggestionCount > 0 ? 'has-open-feedback' : '' ?>" href="<?= e(route('/admin/suggestions')) ?>">
      <span><?= $unfinishedSuggestionCount > 0 ? 'Geri bildirim - Bekliyor' : 'Geri bildirim' ?></span>
      <strong>Kullanıcı Önerileri</strong>
      <?php if ($unfinishedSuggestionCount > 0): ?>
        <em class="feedback-count"><?= e($unfinishedSuggestionCount) ?></em>
      <?php endif; ?>
      <small>
        <?= $unfinishedSuggestionCount > 0
          ? e($unfinishedSuggestionCount) . ' tamamlanmamış öneri var. İnceleme bekleyen geri bildirimleri takip edin.'
          : 'Personelden gelen fikir, hata bildirimi ve eğitim ihtiyaçlarını takip edin.' ?>
      </small>
    </a>
  <?php endif; ?>
</section>

<?php if ($canSettings): ?>
  <section class="admin-layout single">
    <form class="panel-card wide" method="post" action="<?= e(route('/admin/escalation-settings')) ?>">
      <?= Csrf::field() ?>
      <div class="section-head">
        <div>
          <p class="eyebrow">Global Zincir</p>
          <h1>Eskalasyon Zinciri</h1>
          <p class="muted">Bu zincir tüm kategoriler için geçerlidir. Cevap gelmezse sıradaki N+ amire geçer; son tanımlı amirde beklemede kalır.</p>
        </div>
      </div>

      <?php for ($level = 1; $level <= 3; $level++): ?>
        <?php
          $userKey = 'escalation.level_' . $level . '_user_id';
          $minutesKey = 'escalation.level_' . $level . '_after_minutes';
        ?>
        <div class="split-fields">
          <label>
            N+<?= e($level) ?> amiri
            <select name="level_<?= e($level) ?>_user_id">
              <option value="">Seçiniz</option>
              <?php foreach ($users as $user): ?>
                <option value="<?= e($user['id']) ?>" <?= (int) ($settings[$userKey] ?? 0) === (int) $user['id'] ? 'selected' : '' ?>>
                  <?= e($user['full_name']) ?><?= !empty($user['department_name']) ? ' - ' . e($user['department_name']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            N+<?= e($level) ?> bekleme süresi (dk)
            <input name="level_<?= e($level) ?>_after_minutes" type="number" min="0" value="<?= e($settings[$minutesKey] ?? ($level === 1 ? 5 : ($level === 2 ? 10 : 15))) ?>">
          </label>
        </div>
      <?php endfor; ?>

      <div class="form-actions">
        <button class="primary-action" type="submit">Zinciri Kaydet</button>
      </div>
    </form>

    <form class="panel-card wide" method="post" action="<?= e(route('/admin/security-settings')) ?>">
      <?= Csrf::field() ?>
      <div class="section-head">
        <div>
          <p class="eyebrow">Sistem Modülleri</p>
          <h1>Güvenlik ve Operasyon Ayarları</h1>
          <p class="muted">Hatalı giriş uyarılarını ve işletmeye göre kullanılacak ek operasyon modüllerini buradan açıp kapatın.</p>
        </div>
      </div>

      <div class="settings-toggle-grid">
        <label class="settings-toggle-card">
          <input type="checkbox" name="failed_login_alert_enabled" <?= (string) ($settings['security.failed_login_alert_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span class="permission-card-toggle" aria-hidden="true"></span>
          <span>
            <strong>Hatalı giriş mail uyarısı</strong>
            <small>Yanlış şifre denemelerinde kullanıcı adı, IP, tarih ve cihaz bilgisi e-posta olarak gönderilir.</small>
          </span>
        </label>

        <label class="settings-toggle-card is-feature">
          <input type="checkbox" name="external_movements_enabled" <?= (string) ($settings['external_movements.enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span class="permission-card-toggle" aria-hidden="true"></span>
          <span>
            <strong>Dış Görev Takibi</strong>
            <small>Sahil veya dış hizmete çıkan personel/araçlar için alt menüde hızlı çıkış-giriş ekranı açılır.</small>
          </span>
        </label>
      </div>

      <label>
        Uyarı e-posta adresi
        <input
          name="failed_login_alert_email"
          type="email"
          placeholder="guvenlik@otel.com"
          value="<?= e($settings['security.failed_login_alert_email'] ?? '') ?>"
        >
      </label>

      <p class="muted">Mailin çıkması için Kanal Ayarları bölümündeki Mail ayarının aktif ve doğru olması gerekir. Dış Görev Takibi için kullanıcıya ayrıca Kullanıcı ve Yetki ekranından dış görev yetkileri verilmelidir.</p>

      <div class="form-actions">
        <button class="primary-action" type="submit">Ayarları Kaydet</button>
      </div>
    </form>
  </section>
<?php endif; ?>
