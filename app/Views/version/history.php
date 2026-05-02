<?php

$historyText = $historyText ?? '';
$backRoute = (isset($_SESSION['user_id']) && $_SESSION['user_id']) ? '/dashboard' : '/login';
$backLabel = (isset($_SESSION['user_id']) && $_SESSION['user_id']) ? 'Canlı panele dön' : 'Giriş ekranına dön';
?>
<section class="version-history-page">
  <div class="panel-card wide">
    <div class="section-head">
      <div>
        <p class="eyebrow">Sürüm</p>
        <h1>Sürüm Geçmişi</h1>
        <p class="muted">Tüm sürüm notları düz metin olarak aşağıda yer alır.</p>
      </div>
      <a class="dark-button" href="<?= e(route($backRoute)) ?>"><?= e($backLabel) ?></a>
    </div>

    <pre class="version-history-text"><?= e($historyText) ?></pre>
  </div>
</section>
