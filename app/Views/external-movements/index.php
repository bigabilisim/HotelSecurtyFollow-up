<?php

use App\Core\Auth;
use App\Core\Csrf;

$departments = $departments ?? [];
$outsideMovements = $outsideMovements ?? [];
$recentMovements = $recentMovements ?? [];
$quickNotes = $quickNotes ?? ['Sahil'];
$quickPlates = ['07 L 5984', '07 LJT 93', '07 LMU 84'];
$canCreateExit = Auth::can('external_movements.create_exit');
$canCreateReturn = Auth::can('external_movements.create_return');
$formatDate = static function (?string $value): string {
    $time = $value ? strtotime($value) : false;

    return $time ? date('d.m.Y H:i', $time) : '-';
};
?>

<section class="external-page">
  <section class="panel-card external-entry-card">
    <div class="section-head">
      <div>
        <p class="eyebrow">Dış görev</p>
        <h1>Hızlı Çıkış</h1>
        <p class="muted">Sahil veya dış hizmete giden kişi/araç hareketini bölüm, km ve notla kaydedin.</p>
      </div>
      <span class="state-chip ok"><?= e(count($outsideMovements)) ?> dışarıda</span>
    </div>

    <?php if ($canCreateExit): ?>
      <form class="external-form" method="post" action="<?= e(route('/external-movements/exit')) ?>">
        <?= Csrf::field() ?>

        <?php if ($quickNotes): ?>
          <div class="quick-select-panel">
            <div class="quick-select-head">
              <strong>Hızlı seçim</strong>
              <small>Tek dokunuşla gideceği yer/not alanını doldurur.</small>
            </div>
            <div class="quick-note-grid" aria-label="Hızlı seçim notları">
              <?php foreach ($quickNotes as $note): ?>
                <button class="quick-note-chip" type="button" data-external-note="<?= e($note) ?>">
                  <span>Gitilecek yer</span>
                  <strong><?= e($note) ?></strong>
                </button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <label>
          Gideceği yer / not
          <input name="destination_note" data-external-note-target placeholder="Örn. Sahil">
        </label>

        <label>
          Kişi
          <input name="person_name" autocomplete="off" placeholder="Ad soyad" required>
        </label>

        <div class="quick-select-panel compact">
          <div class="quick-select-head">
            <strong>Araç hızlı seçim</strong>
            <small>Sık kullanılan aracı seçince plaka alanı otomatik dolar.</small>
          </div>
          <div class="quick-note-grid quick-plate-grid" aria-label="Araç plakası hızlı seçimleri">
            <?php foreach ($quickPlates as $plate): ?>
              <button class="quick-note-chip quick-plate-chip" type="button" data-external-plate="<?= e($plate) ?>">
                <span>Plaka</span>
                <strong><?= e($plate) ?></strong>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="split-fields">
          <label>
            Araç / Plaka
            <input
              name="vehicle_plate"
              data-plate-format
              data-external-plate-target
              autocomplete="off"
              autocapitalize="characters"
              spellcheck="false"
              maxlength="11"
              placeholder="34 ABC 123"
            >
          </label>
          <label>
            Aracın çıkış km'sini öğrenin
            <input name="exit_km" type="number" min="0" inputmode="numeric" placeholder="Km">
          </label>
        </div>

        <button class="primary-action" type="submit">Çıkış Ver</button>
      </form>
    <?php else: ?>
      <article class="empty-state compact">Dış görev çıkışı oluşturma yetkiniz yok.</article>
    <?php endif; ?>
  </section>

  <section class="panel-card wide external-outside-card">
    <div class="section-head">
      <div>
        <p class="eyebrow">Anlık durum</p>
        <h1>Dışarıda Olanlar</h1>
      </div>
    </div>

    <?php if (!$outsideMovements): ?>
      <article class="empty-state">Dışarıda aktif kayıt yok.</article>
    <?php else: ?>
      <div class="external-movement-list">
        <?php foreach ($outsideMovements as $movement): ?>
          <article class="external-movement-card is-outside">
            <div class="external-movement-main">
              <span class="external-badge">Dışarıda</span>
              <div>
                <strong><?= e($movement['person_name']) ?></strong>
                <small><?= e($movement['department_name'] ?? 'Bölüm yok') ?><?= !empty($movement['vehicle_plate']) ? ' | ' . e($movement['vehicle_plate']) : '' ?></small>
              </div>
            </div>
            <div class="external-movement-meta">
              <span>Çıkış: <?= e($formatDate($movement['exit_at'] ?? null)) ?></span>
              <span><?= e((int) ($movement['elapsed_minutes'] ?? 0)) ?> dk dışarıda</span>
              <span>Çıkış km: <?= e($movement['exit_km'] ?? '-') ?></span>
              <span>Not: <?= e($movement['destination_note'] ?? '-') ?></span>
            </div>

            <?php if ($canCreateReturn): ?>
              <form class="external-return-form" method="post" action="<?= e(route('/external-movements/return')) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="movement_id" value="<?= e($movement['id']) ?>">
                <label>
                  Giriş km
                  <input name="return_km" type="number" min="<?= e($movement['exit_km'] ?? 0) ?>" inputmode="numeric" placeholder="Km">
                </label>
                <label>
                  Dönüş notu
                  <input name="return_note" placeholder="Not">
                </label>
                <button class="dark-button" type="submit">Giriş Ver</button>
              </form>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="panel-card wide external-history-card">
    <div class="section-head">
      <div>
        <p class="eyebrow">Son kayıtlar</p>
        <h1>Tamamlanan Dış Görevler</h1>
      </div>
    </div>

    <?php if (!$recentMovements): ?>
      <article class="empty-state compact">Henüz tamamlanan dış görev yok.</article>
    <?php else: ?>
      <div class="external-history-list">
        <?php foreach ($recentMovements as $movement): ?>
          <article class="external-history-row">
            <div>
              <strong><?= e($movement['person_name']) ?></strong>
              <small><?= e($movement['department_name'] ?? 'Bölüm yok') ?><?= !empty($movement['destination_note']) ? ' | ' . e($movement['destination_note']) : '' ?></small>
            </div>
            <span><?= e($formatDate($movement['exit_at'] ?? null)) ?> - <?= e($formatDate($movement['return_at'] ?? null)) ?></span>
            <span><?= e($movement['vehicle_plate'] ?? '-') ?></span>
            <span><?= $movement['total_km'] !== null ? e($movement['total_km']) . ' km' : '-' ?></span>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</section>
