<?php

$sections = [
    [
        'title' => '1. Giriş',
        'image' => '01-login.png',
        'text' => 'Kullanıcı adı ve şifre ile sisteme giriş yapılır. Kullanıcı sadece kendisine açık olan panelleri görür.',
        'steps' => [
            'Kullanıcı adınızı ve şifrenizi girin.',
            'Giriş Yap butonuna basın.',
            'Yetkiniz yoksa kapalı panele linkle gidilse bile sistem erişimi engeller.',
        ],
    ],
    [
        'title' => '2. Canlı Panel',
        'image' => '02-dashboard.png',
        'text' => 'Güvenlik ekibinin ana ekranıdır. Giriş kaydı, çıkış kaydı, içeride olanlar, süre aşımı ve kara liste uyarıları buradan takip edilir.',
        'steps' => [
            'Gelen kişi için kategori seçin.',
            'Randevulu veya randevusuz bilgisini işaretleyin.',
            'Ad soyad, telefon, plaka, firma ve departman bilgilerini girin.',
            'Kişi ayrıldığında detayları kontrol edip çıkış verin.',
        ],
    ],
    [
        'title' => '3. Yönetim Ana Ekranı',
        'image' => '03-admin-home.png',
        'text' => 'Tanımlar, kurallar, raporlar ve sistem ayarları yönetim ana ekranından açılır.',
        'steps' => [
            'Her kart ilgili yönetim paneline gider.',
            'Kapalı yetkiler menüde ve kart listesinde görünmez.',
            'Bu sayfadaki kılavuz butonu V1 yardım ekranını açar.',
        ],
    ],
    [
        'title' => '4. Kategoriler',
        'image' => '04-categories.png',
        'text' => 'Otele gelen kişi türleri ve bu türlere bağlı süre kuralları bu panelden tanımlanır.',
        'steps' => [
            'Kategori adını ve rengini belirleyin.',
            'İçeride kalma süresi ve uyarı zamanını girin.',
            'Hızlı seçimde görünecek kategorileri işaretleyin.',
        ],
    ],
    [
        'title' => '5. Kullanıcı ve Yetki Yönetimi',
        'image' => '05-users-permissions.png',
        'text' => 'Kullanıcı, rol, rapor aboneliği ve kullanıcı bazlı panel erişimleri buradan yönetilir.',
        'steps' => [
            'Kullanıcı bilgilerini girin.',
            'Rol veya rolleri seçin.',
            'Günlük, haftalık ve aylık rapor tiklerini ihtiyaca göre açın.',
            'Yönetim ekranı panellerinde kullanıcının göreceği alanları işaretleyin.',
        ],
    ],
    [
        'title' => '6. Bildirim Kanalları',
        'image' => '06-notification-channels.png',
        'text' => 'Mail, Telegram ve WhatsApp gönderim ayarları bu ekranda yapılır.',
        'steps' => [
            'Kanalı aktif hale getirin.',
            'Gerekli hesap, token veya SMTP bilgilerini girin.',
            'Kaydetmeden sonra test gönderimi yapın.',
        ],
    ],
    [
        'title' => '7. Bildirim Kuralları',
        'image' => '07-notification-rules.png',
        'text' => 'Hangi olayda kime, hangi kanaldan bildirim gönderileceği bu panelde belirlenir.',
        'steps' => [
            'Olay tipini ve kategoriyi seçin.',
            'Kanal ve alıcı bilgilerini belirleyin.',
            'Eskalasyon cevap gelmezse sıradaki amire geçer.',
        ],
    ],
    [
        'title' => '8. Mail Şablonları',
        'image' => '08-mail-templates.png',
        'text' => 'Giden maillerin konu ve içerikleri basic kullanıcıların da rahat düzenleyebileceği şekilde hazırlanır.',
        'steps' => [
            'Şablon açıklamasını okuyun.',
            'Hazır değişkenleri kullanarak mesajı düzenleyin.',
            'Şablonları kaydedin.',
        ],
    ],
    [
        'title' => '9. Kayıt Listesi ve PDF',
        'image' => '09-records.png',
        'text' => 'Giriş çıkış kayıtları filtrelenir, PDF alınır veya mail ile gönderilir.',
        'steps' => [
            'Tarih, kategori, departman, durum veya arama filtresi seçin.',
            'Filtrele ile listeyi daraltın.',
            'PDF indir veya mail ile paylaş işlemini kullanın.',
        ],
    ],
    [
        'title' => '10. Planlı Raporlar',
        'image' => '10-reports.png',
        'text' => 'Günlük, haftalık ve aylık rapor planları buradan yönetilir.',
        'steps' => [
            'Rapor sıklığını seçin.',
            'Alıcıları ve kanalları belirleyin.',
            'Gerekirse Çalıştır ile manuel gönderim yapın.',
        ],
    ],
    [
        'title' => '11. Kara / Uyarı Listesi',
        'image' => '11-watchlist.png',
        'text' => 'Riskli veya dikkat gerektiren kişiler ad, telefon veya plaka eşleşmesine göre takip edilir.',
        'steps' => [
            'Liste türünü seçin.',
            'Eşleşecek değeri ve sebebi girin.',
            'Kişi tekrar giriş yaptığında canlı panelde büyük uyarı çıkar.',
        ],
    ],
    [
        'title' => '12. Yedekleme',
        'image' => '12-backups.png',
        'text' => 'Otomatik yedekleme, manuel yedek alma, yedek geçmişi ve indirme işlemleri bu panelden yapılır.',
        'steps' => [
            'Yedek planını aktif hale getirin.',
            'Veritabanı ve dosya seçeneklerini kontrol edin.',
            'Geçmiş yedekleri indirerek saklayın.',
        ],
    ],
    [
        'title' => '13. Kurulum Sihirbazı',
        'image' => '13-setup.png',
        'text' => 'İlk kurulumda SQL bilgileri ve ilk admin hesabı girilerek sistem otomatik hazırlanır.',
        'steps' => [
            'SQL bağlantı bilgilerini girin.',
            'İlk admin kullanıcısını oluşturun.',
            'Sistem kurulduktan sonra bu ekran sadece yetkili yöneticilere açık kalmalıdır.',
        ],
    ],
];
$backRoute = $backRoute ?? '/admin';
$backLabel = $backLabel ?? 'Yönetim ekranına dön';
?>

<section class="admin-hero">
  <div>
    <p class="eyebrow">Yardım</p>
    <h1>V1 Kullanım Kılavuzu</h1>
    <p class="muted">Güvenlik ve yönetim ekipleri için ekran görüntülü hızlı kullanım rehberi.</p>
  </div>
  <div class="admin-hero-actions">
    <a class="dark-button" href="<?= e(route($backRoute)) ?>"><?= e($backLabel) ?></a>
  </div>
</section>

<section class="guide-shell">
  <aside class="panel-card guide-toc">
    <p class="eyebrow">İçindekiler</p>
    <h2>Başlıklar</h2>
    <nav aria-label="Kılavuz başlıkları">
      <?php foreach ($sections as $index => $section): ?>
        <a href="#guide-section-<?= e($index + 1) ?>"><?= e($section['title']) ?></a>
      <?php endforeach; ?>
    </nav>
  </aside>

  <div class="guide-content">
    <?php foreach ($sections as $index => $section): ?>
      <article class="panel-card guide-section" id="guide-section-<?= e($index + 1) ?>">
        <div class="section-head">
          <div>
            <p class="eyebrow">V1</p>
            <h2><?= e($section['title']) ?></h2>
            <p class="muted"><?= e($section['text']) ?></p>
          </div>
        </div>

        <img
          class="guide-image"
          src="/assets/guide/v1/<?= e($section['image']) ?>"
          alt="<?= e($section['title']) ?> ekran görüntüsü"
          loading="lazy"
        >

        <ol class="guide-steps">
          <?php foreach ($section['steps'] as $step): ?>
            <li><?= e($step) ?></li>
          <?php endforeach; ?>
        </ol>
      </article>
    <?php endforeach; ?>
  </div>
</section>
