# Otel Güvenlik Giriş Çıkış Sistemi

Bu paket, otel güvenlik birimi için hazırlanmış PHP / MariaDB tabanlı giriş-çıkış takip uygulamasıdır. Statik `index.html` dosyası panel ön izlemesi olarak bırakıldı; canlı uygulama `public/` klasöründen çalışır.

## İçerik

- Hızlı ziyaretçi giriş formu
- Girişte randevulu / randevusuz işareti
- Daha önce gelen kişi için otomatik bilgi doldurma
- İçeride olanlar listesi
- Tek tık çıkış işlemi
- Kayıtlı kişiler ekranı ve kişi kartı düzenleme
- Kara liste / uyarı listesi; kara liste girişi engeller, uyarı listesi güvenliğe ikaz verir
- Kategori bazlı içeride kalma süresi takibi
- Süre aşımında departman amiri doğrulama akışı
- Olumsuz cevapta yönetici eskalasyonu
- Canlı kurulum sihirbazı: SQL bilgileri ve ilk admin hesabı ile otomatik kurulum
- Logo, otel adı, domain ve bildirim ayarları
- Manuel ve otomatik yedekleme akışı, canlı veritabanı dump dosyası ve günlük mail eki
- JSON yedekten geri dönme demo akışı
- MariaDB şema dosyası ve başlangıç verileri
- PHP proje iskeleti, login, kurulum ve veritabanına bağlı giriş/çıkış paneli
- Basit yönetim paneli: kategori, departman, kullanıcı/yetki, kayıtlı kişiler, kara/uyarı listesi ve bildirim kuralı ekranları
- Mail kanalına özel açıklamalı konu/gövde şablonları ve canlı ön izleme
- Kullanıcı yönetiminde günlük / haftalık / aylık rapor gönderim tikleri
- Kullanıcı yönetiminde CSV / XLSX import ve örnek dosya indirme
- Bildirim kuyruğu ve Mail / Telegram / WhatsApp gönderim adaptörleri
- Süre aşımı, departman amiri onayı ve yönetici eskalasyonu
- Tüm kategoriler için geçerli global N+ eskalasyon zinciri
- Kayıtları filtreleme, PDF indirme ve PDF ekli mail gönderimi
- Günlük / haftalık / aylık rapor planı ekleme, düzenleme, silme, HTML/PDF üretimi ve PDF mail eki
- Manuel ve otomatik yedekleme işleyicisi; yedek zip dosyasını günlük mail olarak gönderme
- Yedek geçmişinden zip dosyası indirme
- Kurulum sihirbazında yedek staging akışı

## Çalıştırma

Sunucuda PHP ve MariaDB hazırlandıktan sonra canlı kurulum:

1. Dosyaları sunucuya yükleyin.
2. Web sunucusunun document root değerini `public/` klasörüne verin.
3. Tarayıcıdan `/setup` adresine girin.
4. SQL host, port, veritabanı adı, SQL kullanıcı adı/şifre ve ilk admin giriş bilgilerini yazın.
5. Sihirbaz `.env` dosyasını yazar, tabloları kurar, başlangıç verilerini yükler ve admin hesabını oluşturur.

Mac üzerinde Herd PHP ile hızlı lokal test:

```bash
php -S 127.0.0.1:8000 -t public public/router.php
```

Document root örneği:

```text
/var/www/otel-guvenlik
├── app
├── config
├── database
├── public  ← domain burayı göstermeli
└── storage
```

Eğer panel açılmazsa önce şu adresi deneyin:

```text
https://alanadiniz.com/index.php?route=/setup
```

## Veritabanı

İlk faz veritabanı tasarımı `database/schema.sql` ve `database/seed.sql` dosyalarındadır. Açıklama için `docs/database-schema.md` dosyasına bakabilirsiniz.

## PHP Uygulaması

PHP iskeleti `public/`, `app/` ve `config/` klasörlerinde hazırdır. Kurulum notları için `docs/php-skeleton.md` dosyasına bakabilirsiniz.

## Yönetim Paneli

Yönetim ekranları için `docs/admin-modules.md` dosyasına bakabilirsiniz.

Kullanıcı import için Yönetim > Kullanıcı ve Yetki Yönetimi ekranındaki "Örnek Dosya İndir" bağlantısını kullanın. Dosya kolonları: `full_name`, `username`, `email`, `phone`, `department_code`, `password`, `roles`, `status`. Roller birden fazlaysa `Operasyon Müdürü|Gece Müdürü` şeklinde `|` ile ayrılabilir.

## Bildirim Servisleri

Giriş, çıkış ve süre aşımı olaylarında bildirim kuralları `notification_logs` kuyruğuna alınır. Telefon bildirimi yetkisi olan kullanıcılar V1.7 ile gerçek Web Push aboneliği alabilir; kullanıcı kartında giriş ve çıkış Web Push bildirimleri ayrı ayrı seçilir. Desteklemeyen cihazlarda açık-panel polling bildirimi yedek olarak devam eder. Sunucu tarafında kesintisiz çalışma için yine de cron işleri önerilir. Kuyruk işleyici ve kanal ayarları için `docs/notification-services.md` dosyasına bakabilirsiniz.

## Cron İşleri

Sunucuda önerilen cron akışı:

```bash
* * * * * /usr/bin/php /var/www/otel-guvenlik/bin/timeouts-work.php --limit=100
* * * * * /usr/bin/php /var/www/otel-guvenlik/bin/notifications-work.php --limit=50
*/10 * * * * /usr/bin/php /var/www/otel-guvenlik/bin/reports-work.php --limit=10
15 * * * * /usr/bin/php /var/www/otel-guvenlik/bin/backups-work.php --limit=5
```

Yedekleme maili için Yönetim > Yedekleme ekranında "Yedek tamamlanınca mail gönder" seçeneğini aktif bırakın ve alıcı e-posta adresini yazın. Cron çalıştığında yedek zip dosyası oluşturulur, canlı veritabanı dump dosyası pakete eklenir ve mail kanalına ekli dosya olarak gönderilir.

Final paket notları için `docs/final-package.md` dosyasına bakabilirsiniz.
