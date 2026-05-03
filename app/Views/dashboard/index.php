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
$dashboardBlockAccess = $dashboardBlockAccess ?? [
    'door' => true,
    'inside' => true,
    'activity' => true,
    'stats' => true,
    'verifications' => true,
];
$canCustomizeDashboard = (bool) ($canCustomizeDashboard ?? true);
$visibleDashboardBlocks = array_filter($dashboardBlockAccess, static fn ($allowed): bool => (bool) $allowed);
?>

<section class="dashboard-page">
<?php if ($canCustomizeDashboard): ?>
<section
  class="dashboard-view-controls"
  id="dashboard-view-controls"
  data-dashboard-view-panel
  aria-label="Canlı panel bölümleri"
  hidden
>
  <div>
    <p class="eyebrow">Ekran Bölümleri</p>
    <h1>Görünüm Ayarı</h1>
  </div>
  <div class="dashboard-view-control-body">
    <div class="dashboard-zoom-panel" data-dashboard-zoom-controls aria-label="Dashboard ekran ölçeği">
      <button class="template-toggle zoom-button" type="button" data-dashboard-zoom="out" aria-label="Ekranı küçült">-</button>
      <output class="dashboard-zoom-value" data-dashboard-zoom-value>100%</output>
      <button class="template-toggle zoom-button" type="button" data-dashboard-zoom="in" aria-label="Ekranı büyüt">+</button>
      <button class="template-reset" type="button" data-dashboard-zoom="reset">100%</button>
    </div>
    <div class="template-toggle-panel" data-dashboard-template-controls aria-label="Dashboard şablonları">
      <button class="template-toggle" type="button" data-dashboard-template="operations" aria-pressed="true">Operasyon</button>
      <button class="template-toggle" type="button" data-dashboard-template="monitoring" aria-pressed="false">İzleme</button>
      <button class="template-toggle" type="button" data-dashboard-template="compact" aria-pressed="false">Kompakt</button>
      <button class="template-reset" type="button" data-dashboard-layout-reset>Sıfırla</button>
    </div>
    <div class="block-toggle-panel" data-dashboard-block-controls>
      <?php if (!empty($dashboardBlockAccess['door'])): ?>
        <button class="column-toggle" type="button" data-dashboard-block-toggle="door" aria-pressed="true">
          <span>Kapı İşlemi</span><i data-block-icon aria-hidden="true">x</i>
        </button>
      <?php endif; ?>
      <?php if (!empty($dashboardBlockAccess['inside'])): ?>
        <button class="column-toggle" type="button" data-dashboard-block-toggle="inside" aria-pressed="true">
          <span>İçeride Olanlar</span><i data-block-icon aria-hidden="true">x</i>
        </button>
      <?php endif; ?>
      <?php if (!empty($dashboardBlockAccess['activity'])): ?>
        <button class="column-toggle" type="button" data-dashboard-block-toggle="activity" aria-pressed="true">
          <span>Canlı Akış</span><i data-block-icon aria-hidden="true">x</i>
        </button>
      <?php endif; ?>
      <?php if (!empty($dashboardBlockAccess['stats'])): ?>
        <button class="column-toggle" type="button" data-dashboard-block-toggle="stats" aria-pressed="true">
          <span>Özet Kartları</span><i data-block-icon aria-hidden="true">x</i>
        </button>
      <?php endif; ?>
      <?php if (!empty($dashboardBlockAccess['verifications'])): ?>
        <button class="column-toggle" type="button" data-dashboard-block-toggle="verifications" aria-pressed="true">
          <span>Departman Onayı</span><i data-block-icon aria-hidden="true">x</i>
        </button>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section
  class="dashboard-board live-dashboard"
  data-dashboard-heartbeat
  data-dashboard-board
  data-dashboard-template="operations"
  data-heartbeat-url="<?= e(route('/dashboard/heartbeat')) ?>"
  data-dashboard-signature="<?= e($liveSignature) ?>"
  data-csrf="<?= e(Csrf::token()) ?>"
>
  <?php if (!$visibleDashboardBlocks): ?>
    <section class="panel-card wide">
      <p class="eyebrow">Yetki</p>
      <h1>Panel bölümü açık değil</h1>
      <p class="muted">Bu kullanıcı için Canlı Panel açık, ancak görüntülenecek panel bölümü seçilmemiş. Yönetim > Kullanıcılar ekranından Panel Bölümleri yetkileri açılmalıdır.</p>
    </section>
  <?php endif; ?>

  <?php if (!empty($dashboardBlockAccess['door'])): ?>
  <aside class="panel-card door-actions-panel" data-dashboard-block="door" data-dashboard-block-title="Kapı İşlemi">
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
  <?php endif; ?>

  <?php if (!empty($dashboardBlockAccess['inside'])): ?>
  <section class="panel-card wide inside-panel" data-dashboard-block="inside" data-dashboard-block-title="İçeride Olanlar">
    <div class="section-head inside-section-head">
      <div>
        <p class="eyebrow">Anlık durum</p>
        <h1>İçeride Olanlar</h1>
      </div>
      <div class="section-tools" data-section-filter="dashboard-inside-columns">
        <button
          class="filter-menu-toggle"
          type="button"
          data-section-filter-toggle
          aria-controls="inside-column-filter"
          aria-expanded="false"
          title="Filtreleri göster"
        >
          <span class="filter-glyph" aria-hidden="true"><i></i></span>
          <span data-filter-label>Filtre</span>
        </button>
        <div
          class="section-filter-panel"
          id="inside-column-filter"
          data-section-filter-panel
          aria-label="İçeride olanlar sütunları"
          hidden
        >
          <div class="column-toggle-panel" data-visit-column-controls>
            <button class="column-toggle" type="button" data-column-toggle="person" aria-pressed="true">
              <span>Kişi</span><i data-column-icon aria-hidden="true">x</i>
            </button>
            <button class="column-toggle" type="button" data-column-toggle="department" aria-pressed="true">
              <span>Departman</span><i data-column-icon aria-hidden="true">x</i>
            </button>
            <button class="column-toggle" type="button" data-column-toggle="duration" aria-pressed="true">
              <span>Süre</span><i data-column-icon aria-hidden="true">x</i>
            </button>
            <button class="column-toggle" type="button" data-column-toggle="status" aria-pressed="true">
              <span>Durum</span><i data-column-icon aria-hidden="true">x</i>
            </button>
            <button class="column-toggle" type="button" data-column-toggle="actions" aria-pressed="true">
              <span>İşlem</span><i data-column-icon aria-hidden="true">x</i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div data-live-inside>
      <?php require BASE_PATH . '/app/Views/dashboard/partials/inside-visits.php'; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($dashboardBlockAccess['activity'])): ?>
  <aside class="panel-card activity-panel" data-dashboard-block="activity" data-dashboard-block-title="Canlı Akış">
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
  <?php endif; ?>

  <?php if (!empty($dashboardBlockAccess['stats'])): ?>
  <div class="dashboard-stats-block" data-dashboard-block="stats" data-dashboard-block-title="Özet Kartları">
    <div data-live-stats>
      <?php require BASE_PATH . '/app/Views/dashboard/partials/stats.php'; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($dashboardBlockAccess['verifications'])): ?>
  <div class="dashboard-verifications-block" data-dashboard-block="verifications" data-dashboard-block-title="Departman Onayı">
    <div data-live-verifications>
      <?php require BASE_PATH . '/app/Views/dashboard/partials/pending-verifications.php'; ?>
    </div>
  </div>
  <?php endif; ?>
</section>
</section>

<script type="application/json" id="visitor-suggestion-data"><?= json_encode($visitorSuggestions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script defer src="/assets/dashboard.js?v=<?= e(is_file(BASE_PATH . '/public/assets/dashboard.js') ? (string) filemtime(BASE_PATH . '/public/assets/dashboard.js') : '1') ?>"></script>
