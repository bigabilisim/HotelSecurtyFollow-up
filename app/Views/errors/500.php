<section class="auth-wrap">
  <article class="auth-card">
    <p class="eyebrow">Sistem hatası</p>
    <h1>İstek işlenemedi</h1>
    <p class="muted"><?= e($message ?? 'Sistem şu anda isteği işleyemedi.') ?></p>
    <a class="primary-link" href="<?= e(route('/dashboard')) ?>">Panele dön</a>
  </article>
</section>
