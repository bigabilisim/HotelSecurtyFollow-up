<?php

$recentActivity = $recentActivity ?? [];
$activityLabels = $activityLabels ?? [];
$maskName = $maskName ?? static fn (?string $value): string => trim((string) $value);
?>
<div class="feed-list">
  <?php if (!$recentActivity): ?>
    <article class="feed-item">
      <span class="feed-dot muted"></span>
      <div>
        <time>Henüz hareket yok</time>
        <p>Giriş, çıkış ve süre hareketleri burada görünecek.</p>
      </div>
    </article>
  <?php endif; ?>

  <?php foreach ($recentActivity as $index => $activity): ?>
    <?php
      $activityState = $activityLabels[$activity['event_type']] ?? ['Hareket', 'muted'];
      $createdAt = strtotime((string) $activity['created_at']);
      $meta = trim(($activity['category_name'] ?? '') . ' · ' . ($activity['department_name'] ?? '-'), " ·");
    ?>
    <article class="feed-item <?= $index === 0 ? 'is-live' : '' ?>">
      <span class="feed-dot <?= e($activityState[1]) ?>"></span>
      <div>
        <time data-relative-time data-created-at="<?= e($createdAt ? date(DATE_ATOM, $createdAt) : '') ?>">
          <?= e($createdAt ? date('H:i', $createdAt) : '-') ?>
        </time>
        <p>
          <strong><?= e($maskName($activity['visitor_name'])) ?></strong>
          <?= e($activityState[0]) ?>
        </p>
        <small><?= e($activity['event_note'] ?: $meta) ?></small>
      </div>
    </article>
  <?php endforeach; ?>
</div>
