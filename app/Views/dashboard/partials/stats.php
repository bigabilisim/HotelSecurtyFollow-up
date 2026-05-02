<?php

$stats = $stats ?? [];
?>
<section class="metric-strip">
  <article class="metric">
    <span>Bugün Giriş</span>
    <strong><?= e($stats['today_entries'] ?? 0) ?></strong>
  </article>
  <article class="metric">
    <span>İçeride</span>
    <strong><?= e($stats['inside'] ?? 0) ?></strong>
  </article>
  <article class="metric danger">
    <span>Süre Aşımı</span>
    <strong><?= e($stats['overdue'] ?? 0) ?></strong>
  </article>
  <article class="metric">
    <span>Bildirim</span>
    <strong><?= e($stats['notifications_today'] ?? 0) ?></strong>
  </article>
</section>
