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
            'Telefon / Web Push bildirimini aktif ederek giriş ve çıkış bildirimlerini ayrı ayrı seçin.',
            'Yönetim ekranı panellerinde kullanıcının göreceği alanları işaretleyin.',
            'Yeni kullanıcı kaydedildiğinde giriş bilgileri e-posta ile gönderilir.',
        ],
    ],
    [
        'title' => '6. Bildirim Kanalları',
        'image' => '06-notification-channels.png',
        'text' => 'Mail, Telegram ve WhatsApp gönderim ayarları bu ekranda yapılır.',
        'steps' => [
            'Kanalı aktif hale getirin.',
            'Gerekli hesap, token veya SMTP bilgilerini girin.',
            'WhatsApp için Meta Cloud API seçiliyken API versiyonu, kalıcı Access Token ve WhatsApp Business Account ID yazın.',
            'Entegrasyonu Tamamla butonu Meta Graph API bağlantısını kurar, bağlı numarayı bulur ve Phone Number ID değerini kaydeder.',
            'Bağlantı tamamlanınca doğrulanmış ad, gönderici numara, kalite ve platform bilgilerini ekrandan kontrol edin.',
            'Kaydetmeden sonra test gönderimi yapın.',
        ],
    ],
    [
        'title' => '7. Telefon ve Web Push Bildirimleri',
        'image' => '05-users-permissions.png',
        'text' => 'Yetki verilen kullanıcıların telefon veya bilgisayar tarayıcısından yeni giriş ve çıkış bildirimi alması bu akışla çalışır.',
        'steps' => [
            'Yönetim > Kullanıcı ve Yetki Yönetimi ekranında kullanıcı için Web Push bildirimini aktif edin.',
            'İçeri giriş ve çıkış bildirim tiklerini kullanıcının sorumluluğuna göre açın.',
            'Kullanıcı sisteme giriş yaptığında ekrandaki Telefon bildirimleri uyarısından İzin Ver seçeneğine basmalıdır.',
            'iPhone veya iPad kullanılıyorsa site önce Safari içinden Paylaş > Ana Ekrana Ekle ile kurulmalı, sonra ana ekran ikonundan açılmalıdır.',
            'Normal Safari sekmesinde açılırsa iPhone Web Push çalışmaz; sistem kullanıcıya ana ekrana ekleme uyarısı gösterir.',
            'Bildirim izni reddedilmişse cihaz veya tarayıcı ayarlarından izin tekrar açılmalıdır.',
            'Gerçek Web Push desteklenmeyen cihazlarda panel açık olduğu sürece bildirim yedeği çalışmaya devam eder.',
        ],
    ],
    [
        'title' => '8. Bildirim Kuralları',
        'image' => '07-notification-rules.png',
        'text' => 'Hangi olayda kime, hangi kanaldan bildirim gönderileceği bu panelde belirlenir.',
        'steps' => [
            'Olay tipini ve kategoriyi seçin.',
            'Kanal ve alıcı bilgilerini belirleyin.',
            'Eskalasyon cevap gelmezse sıradaki amire geçer.',
            'Evet / Hayır cevapları mailde buton, Telegramda inline buton, WhatsApp mesajında güvenli cevap linki olarak gönderilir.',
        ],
    ],
    [
        'title' => '9. Mail Şablonları',
        'image' => '08-mail-templates.png',
        'text' => 'Giden maillerin konu ve içerikleri basic kullanıcıların da rahat düzenleyebileceği şekilde hazırlanır.',
        'steps' => [
            'Şablon açıklamasını okuyun.',
            'Hazır değişkenleri kullanarak mesajı düzenleyin.',
            'Şablonları kaydedin.',
        ],
    ],
    [
        'title' => '10. Kayıt Listesi ve PDF',
        'image' => '09-records.png',
        'text' => 'Giriş çıkış kayıtları filtrelenir, PDF alınır veya mail ile gönderilir.',
        'steps' => [
            'Tarih, kategori, departman, durum veya arama filtresi seçin.',
            'Filtrele ile listeyi daraltın.',
            'PDF indir veya mail ile paylaş işlemini kullanın.',
        ],
    ],
    [
        'title' => '11. Planlı Raporlar',
        'image' => '10-reports.png',
        'text' => 'Günlük, haftalık ve aylık rapor planları buradan yönetilir.',
        'steps' => [
            'Rapor sıklığını seçin.',
            'Alıcıları ve kanalları belirleyin.',
            'Gerekirse Çalıştır ile manuel gönderim yapın.',
        ],
    ],
    [
        'title' => '12. Kara / Uyarı Listesi',
        'image' => '11-watchlist.png',
        'text' => 'Riskli veya dikkat gerektiren kişiler ad, telefon veya plaka eşleşmesine göre takip edilir.',
        'steps' => [
            'Liste türünü seçin.',
            'Eşleşecek değeri ve sebebi girin.',
            'Kişi tekrar giriş yaptığında canlı panelde büyük uyarı çıkar.',
        ],
    ],
    [
        'title' => '13. Yedekleme',
        'image' => '12-backups.png',
        'text' => 'Otomatik yedekleme, manuel yedek alma, yedek geçmişi ve indirme işlemleri bu panelden yapılır.',
        'steps' => [
            'Yedek planını aktif hale getirin.',
            'Veritabanı ve dosya seçeneklerini kontrol edin.',
            'Geçmiş yedekleri indirerek saklayın.',
        ],
    ],
    [
        'title' => '14. Kurulum Sihirbazı',
        'image' => '13-setup.png',
        'text' => 'İlk kurulumda SQL bilgileri ve ilk admin hesabı girilerek sistem otomatik hazırlanır.',
        'steps' => [
            'SQL bağlantı bilgilerini girin.',
            'İlk admin kullanıcısını oluşturun.',
            'Sistem kurulduktan sonra bu ekran sadece yetkili yöneticilere açık kalmalıdır.',
        ],
    ],
    [
        'title' => '15. Günlük Operasyon Akışı',
        'text' => 'Güvenlik görevlisi için önerilen günlük kullanım sırası bu şekildedir.',
        'steps' => [
            'Sisteme giriş yapın ve canlı paneli açık tutun.',
            'Gelen kişi için kategori, randevu durumu ve kişi bilgilerini girerek giriş kaydı oluşturun.',
            'Kara liste uyarısı çıkarsa uyarıyı okuyup amire bilgi verin.',
            'İçeride olanlar listesinden süre aşımı ve departman onay durumlarını takip edin.',
            'Kişi ayrıldığında Detay ile bilgileri kontrol edip çıkış kaydı oluşturun.',
            'Gün sonunda kayıt listesi veya rapor ekranından kayıtları kontrol edin.',
        ],
    ],
    [
        'title' => '16. Yönetici Kontrol Listesi',
        'text' => 'Yöneticiler belirli aralıklarla aşağıdaki ayarları kontrol etmelidir.',
        'steps' => [
            'Kategoriler, departmanlar ve amirler doğru mu?',
            'Kullanıcı rolleri, panel yetkileri ve Web Push tikleri doğru mu?',
            'Mail, Telegram ve WhatsApp kanalları test edildi mi?',
            'WhatsApp Meta bağlantısında doğrulanmış ad ve Phone Number ID görünüyor mu?',
            'Eskalasyon zinciri ve cevap bekleme süreleri doğru sırada mı?',
            'Mail şablonları, rapor alıcıları ve yedekleme planı güncel mi?',
        ],
    ],
    [
        'title' => '17. Önemli Notlar',
        'text' => 'Canlı kullanımda dikkat edilmesi gereken genel kurallar.',
        'steps' => [
            'Canlıya alınacak her yeni sürüm için sürüm adı ayrıca belirtilmelidir.',
            'DB değişikliği olacaksa canlıya almadan önce yöneticiden onay alınmalıdır.',
            'Yeni geliştirmeler önce test ortamında denenmelidir.',
            'iPhone Web Push için uygulama mutlaka ana ekran ikonundan açılmalıdır.',
            'Mail sağlayıcısı spam reddi verirse uygulama gönderimi denemiş olur; SMTP veya mail itibarı ayrıca kontrol edilmelidir.',
        ],
    ],
];
$backRoute = $backRoute ?? '/admin';
$backLabel = $backLabel ?? 'Yönetim ekranına dön';
?>

<section class="admin-hero">
  <div>
    <p class="eyebrow">Yardım</p>
    <h1>V1.12 Kullanım Kılavuzu</h1>
    <p class="muted">Güvenlik ve yönetim ekipleri için güncel ekran görüntülü hızlı kullanım rehberi.</p>
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
            <p class="eyebrow">V1.12</p>
            <h2><?= e($section['title']) ?></h2>
            <p class="muted"><?= e($section['text']) ?></p>
          </div>
        </div>

        <?php if (!empty($section['image'])): ?>
          <img
            class="guide-image"
            src="/assets/guide/v1/<?= e($section['image']) ?>"
            alt="<?= e($section['title']) ?> ekran görüntüsü"
            loading="lazy"
          >
        <?php endif; ?>

        <ol class="guide-steps">
          <?php foreach ($section['steps'] as $step): ?>
            <li><?= e($step) ?></li>
          <?php endforeach; ?>
        </ol>
      </article>
    <?php endforeach; ?>
  </div>
</section>
