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
        <form class="channel-card wide-channel" method="post" action="<?= e(route('/admin/notification-channels')) ?>" <?= $channel['code'] === 'whatsapp' ? 'data-whatsapp-wizard' : '' ?>>
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
            <?php
              $apiVersion = (string) ($config['api_version'] ?? 'v25.0');
              $phoneNumberId = (string) ($config['phone_number_id'] ?? '');
              $businessId = (string) ($config['business_id'] ?? '');
              $metaConnection = is_array($config['meta_connection'] ?? null) ? $config['meta_connection'] : [];
              $discoveredPhones = is_array($config['discovered_phone_numbers'] ?? null) ? $config['discovered_phone_numbers'] : [];
              $isMetaConnected = ($metaConnection['status'] ?? '') === 'connected';
              $connectedAt = strtotime((string) ($metaConnection['connected_at'] ?? ''));
              $connectedAtText = $connectedAt ? date('d.m.Y H:i', $connectedAt) : '-';
              $endpointPreview = $apiVersion !== '' && $phoneNumberId !== ''
                  ? 'https://graph.facebook.com/' . $apiVersion . '/' . $phoneNumberId . '/messages'
                  : ($businessId !== '' ? 'Meta WABA üzerinden otomatik bulunacak' : 'https://graph.facebook.com/v25.0/{PHONE_NUMBER_ID}/messages');
            ?>
            <section class="whatsapp-wizard" aria-label="WhatsApp kurulum sihirbazı">
              <div class="wizard-title-row">
                <div>
                  <p class="eyebrow">Sihirbaz</p>
                  <h3>WhatsApp Cloud API Kurulumu</h3>
                </div>
                <span class="wizard-status <?= $isConfigured ? 'ok' : 'pending' ?>" data-whatsapp-status>
                  <?= $isConfigured ? 'Hazır' : 'Bekliyor' ?>
                </span>
              </div>

              <ol class="wizard-steps">
                <li>
                  <strong>Meta erişimini gir</strong>
                  <small>Kalıcı Access Token ile WhatsApp Business Account ID yazın. Phone Number ID bilinmiyorsa sistem Meta'dan otomatik bulur.</small>
                </li>
                <li>
                  <strong>Entegrasyonu tamamla</strong>
                  <small>Butona basınca sistem Meta Graph API'ye bağlanır, bağlı numaraları okur ve doğru Phone Number ID değerini kaydeder.</small>
                </li>
                <li>
                  <strong>Sonucu gör ve test et</strong>
                  <small>Bağlantı sonucu ekranda kalıcı görünür; ardından gerçek WhatsApp numarasına test mesajı gönderilebilir.</small>
                </li>
              </ol>

              <div class="endpoint-preview">
                <span>Otomatik endpoint</span>
                <code data-whatsapp-endpoint-preview><?= e($endpointPreview) ?></code>
              </div>

              <?php if ($isMetaConnected): ?>
                <div class="integration-result ok">
                  <div class="integration-result-head">
                    <strong>Meta bağlantısı tamamlandı</strong>
                    <span><?= e($connectedAtText) ?></span>
                  </div>
                  <dl>
                    <div>
                      <dt>Doğrulanmış ad</dt>
                      <dd><?= e($metaConnection['verified_name'] ?? '-') ?></dd>
                    </div>
                    <div>
                      <dt>Gönderici numara</dt>
                      <dd><?= e($metaConnection['display_phone_number'] ?? '-') ?></dd>
                    </div>
                    <div>
                      <dt>Phone Number ID</dt>
                      <dd><?= e($metaConnection['phone_number_id'] ?? '-') ?></dd>
                    </div>
                    <div>
                      <dt>Kalite</dt>
                      <dd><?= e($metaConnection['quality_rating'] ?? '-') ?></dd>
                    </div>
                    <div>
                      <dt>Platform</dt>
                      <dd><?= e($metaConnection['platform_type'] ?? '-') ?></dd>
                    </div>
                    <div>
                      <dt>Kaynak</dt>
                      <dd><?= e(($metaConnection['source'] ?? '') === 'business_phone_numbers' ? 'WABA numara listesi (' . count($discoveredPhones) . ')' : 'Phone Number ID doğrulama') ?></dd>
                    </div>
                  </dl>
                </div>
              <?php endif; ?>
            </section>

            <label>
              Sağlayıcı
              <select name="provider" data-whatsapp-provider>
                <option value="meta_cloud" <?= ($config['provider'] ?? 'meta_cloud') === 'meta_cloud' ? 'selected' : '' ?>>Meta Cloud API</option>
                <option value="custom_http" <?= ($config['provider'] ?? '') === 'custom_http' ? 'selected' : '' ?>>Özel HTTP API</option>
              </select>
            </label>

            <div class="split-fields">
              <label>
                API versiyonu
                <input name="api_version" value="<?= e($config['api_version'] ?? 'v25.0') ?>" placeholder="v25.0" data-whatsapp-api-version>
              </label>
              <label>
                Phone Number ID
                <input name="phone_number_id" value="<?= e($config['phone_number_id'] ?? '') ?>" placeholder="123456789012345" data-whatsapp-phone-id>
              </label>
            </div>

            <div class="split-fields">
              <label>
                WhatsApp Business Account ID
                <input name="business_id" value="<?= e($businessId) ?>" placeholder="Meta WABA ID" data-whatsapp-business-id>
              </label>
              <label>
                Gönderici telefon
                <input name="display_phone_number" value="<?= e($config['display_phone_number'] ?? '') ?>" placeholder="+90 5xx xxx xx xx" data-whatsapp-phone-format>
              </label>
            </div>

            <label>
              Access token
              <input name="access_token" type="password" placeholder="<?= !empty($config['access_token']) ? 'Kayıtlı - değiştirmek için yazın' : 'EAAG...' ?>" data-whatsapp-token data-token-saved="<?= !empty($config['access_token']) ? '1' : '0' ?>">
            </label>

            <label data-custom-endpoint-row>
              Özel endpoint
              <input name="endpoint" value="<?= e($config['endpoint'] ?? '') ?>" placeholder="Örn. https://api.saglayici.com/send" data-whatsapp-endpoint>
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
            <?php if ($channel['code'] === 'whatsapp'): ?>
              <button class="primary-action" type="submit" name="action" value="complete_whatsapp_integration">Entegrasyonu Tamamla</button>
              <button class="ghost-link" type="submit" name="action" value="save_whatsapp_wizard">Elle Kaydet ve Aktifleştir</button>
              <button class="ghost-link" type="submit" name="action" value="validate_whatsapp">Bağlantıyı Doğrula</button>
            <?php endif; ?>
            <button class="dark-button" type="submit" name="action" value="test">Test Gönder</button>
          </div>
        </form>
      <?php endforeach; ?>
    </div>
  </section>
</section>
