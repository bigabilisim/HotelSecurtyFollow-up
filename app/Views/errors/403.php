<?php

$permissionAdvice = $permissionAdvice ?? ['missing' => [], 'summary' => 'Bu işlem için gerekli yetki bulunmuyor.'];
$missingPermissions = $permissionAdvice['missing'] ?? [];
?>
<section class="auth-wrap">
  <article class="auth-card">
    <p class="eyebrow">403</p>
    <h1>Yetkisiz erişim</h1>
    <p class="muted"><?= e($permissionAdvice['summary'] ?? 'Bu ekran için kullanıcı rolünüzde gerekli yetki bulunmuyor.') ?></p>

    <?php if ($missingPermissions): ?>
      <div class="permission-help-list">
        <?php foreach ($missingPermissions as $permission): ?>
          <article class="permission-help-card">
            <strong><?= e($permission['label']) ?></strong>
            <small><?= e($permission['description']) ?></small>
            <p><?= e($permission['grant_hint']) ?></p>
            <code><?= e($permission['code']) ?></code>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="permission-help-note">Yönetim > Kullanıcı ve Yetki ekranında ilgili kullanıcı için gerekli görev yetkisini açmanız gerekir.</p>
    <?php endif; ?>

    <a class="primary-link" href="<?= e(route('/dashboard')) ?>">Panele dön</a>
  </article>
</section>
