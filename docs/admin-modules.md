# Yönetim Paneli Modülleri

Bu bölümde PHP uygulamasına ilk yönetim ekranları eklendi.

## Eklenen Ekranlar

| Route | Amaç |
| --- | --- |
| `/admin` | Yönetim paneli ana menüsü |
| `/admin/categories` | Ziyaretçi kategorisi tanımlama |
| `/admin/departments` | Departman ve departman amiri tanımlama |
| `/admin/users` | Kullanıcı oluşturma ve rol atama |
| `/admin/visitors` | Kayıtlı kişi kartlarını görüntüleme ve düzenleme |
| `/admin/watchlist` | Kara liste ve uyarı listesi yönetimi |
| `/admin/mail-templates` | Mail konu ve gövde şablonları |
| `/admin/notification-channels` | Mail, Telegram ve WhatsApp kanal ayarları |
| `/admin/notification-rules` | Mail, Telegram ve WhatsApp bildirim kuralı tanımlama |
| `/admin/records` | Giriş çıkış kayıtlarını filtreleme ve PDF gönderimi |
| `/admin/reports` | Günlük, haftalık ve aylık rapor geçmişi |
| `/admin/backups` | Manuel yedek ve yedek logları |

## Kategori Yönetimi

Kategori ekranında şu bilgiler tanımlanır:

- kategori adı ve kodu
- renk
- maksimum içeride kalma süresi
- süre bitmeden kaç dakika önce uyarı verileceği
- süre aşımında departman amirine soru sorulup sorulmayacağı
- bildirimlerin aktif olup olmayacağı

N+ eskalasyon zinciri kategoriye bağlı değildir. Tüm kategoriler, `/admin` ana yönetim ekranında tanımlanan global zinciri kullanır.

## Global Eskalasyon Zinciri

Yönetim ana ekranında N+1, N+2 ve N+3 amirleri ile her seviyenin bekleme süresi tanımlanır. Departman amiri cevap vermezse sıradaki N+ amire geçilir; son tanımlı seviyede beklemede kalır.

## Departman Yönetimi

Departman ekranında departman adı, kodu, amir kullanıcısı, e-posta ve telefon bilgisi tutulur. Süre aşımında “Bu kişi sizinle beraber mi?” sorusu bu departman amirine yönlendirilir.

## Kullanıcı ve Yetki Yönetimi

Kullanıcı ekranında panel kullanıcısı oluşturulur ve sistem rolleri atanır:

- Patron
- Genel Müdür
- Operasyon Müdürü
- Gece Müdürü
- Departman Müdürü
- Güvenlik Personeli
- Sistem Yöneticisi

Kullanıcı üzerinde Günlük Rapor Gönder, Haftalık Rapor Gönder ve Aylık Rapor Gönder tikleri bulunur. Bu tikler aktifse rapor işleyici ilgili rapor zamanı geldiğinde kullanıcının e-posta adresine mail kuyruğu oluşturur.

## Kayıtlı Kişiler

Kayıtlı kişiler ekranında daha önce gelen veya güvenlik tarafından manuel eklenen kişi kartları görünür. Ad soyad, telefon, firma, plaka ve not bilgisi düzenlenebilir veya silinebilir. Giriş ekranında ad soyad yazıldığında bu kartlardaki bilgiler otomatik önerilir ve forma doldurulur.

## Kara / Uyarı Listesi

Kara liste ve uyarı listesi ad soyad, telefon veya plaka üzerinden eşleşir. Kara liste eşleşmesi varsa giriş kaydı oluşturulmaz. Uyarı listesi eşleşmesi varsa giriş kaydı oluşturulur, fakat güvenliğe sebep ve aksiyon notuyla uyarı gösterilir.

## Bildirim Kuralları

Bildirim kuralları olay bazlı çalışacak şekilde tasarlandı:

- giriş
- çıkış
- süre uyarısı
- departman sorusu
- departman “hayır” cevabı
- cevap yok
- gün sonu raporu
- haftalık rapor
- ay sonu raporu

Her kural için kanal seçilebilir:

- Mail
- Telegram
- WhatsApp

Alıcı tipi özel kişi, departman amiri veya rol grubu olabilir.

## Mail Şablonları

Mail şablonları ekranında her olay tipi için konu ve gövde metni ayrı düzenlenir. Ekranda temel kullanım açıklaması, tıklanabilir değişkenler ve canlı ön izleme bulunur. Bu şablonlar yalnızca Mail kanalında uygulanır; Telegram ve WhatsApp mesajları bildirim kuralı mesaj şablonundan devam eder.

## Bildirim Servisi Bağlantısı

Bu kurallar artık giriş ve çıkış olaylarında `notification_logs` kuyruğuna kayıt açar. Mail, Telegram ve WhatsApp gönderim adaptörleri ile kuyruk işleyici detayları `docs/notification-services.md` dosyasındadır.

## Kayıt Listesi ve PDF

Kayıtlar ekranında giriş çıkış kayıtları tarih, kategori, departman, durum ve arama metniyle filtrelenir. Randevulu / randevusuz bilgisi giriş kaydı üzerinde saklanır ve kayıt listesinde görünür. Aynı filtreyle PDF indirilebilir veya PDF ekli mail olarak gönderim kuyruğuna alınabilir.

## Rapor Planları

Rapor ekranında günlük, haftalık ve aylık planlar eklenebilir, düzenlenebilir, silinebilir, kaydedilebilir ve manuel çalıştırılabilir. Format PDF seçilirse rapor dosyası PDF olarak üretilir ve mail gönderiminde ekli dosya olarak kuyruğa yazılır. Silinen planlar pasife çekilip gizlenir; geçmiş rapor logları korunur.

## Yedekleme

Yedekleme ekranında günlük otomatik sistem yedeği planı düzenlenebilir. Plan aktifse `bin/backups-work.php` cron işi zamanı geldiğinde yedek zip dosyasını oluşturur. Veritabanı seçeneği açık olduğunda zip içine `database/live-dump.sql` canlı veri dump dosyası da eklenir. "Yedek tamamlanınca mail gönder" seçeneği ve alıcı e-posta adresi tanımlıysa zip dosyası Mail kanalına ekli dosya olarak kuyruğa alınır ve işleyici aynı turda kuyruğu göndermeye çalışır. Başarılı yedek loglarında `İndir` butonu görünür; dosya hâlâ `storage/backups` altında mevcutsa zip olarak indirilir.

## Süre Aşımı Akışı

`bin/timeouts-work.php` içeride kalma süresi yaklaşan veya dolan ziyaretçileri kontrol eder.

- Süre yaklaşınca `timeout_warning` bildirimi kuyruğa alınır.
- Süre dolunca departman amirine “sizinle beraber mi?” sorusu oluşturulur.
- Amir panelden evet/hayır cevabı verebilir.
- Hayır cevabı veya cevap gelmemesi durumunda yönetici eskalasyonu kuyruğa alınır.
