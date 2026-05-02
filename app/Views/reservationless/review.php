<?php

use App\Core\Csrf;

$review = $review ?? null;
$result = $result ?? null;
$token = (string) ($token ?? '');
?>
<section class="auth-wrap reservationless-review-wrap">
  <div class="auth-card reservationless-review-card">
    <p class="eyebrow">Rezervasyonsuz giriş</p>

    <?php if (!$review): ?>
      <h1>Bağlantı Geçersiz</h1>
      <p class="muted"><?= e($result['message'] ?? 'Bu form bağlantısı geçersiz veya kayıt bulunamadı.') ?></p>
      <a class="primary-link" href="<?= e(route('/login')) ?>">Panele Git</a>
    <?php else: ?>
      <h1>Oda ve Departman Notu</h1>

      <?php if ($result): ?>
        <div class="flash <?= !empty($result['ok']) ? 'success' : 'error' ?>"><?= e($result['message']) ?></div>
      <?php endif; ?>

      <dl class="review-summary">
        <div>
          <dt>Ziyaretçi</dt>
          <dd><?= e($review['visitor_name']) ?></dd>
        </div>
        <div>
          <dt>Departman</dt>
          <dd><?= e($review['department_name'] ?? '-') ?></dd>
        </div>
        <div>
          <dt>Telefon / Firma</dt>
          <dd><?= e(trim(($review['visitor_phone'] ?? '') . ' ' . (($review['visitor_company'] ?? '') ? '| ' . $review['visitor_company'] : '')) ?: '-') ?></dd>
        </div>
        <div>
          <dt>Görüşeceği kişi</dt>
          <dd><?= e($review['host_name'] ?: '-') ?></dd>
        </div>
      </dl>

      <?php if (($review['status'] ?? '') === 'submitted'): ?>
        <div class="submitted-note">
          <strong>Oda bilgisi kaydedildi.</strong>
          <span>Oda: <?= e($review['room_number'] ?? '-') ?></span>
          <span>Not: <?= e($review['manager_note'] ?? '-') ?></span>
        </div>
      <?php else: ?>
        <form class="auth-inner-form" method="post" action="<?= e(route('/reservationless/review')) ?>">
          <?= Csrf::field() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">

          <label>
            Verilen oda
            <input name="room_number" required maxlength="80" placeholder="Örn. 1204">
          </label>

          <label>
            Departman notu
            <textarea name="manager_note" rows="5" required placeholder="Oda verilme nedeni, özel notlar veya takip edilmesi gereken konu"></textarea>
          </label>

          <button class="primary-action" type="submit">Kaydet ve Yöneticilere Gönder</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
