<?php

use App\Core\Csrf;

$stats = $stats ?? [];
$insideVisits = $insideVisits ?? [];
$recentActivity = $recentActivity ?? [];
$categories = $categories ?? [];
$quickCategories = $quickCategories ?? [];
$departments = $departments ?? [];
$visitorSuggestions = $visitorSuggestions ?? [];
$pendingVerifications = $pendingVerifications ?? [];
$statusLabels = $statusLabels ?? [];
$activityLabels = $activityLabels ?? [];
$maskName = $maskName ?? static fn (?string $value): string => trim((string) $value);
$liveSignature = (string) ($liveSignature ?? '');
?>
<div data-live-stats>
  <?php require BASE_PATH . '/app/Views/dashboard/partials/stats.php'; ?>
</div>

<div data-live-verifications>
  <?php require BASE_PATH . '/app/Views/dashboard/partials/pending-verifications.php'; ?>
</div>

<section
  class="workspace live-dashboard"
  data-dashboard-heartbeat
  data-heartbeat-url="<?= e(route('/dashboard/heartbeat')) ?>"
  data-dashboard-signature="<?= e($liveSignature) ?>"
  data-csrf="<?= e(Csrf::token()) ?>"
>
  <aside class="panel-card">
    <p class="eyebrow">Kapı işlemi</p>
    <h1>Hızlı Giriş</h1>

    <form class="entry-form" method="post" action="<?= e(route('/visits/entry')) ?>">
      <?= Csrf::field() ?>

      <?php if ($quickCategories): ?>
        <div class="quick-category-grid" aria-label="Hızlı kategori seçimi">
          <?php foreach ($quickCategories as $category): ?>
            <button
              class="quick-category-card"
              type="button"
              data-quick-category-id="<?= e($category['id']) ?>"
              style="--category-color: <?= e($category['color']) ?>"
            >
              <span><?= e($category['name']) ?></span>
              <small><?= $category['max_duration_minutes'] ? e($category['max_duration_minutes']) . ' dk' : 'Süre yok' ?></small>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <label>
        Kategori
        <select name="category_id" data-category-select required>
          <option value="">Seçiniz</option>
          <?php foreach ($categories as $category): ?>
            <option value="<?= e($category['id']) ?>">
              <?= e($category['name']) ?>
              <?= $category['max_duration_minutes'] ? ' - ' . e($category['max_duration_minutes']) . ' dk' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="switch-line">
        <input name="has_appointment" type="checkbox">
        Randevulu giriş
      </label>

      <label>
        Ad Soyad
        <input
          name="full_name"
          list="visitor-suggestions"
          data-visitor-name
          autocomplete="off"
          placeholder="Örn. Ahmet Yılmaz"
          required
        >
        <datalist id="visitor-suggestions">
          <?php foreach ($visitorSuggestions as $visitor): ?>
            <?php $label = trim(($visitor['company'] ?? '') . ' ' . ($visitor['vehicle_plate'] ? '| ' . $visitor['vehicle_plate'] : '')); ?>
            <option value="<?= e($visitor['full_name']) ?>" label="<?= e($label) ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <span class="autofill-status" data-autofill-status aria-live="polite"></span>
      </label>

      <div class="split-fields">
        <label>
          Telefon
          <input name="phone" placeholder="05xx xxx xx xx">
        </label>
        <label>
          Plaka
          <input
            name="vehicle_plate"
            data-plate-format
            autocomplete="off"
            autocapitalize="characters"
            spellcheck="false"
            maxlength="11"
            placeholder="34 ABC 123"
          >
        </label>
      </div>

      <label>
        Firma / Kurum
        <input name="company" placeholder="Firma adı">
      </label>

      <label>
        Geldiği Departman
        <select name="department_id">
          <option value="">Seçiniz</option>
          <?php foreach ($departments as $department): ?>
            <option value="<?= e($department['id']) ?>"><?= e($department['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        Görüşeceği kişi
        <input name="host_name" placeholder="Ad soyad">
      </label>

      <label>
        Amaç / Not
        <textarea name="note" rows="3"></textarea>
      </label>

      <input type="hidden" name="purpose" value="Ziyaret">
      <button class="primary-action" type="submit">Giriş Ver</button>
    </form>
  </aside>

  <section class="panel-card wide">
    <div class="section-head">
      <div>
        <p class="eyebrow">Anlık durum</p>
        <h1>İçeride Olanlar</h1>
      </div>
    </div>

    <div data-live-inside>
      <?php require BASE_PATH . '/app/Views/dashboard/partials/inside-visits.php'; ?>
    </div>
  </section>

  <aside class="panel-card activity-panel">
    <div class="section-head compact">
      <div>
        <p class="eyebrow">Canlı akış</p>
        <h1>Hareketler</h1>
      </div>
      <span class="live-indicator"><i></i>Canlı</span>
    </div>

    <div data-live-activity>
      <?php require BASE_PATH . '/app/Views/dashboard/partials/recent-activity.php'; ?>
    </div>
  </aside>
</section>

<script type="application/json" id="visitor-suggestion-data"><?= json_encode($visitorSuggestions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script defer src="/assets/dashboard.js?v=<?= e(is_file(BASE_PATH . '/public/assets/dashboard.js') ? (string) filemtime(BASE_PATH . '/public/assets/dashboard.js') : '1') ?>"></script>
