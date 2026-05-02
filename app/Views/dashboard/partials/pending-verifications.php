<?php

use App\Core\Csrf;

$pendingVerifications = $pendingVerifications ?? [];
?>
<?php if ($pendingVerifications): ?>
  <section class="panel-card verification-panel">
    <div class="section-head">
      <div>
        <p class="eyebrow">Departman onayı</p>
        <h1>Bekleyen Sorular</h1>
      </div>
    </div>

    <div class="verification-list">
      <?php foreach ($pendingVerifications as $verification): ?>
        <article class="verification-item">
          <div>
            <strong><?= e($verification['full_name']) ?></strong>
            <small>
              <?= e($verification['category_name']) ?> ·
              <?= e($verification['department_name'] ?? '-') ?> ·
              <?= (int) ($verification['escalation_level'] ?? 0) > 0 ? 'N+' . e((int) $verification['escalation_level']) . ' · ' : '' ?>
              <?= e((int) $verification['elapsed_minutes']) ?> dk içeride
            </small>
            <p><?= e($verification['question_text']) ?></p>
          </div>
          <div class="verification-actions">
            <form method="post" action="<?= e(route('/verifications/answer')) ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="verification_id" value="<?= e($verification['id']) ?>">
              <input type="hidden" name="answer" value="yes">
              <button class="primary-action" type="submit">Evet</button>
            </form>
            <form method="post" action="<?= e(route('/verifications/answer')) ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="verification_id" value="<?= e($verification['id']) ?>">
              <input type="hidden" name="answer" value="no">
              <button class="danger-button" type="submit">Hayır</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
