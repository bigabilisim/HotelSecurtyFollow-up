<?php

use App\Core\Csrf;
?>
<section class="auth-wrap">
  <form class="auth-card" method="post" action="<?= e(route('/password/forgot')) ?>">
    <?= Csrf::field() ?>
    <p class="eyebrow">Hesap yardımı</p>
    <h1 class="auth-title-with-icon">
      <svg class="fish-icon title-fish-icon" aria-hidden="true" viewBox="0 0 24 24" focusable="false">
        <path d="M3.5 12s3.2-5 8.3-5c3.1 0 5.2 1.8 7 5-1.8 3.2-3.9 5-7 5-5.1 0-8.3-5-8.3-5Z"></path>
        <path d="M18.8 12 22 8.8v6.4L18.8 12Z"></path>
        <path d="M10.8 8.2c1.1 1.1 1.1 6.5 0 7.6"></path>
        <circle cx="8.1" cy="10.6" r="0.6"></circle>
      </svg>
      <span>Şifremi Unuttum</span>
    </h1>
    <p class="muted">Kayıtlı e-posta adresinizi yazın. Hesabınız aktifse sıfırlama bağlantısı mail olarak gönderilir.</p>

    <label>
      E-posta adresi
      <input name="email" type="email" autocomplete="email" required autofocus placeholder="mail@otel.com">
    </label>

    <button class="primary-action" type="submit">Sıfırlama Linki Gönder</button>
    <a class="dark-button auth-guide-button" href="<?= e(route('/login')) ?>">Giriş ekranına dön</a>
  </form>
</section>
