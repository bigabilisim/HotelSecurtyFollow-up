<?php

use App\Core\Csrf;

$reset = $reset ?? null;
$token = $token ?? '';
?>
<section class="auth-wrap">
  <div class="auth-card">
    <p class="eyebrow">Hesap yardımı</p>
    <h1>Yeni Şifre Belirle</h1>

    <?php if ($reset): ?>
      <p class="muted">Yeni şifrenizi yazın. Bu bağlantı tek kullanımlıktır.</p>

      <form class="auth-inner-form" method="post" action="<?= e(route('/password/reset')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <label>
          Yeni şifre
          <input name="password" type="password" autocomplete="new-password" required autofocus minlength="6">
        </label>

        <label>
          Yeni şifre tekrar
          <input name="password_confirmation" type="password" autocomplete="new-password" required minlength="6">
        </label>

        <button class="primary-action" type="submit">Şifreyi Güncelle</button>
      </form>
    <?php else: ?>
      <p class="muted">Şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş.</p>
      <a class="primary-action auth-guide-button" href="<?= e(route('/password/forgot')) ?>">Yeni Link İste</a>
    <?php endif; ?>

    <a class="dark-button auth-guide-button" href="<?= e(route('/login')) ?>">Giriş ekranına dön</a>
  </div>
</section>
