# PHP Proje İskeleti

Bu bölüm, statik prototipten MariaDB bağlantılı PHP uygulamasına geçiş için hazırlandı.

## Klasörler

| Klasör | Amaç |
| --- | --- |
| `public/` | Web sunucusunun document root klasörü |
| `public/index.php` | Basit front controller ve route tablosu |
| `public/assets/app.css` | PHP panel arayüzü stilleri |
| `app/Controllers/` | Setup, login, dashboard ve ziyaret işlem controllerları |
| `app/Models/` | PDO ile çalışan veri erişim sınıfları |
| `app/Core/` | Database, Auth ve CSRF yardımcıları |
| `app/Views/` | PHP ekran şablonları |
| `config/` | Uygulama ve veritabanı ayarları |
| `database/` | MariaDB şema ve başlangıç verileri |
| `storage/` | İleride yedekler, raporlar ve loglar |

## Kurulum

1. Dosyaları sunucuya yükleyin.
2. Web sunucusunun document root değerini `public/` klasörüne verin.
3. Tarayıcıdan `/setup` adresini açın.
4. SQL host, port, veritabanı adı, SQL kullanıcı adı/şifre ve ilk admin giriş bilgilerini yazın.
5. Kurulum sihirbazı `.env` dosyasını yazar, `database/schema.sql` ve `database/seed.sql` dosyalarını otomatik çalıştırır, ilk admin kullanıcısını oluşturur.

PHP dahili sunucusu ile örnek:

```bash
php -S localhost:8000 -t public
```

Tarayıcıdan `http://localhost:8000/setup` adresine gidin.

## Mevcut PHP Akışları

- İlk kurulum ekranı
- Admin kullanıcısı oluşturma
- Login / logout
- Canlı panel
- Giriş kaydı oluşturma
- Çıkış kaydı oluşturma
- Ziyaretçi kartını tekrar kullanma

## Sonraki Adım

Bir sonraki bölümde yönetim paneli modülleri eklenecek:

- Kategori tanımlama
- Kullanıcı ve yetki yönetimi
- Departman yönetimi
- Bildirim kuralı yönetimi
