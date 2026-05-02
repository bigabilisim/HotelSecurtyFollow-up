<?php

$ok = (bool) ($ok ?? false);
$message = $message ?? '';
?>
<section class="auth-wrap">
  <div class="auth-card">
    <p class="eyebrow"><?= $ok ? 'Doğrulama' : 'Uyarı' ?></p>
    <h1><?= $ok ? 'Cevap Kaydedildi' : 'Cevap Kaydedilemedi' ?></h1>
    <p class="muted"><?= e($message) ?></p>
    <a class="primary-link" href="<?= e(route('/login')) ?>">Panele Git</a>
  </div>
</section>
