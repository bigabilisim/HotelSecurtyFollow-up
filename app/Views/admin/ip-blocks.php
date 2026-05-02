<?php

use App\Core\Csrf;

$blocks = $blocks ?? [];

$formatDate = static function (?string $value): string {
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d.m.Y H:i', $timestamp) : '-';
};
?>
<section class="admin-layout single">
  <section class="panel-card wide">
    <div class="section-head">
      <div>
        <p class="eyebrow">Giriş Güvenliği</p>
        <h1>IP Blokları</h1>
        <p class="muted">İlk seride 5 hatalı şifre denemesi IP adresini 1 saat banlar. Süre dolunca otomatik düşer. Aynı IP ikinci seride 3 hatalı denemeye ulaşırsa kalıcı bloklanır ve sadece buradan açılır.</p>
      </div>
    </div>

    <div class="admin-table">
      <div class="admin-head seven">
        <span>IP</span>
        <span>Durum</span>
        <span>Deneme</span>
        <span>Son Kullanıcı</span>
        <span>Son Deneme</span>
        <span>Bitiş</span>
        <span>İşlem</span>
      </div>

      <?php foreach ($blocks as $block): ?>
        <article class="admin-row seven">
          <span>
            <strong><?= e($block['ip_address']) ?></strong>
            <small><?= e($block['last_user_agent'] ?: '-') ?></small>
          </span>
          <span class="state-chip <?= e($block['status_class']) ?>"><?= e($block['status_label']) ?></span>
          <span>
            <strong><?= e((int) $block['failed_attempt_count']) ?> / <?= e((int) $block['total_failed_attempt_count']) ?></strong>
            <small>Ban sayısı: <?= e((int) $block['ban_count']) ?></small>
          </span>
          <span>
            <strong><?= e($block['last_username'] ?: '-') ?></strong>
            <small><?= e($block['last_block_reason'] ?: '-') ?></small>
          </span>
          <span><?= e($formatDate($block['last_failed_at'] ?? null)) ?></span>
          <span>
            <?php if ($block['status'] === 'temporary'): ?>
              <strong><?= e($formatDate($block['blocked_until'] ?? null)) ?></strong>
              <small><?= e(max(0, (int) $block['remaining_minutes'])) ?> dk kaldı</small>
            <?php elseif ($block['status'] === 'permanent'): ?>
              <strong>Süresiz</strong>
              <small>Admin açmalı</small>
            <?php else: ?>
              <strong>Açık</strong>
              <small><?= e($block['release_note'] ?: 'Blok yok') ?></small>
            <?php endif; ?>
          </span>
          <span class="row-actions">
            <?php if (!empty($block['is_active_block'])): ?>
              <form method="post" action="<?= e(route('/admin/ip-blocks')) ?>" onsubmit="return confirm('Bu IP bloğunu kaldırmak istiyor musunuz?');">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= e($block['id']) ?>">
                <input type="hidden" name="release_note" value="Admin panelinden manuel açıldı.">
                <button class="primary-action small-action" type="submit">Banı Kaldır</button>
              </form>
            <?php else: ?>
              <span class="state-chip ok">Açık</span>
            <?php endif; ?>
          </span>
        </article>
      <?php endforeach; ?>

      <?php if (!$blocks): ?>
        <article class="empty-state">Henüz hatalı şifre IP kaydı yok.</article>
      <?php endif; ?>
    </div>
  </section>
</section>
