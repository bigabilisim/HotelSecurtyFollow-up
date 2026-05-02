<?php

use App\Core\Csrf;

$rules = $rules ?? [];
$channels = $channels ?? [];
$categories = $categories ?? [];
$departments = $departments ?? [];
$roles = $roles ?? [];
$editingRule = $editingRule ?? null;
$isEditing = is_array($editingRule);
$selectedChannelIds = array_map('intval', $editingRule['channel_ids'] ?? []);
$recipientType = $editingRule['recipient_type'] ?? 'custom';

$eventTypes = [
    'entry' => 'Giriş',
    'exit' => 'Çıkış',
    'timeout_warning' => 'Süre Uyarısı',
    'department_question' => 'Departman Sorusu',
    'department_answer_no' => 'Departman Hayır',
    'department_no_response' => 'Cevap Yok',
    'daily_report' => 'Gün Sonu Raporu',
    'weekly_report' => 'Haftalık Rapor',
    'monthly_report' => 'Ay Sonu Raporu',
];
?>
<section class="admin-layout">
  <form class="panel-card" method="post" action="<?= e(route('/admin/notification-rules')) ?>">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= e($editingRule['id'] ?? '') ?>">
    <p class="eyebrow">Kural</p>
    <h1><?= $isEditing ? 'Bildirim Kuralı Düzenle' : 'Bildirim Kuralı Ekle' ?></h1>
    <?php if ($isEditing): ?>
      <p class="muted">Seçili kayıt: <?= e($editingRule['name']) ?></p>
    <?php endif; ?>

    <label>
      Kural adı
      <input name="name" placeholder="Örn. VIP giriş bildirimi" value="<?= e($editingRule['name'] ?? '') ?>" required>
    </label>

    <div class="split-fields">
      <label>
        Olay
        <select name="event_type">
          <?php foreach ($eventTypes as $value => $label): ?>
            <option value="<?= e($value) ?>" <?= ($editingRule['event_type'] ?? 'entry') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Kategori
        <select name="category_id">
          <option value="">Tüm kategoriler</option>
          <?php foreach ($categories as $category): ?>
            <option value="<?= e($category['id']) ?>" <?= (int) ($editingRule['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <label>
      Öncelik
      <input name="priority" type="number" min="1" value="<?= e($editingRule['priority'] ?? 100) ?>">
    </label>

    <fieldset class="check-list">
      <legend>Kanallar</legend>
      <?php foreach ($channels as $channel): ?>
        <label class="switch-line">
          <input name="channel_ids[]" type="checkbox" value="<?= e($channel['id']) ?>" <?= in_array((int) $channel['id'], $selectedChannelIds, true) ? 'checked' : '' ?>>
          <?= e($channel['name']) ?>
        </label>
      <?php endforeach; ?>
    </fieldset>

    <label>
      Alıcı tipi
      <select name="recipient_type">
        <option value="custom" <?= $recipientType === 'custom' ? 'selected' : '' ?>>Özel alıcı</option>
        <option value="department_manager" <?= $recipientType === 'department_manager' ? 'selected' : '' ?>>Departman amiri</option>
        <option value="role" <?= $recipientType === 'role' ? 'selected' : '' ?>>Rol grubu</option>
      </select>
    </label>

    <div class="split-fields">
      <label>
        Alıcı departman
        <select name="recipient_department_id">
          <option value="">Seçiniz</option>
          <?php foreach ($departments as $department): ?>
            <option value="<?= e($department['id']) ?>" <?= (int) ($editingRule['recipient_department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>
        Alıcı rol
        <select name="recipient_role_id">
          <option value="">Seçiniz</option>
          <?php foreach ($roles as $role): ?>
            <option value="<?= e($role['id']) ?>" <?= (int) ($editingRule['recipient_role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <div class="split-fields">
      <label>
        Özel alıcı adı
        <input name="custom_name" placeholder="Patron / Genel Müdür" value="<?= e($editingRule['custom_name'] ?? '') ?>">
      </label>
      <label>
        Mail
        <input name="custom_email" type="email" value="<?= e($editingRule['custom_email'] ?? '') ?>">
      </label>
    </div>

    <div class="split-fields">
      <label>
        Telefon
        <input name="custom_phone" value="<?= e($editingRule['custom_phone'] ?? '') ?>">
      </label>
      <label>
        Telegram Chat ID
        <input name="custom_telegram_chat_id" value="<?= e($editingRule['custom_telegram_chat_id'] ?? '') ?>">
      </label>
    </div>

    <label>
      WhatsApp numarası
      <input name="custom_whatsapp_number" value="<?= e($editingRule['custom_whatsapp_number'] ?? '') ?>">
    </label>

    <label>
      Mesaj şablonu
      <textarea name="message_template" rows="4" placeholder="{visitor_name} giriş yaptı. Departman: {department_name}."><?= e($editingRule['message_template'] ?? '') ?></textarea>
    </label>

    <label class="switch-line">
      <input name="is_active" type="checkbox" <?= (bool) ($editingRule['is_active'] ?? true) ? 'checked' : '' ?>>
      Kural aktif
    </label>

    <div class="form-actions">
      <button class="primary-action" type="submit"><?= $isEditing ? 'Kural Güncelle' : 'Kural Kaydet' ?></button>
      <?php if ($isEditing): ?>
        <a class="ghost-link" href="<?= e(route('/admin/notification-rules')) ?>">Yeni Kayıt</a>
      <?php endif; ?>
    </div>
  </form>

  <section class="panel-card wide">
    <div class="section-head">
      <div>
        <p class="eyebrow">Liste</p>
        <h1>Bildirim Kuralları</h1>
      </div>
    </div>

    <div class="admin-table">
      <div class="admin-head six">
        <span>Kural</span>
        <span>Olay</span>
        <span>Kategori</span>
        <span>Kanal / Alıcı</span>
        <span>Durum</span>
        <span>İşlem</span>
      </div>

      <?php foreach ($rules as $rule): ?>
        <article class="admin-row six">
          <span>
            <strong><?= e($rule['name']) ?></strong>
            <small>Öncelik: <?= e($rule['priority']) ?></small>
          </span>
          <span><?= e($eventTypes[$rule['event_type']] ?? $rule['event_type']) ?></span>
          <span><?= e($rule['category_name'] ?? 'Tüm kategoriler') ?></span>
          <span>
            <strong><?= e($rule['channel_names'] ?? '-') ?></strong>
            <small><?= e($rule['recipient_names'] ?? '-') ?></small>
          </span>
          <span class="state-chip <?= $rule['is_active'] ? 'ok' : 'muted' ?>"><?= $rule['is_active'] ? 'Aktif' : 'Pasif' ?></span>
          <span class="row-actions">
            <a class="dark-button small-action" href="<?= e(route('/admin/notification-rules', ['edit_id' => $rule['id']])) ?>">Düzenle</a>
            <form method="post" action="<?= e(route('/admin/notification-rules')) ?>" onsubmit="return confirm('Bu bildirim kuralını silmek istiyor musunuz?');">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= e($rule['id']) ?>">
              <button class="danger-button small-action" type="submit">Sil</button>
            </form>
          </span>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</section>
