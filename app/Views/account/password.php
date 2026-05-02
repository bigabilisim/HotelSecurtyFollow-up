<?php

use App\Core\Csrf;
?>
<section class="auth-wrap">
  <form class="auth-card" method="post" action="<?= e(route('/account/password')) ?>">
    <?= Csrf::field() ?>
    <p class="eyebrow">Hesap güvenliği</p>
    <h1>Şifre Değiştir</h1>
    <p class="muted">Şifrenizi düzenli aralıklarla değiştirmeniz ve kimseyle paylaşmamanız önerilir.</p>

    <label>
      Mevcut şifre
      <input name="current_password" type="password" autocomplete="current-password" required autofocus>
    </label>

    <label>
      Yeni şifre
      <input name="new_password" type="password" autocomplete="new-password" minlength="6" required>
    </label>

    <label>
      Yeni şifre tekrar
      <input name="new_password_again" type="password" autocomplete="new-password" minlength="6" required>
    </label>

    <button class="primary-action" type="submit">Şifreyi Güncelle</button>
    <a class="ghost-link" href="<?= e(route('/dashboard')) ?>">Canlı panele dön</a>
  </form>
</section>
