<?php

use App\Core\Csrf;

$channels = $channels ?? [];
$channelConfig = static function (array $channel): array {
    $decoded = json_decode((string) ($channel['config_json'] ?? '{}'), true);
    return is_array($decoded) ? $decoded : [];
};
?>
<section class="admin-layout single">
  <section class="panel-card wide">
    <div class="section-head">
      <div>
        <p class="eyebrow">Kanal ayarları</p>
        <h1>Mail / Telegram / WhatsApp</h1>
      </div>
    </div>

    <div class="channel-grid">
      <?php foreach ($channels as $channel): ?>
        <?php
          $config = $channelConfig($channel);
          $isConfigured = (bool) ($config['configured'] ?? false);
          $testPlaceholder = match ($channel['code']) {
              'mail' => 'test@otel.com',
              'telegram' => 'Telegram chat ID',
              'whatsapp' => '905xxxxxxxxx',
              default => 'Test alıcısı',
          };
          $testLabel = match ($channel['code']) {
              'mail' => 'Test e-posta adresi',
              'telegram' => 'Test Telegram Chat ID',
              'whatsapp' => 'Test WhatsApp numarası',
              default => 'Test alıcısı',
          };
        ?>
        <form class="channel-card wide-channel" method="post" action="<?= e(route('/admin/notification-channels')) ?>">
          <?= Csrf::field() ?>
          <input type="hidden" name="code" value="<?= e($channel['code']) ?>">

          <div class="section-head compact">
            <div>
              <p class="eyebrow"><?= e($channel['code']) ?></p>
              <h2><?= e($channel['name']) ?></h2>
            </div>
            <label class="switch-inline">
              <input type="checkbox" name="is_enabled" <?= $channel['is_enabled'] ? 'checked' : '' ?>>
              Aktif
            </label>
          </div>

          <label class="switch-line">
            <input type="checkbox" name="configured" <?= $isConfigured ? 'checked' : '' ?>>
            Ayarları tamamlandı
          </label>

          <label>
            Kanal adı
            <input name="name" value="<?= e($channel['name']) ?>" required>
          </label>

          <?php if ($channel['code'] === 'mail'): ?>
            <div class="split-fields">
              <label>
                Mail gönderim tipi
                <select name="mail_driver">
                  <option value="php_mail" <?= ($config['driver'] ?? 'php_mail') === 'php_mail' ? 'selected' : '' ?>>PHP mail()</option>
                  <option value="smtp" <?= ($config['driver'] ?? '') === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                </select>
              </label>
              <label>
                Gönderen adı
                <input name="from_name" value="<?= e($config['from_name'] ?? 'Otel Güvenlik') ?>">
              </label>
            </div>

            <label>
              Gönderen mail adresi
              <input name="from_email" type="email" value="<?= e($config['from_email'] ?? '') ?>" placeholder="security@otel.com">
            </label>

            <div class="split-fields">
              <label>
                SMTP host
                <input name="smtp_host" value="<?= e($config['smtp_host'] ?? '') ?>" placeholder="smtp.mailserver.com">
              </label>
              <label>
                SMTP port
                <input name="smtp_port" type="number" min="1" value="<?= e($config['smtp_port'] ?? 587) ?>">
              </label>
            </div>

            <div class="split-fields">
              <label>
                SMTP şifreleme
                <select name="smtp_encryption">
                  <option value="tls" <?= ($config['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS / STARTTLS</option>
                  <option value="ssl" <?= ($config['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                  <option value="none" <?= ($config['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Yok</option>
                </select>
              </label>
              <label>
                SMTP zaman aşımı
                <input name="smtp_timeout" type="number" min="5" value="<?= e($config['smtp_timeout'] ?? 20) ?>">
              </label>
            </div>

            <div class="split-fields">
              <label>
                SMTP kullanıcı adı
                <input name="smtp_username" value="<?= e($config['smtp_username'] ?? '') ?>">
              </label>
              <label>
                SMTP şifresi
                <input name="smtp_password" type="password" placeholder="<?= !empty($config['smtp_password']) ? 'Kayıtlı - değiştirmek için yazın' : '' ?>">
              </label>
            </div>
          <?php elseif ($channel['code'] === 'telegram'): ?>
            <label>
              Bot token
              <input name="bot_token" type="password" placeholder="<?= !empty($config['bot_token']) ? 'Kayıtlı - değiştirmek için yazın' : '123456:ABC...' ?>">
            </label>

            <label>
              Parse mode
              <select name="parse_mode">
                <option value="" <?= ($config['parse_mode'] ?? '') === '' ? 'selected' : '' ?>>Düz metin</option>
                <option value="HTML" <?= ($config['parse_mode'] ?? '') === 'HTML' ? 'selected' : '' ?>>HTML</option>
                <option value="MarkdownV2" <?= ($config['parse_mode'] ?? '') === 'MarkdownV2' ? 'selected' : '' ?>>MarkdownV2</option>
              </select>
            </label>
          <?php elseif ($channel['code'] === 'whatsapp'): ?>
            <label>
              Sağlayıcı
              <select name="provider">
                <option value="meta_cloud" <?= ($config['provider'] ?? 'meta_cloud') === 'meta_cloud' ? 'selected' : '' ?>>Meta Cloud API</option>
                <option value="custom_http" <?= ($config['provider'] ?? '') === 'custom_http' ? 'selected' : '' ?>>Özel HTTP API</option>
              </select>
            </label>

            <div class="split-fields">
              <label>
                API versiyonu
                <input name="api_version" value="<?= e($config['api_version'] ?? 'v20.0') ?>">
              </label>
              <label>
                Phone Number ID
                <input name="phone_number_id" value="<?= e($config['phone_number_id'] ?? '') ?>">
              </label>
            </div>

            <label>
              Access token
              <input name="access_token" type="password" placeholder="<?= !empty($config['access_token']) ? 'Kayıtlı - değiştirmek için yazın' : '' ?>">
            </label>

            <label>
              Özel endpoint
              <input name="endpoint" value="<?= e($config['endpoint'] ?? '') ?>" placeholder="Boşsa Meta Cloud endpoint otomatik kullanılır">
            </label>
          <?php else: ?>
            <label>
              JSON ayarları
              <textarea name="config_json" rows="8" spellcheck="false"><?= e($channel['config_json'] ?? '{}') ?></textarea>
            </label>
          <?php endif; ?>

          <div class="test-box">
            <p class="eyebrow">Kanal testi</p>
            <label>
              <?= e($testLabel) ?>
              <input name="test_recipient" placeholder="<?= e($testPlaceholder) ?>">
            </label>
            <label>
              Test mesajı
              <textarea name="test_message" rows="3">Otel Güvenlik Sistemi test bildirimi.</textarea>
            </label>
          </div>

          <div class="form-actions">
            <button class="primary-action" type="submit" name="action" value="save">Kaydet</button>
            <button class="dark-button" type="submit" name="action" value="test">Test Gönder</button>
          </div>
        </form>
      <?php endforeach; ?>
    </div>
  </section>
</section>
