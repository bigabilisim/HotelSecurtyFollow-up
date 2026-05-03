# Sürüm Geçmişi

Bu dosya Otel Güvenlik Sistemi sürüm geçişlerini takip etmek için kullanılır. Canlıya alınacak sürüm ayrıca kullanıcı tarafından belirtilmeden yayınlanmaz.

## V1.12 - Yayın: 2026-05-03

Başlık: Safari Web Push ve mobil kullanım

Özet:

iPhone/Safari Web Push uyarıları, PWA kurulum yardımı ve mobil canlı panel yerleşimi iyileştirildi.

Yenilikler:

- iPhone Safari normal sekmesinde açıldığında kullanıcıya Ana Ekrana Ekle yönlendirmesi gösterilir.
- HTTPS, service worker, Notification API ve PushManager eksikleri artık sessiz geçilmez; kullanıcıya anlaşılır uyarı çıkar.
- Web Push aboneliği sunucuya kaydedilemezse hata ekranda gösterilir ve panel-açık bildirim yedeği çalışmaya devam eder.
- Bildirim izni reddedilmiş cihazlarda ayarlardan izin açılması gerektiği net olarak bildirilir.
- HEAD istekleri GET rotalarıyla eşleştirilerek manifest ve PWA kontrol istekleri daha uyumlu hale getirildi.
- Kullanım kılavuzu WhatsApp entegrasyonu, Web Push ve Safari kurulum adımlarıyla güncellendi.
- Mobil canlı panelde Kapı işlemleri en üst sıraya, ana menü ise alt sabit menüye alındı.
- Ana menüye Uygulamayı Kur butonu eklendi; destekleyen tarayıcıda PWA kurulum penceresini, diğerlerinde kurulum yönergesini gösterir.

DB notu:

- DB şema değişikliği yok.

Canlı durumu:

- 2026-05-03 tarihinde canlıya alındı.

## V1.11 - Yayın: 2026-05-02

Başlık: Otomatik WhatsApp Meta bağlantısı

Özet:

WhatsApp entegrasyonu Meta Graph API bağlantısıyla otomatik tamamlanır ve sonuç ekranda gösterilir.

Yenilikler:

- Entegrasyonu Tamamla butonu Meta Graph API üzerinden WhatsApp Business Account ID ile bağlı telefonları okur.
- Phone Number ID bilinmiyorsa sistem WABA içindeki uygun numarayı otomatik seçer ve kaydeder.
- Phone Number ID girilmişse aynı buton numarayı Meta üzerinde doğrular ve bağlantıyı tamamlar.
- Doğrulanmış ad, gönderici numara, Phone Number ID, kalite ve platform bilgileri kanal kartında kalıcı olarak gösterilir.
- WhatsApp sihirbazı tarayıcı kontrolü WABA ID veya Phone Number ID senaryosuna göre eksik alanları akıllı şekilde bildirir.

DB notu:

- DB şema değişikliği yok. Mevcut `notification_channels.config_json` alanı kullanılır.

Canlı durumu:

- 2026-05-02 tarihinde canlıya alındı.

## V1.10 - Yayın: 2026-05-02

Başlık: WhatsApp kurulum sihirbazı

Özet:

WhatsApp Cloud API kurulumu kullanıcı dostu sihirbazla otomatikleştirildi.

Yenilikler:

- Kanal Ayarları ekranındaki WhatsApp kartına adım adım kurulum sihirbazı eklendi.
- Meta Cloud API endpointi API versiyonu ve Phone Number ID değerlerinden otomatik ön izlenir.
- Sihirbazı Kaydet ve Aktifleştir butonu WhatsApp kanalını tek adımda aktif ve yapılandırılmış hale getirir.
- Bağlantıyı Doğrula butonu Meta Graph API üzerinden Phone Number ID ve token uyumunu kontrol eder.
- WhatsApp test ve canlı alıcı numaraları `05xx`, `+90` veya `90` formatlarından Meta gönderim formatına otomatik çevrilir.

DB notu:

- DB şema değişikliği yok. Mevcut `notification_channels.config_json` alanı kullanılır.

Canlı durumu:

- 2026-05-02 tarihinde canlıya alındı.

## V1.9 - Yayın: 2026-05-02

Başlık: İçeride olan kayıt düzenleme

Özet:

Güvenlik ekibi, giriş sonrası içeride olan ziyaretçilerin bilgilerini canlı panel üzerinden düzeltebilir.

Yenilikler:

- İçeride Olanlar listesine Düzenle aksiyonu eklendi.
- Detay panelinden ad soyad, kategori, departman, telefon, plaka, firma, görüşeceği kişi, randevu durumu ve not güncellenebilir.
- Güncelleme sonrası ziyaretçi kartı, içeride olanlar listesi ve kayıtlı kişi bilgileri birlikte güncellenir.
- Yapılan değişiklik ziyaret hareketlerine not olarak kaydedilir.
- Yenilenen canlı panel parçalarında düzenleme ve plaka formatlama kontrolleri tekrar bağlanır.

DB notu:

- DB şema değişikliği yok.

Canlı durumu:

- 2026-05-02 tarihinde canlıya alındı.

## V1.8 - Yayın: 2026-05-02

Başlık: Hatalı şifre ve IP blok güvenliği

Özet:

Yanlış şifre denemeleri maille bildirilebilir; tekrarlı hatalarda IP adresi geçici veya kalıcı bloklanır.

Yenilikler:

- Yönetim ekranına hatalı giriş denemesi mail ayarı eklendi.
- Ayar aktifse yanlış kullanıcı adı veya şifre denemesinde belirlenen adrese mail gönderilir.
- Uyarı mailinde denenen kullanıcı adı/e-posta, tarih, IP adresi ve cihaz bilgisi yer alır.
- Hatalı giriş bildirimi oluşturulduğunda sadece ilgili mail logu hemen gönderilmeye çalışılır.
- Yönetim ekranındaki Tanımlar ve Kurallar kartları daha kompakt hale getirildi.
- Yönetim kartları sürükle bırak ile kullanıcıya göre sıralanabilir ve varsayılan sıraya döndürülebilir.
- Yanlış şifre denemelerinde IP bazlı güvenlik banı eklendi.
- İlk seride 5 hatalı denemede IP adresi 1 saat banlanır; süre dolunca otomatik açılır.
- Aynı IP ikinci seride 3 hatalı denemeye ulaşırsa kalıcı bloklanır.
- Admin panelindeki IP Blokları ekranından geçici veya kalıcı bloklar manuel açılabilir.
- Kurulum sihirbazında `schema.sql` dosyası girilen veritabanı üzerinde kalacak şekilde güçlendirildi.

DB notu:

- `login_ip_blocks` tablosu eklendi.
- Ayarlar `app_settings` içinde `security.failed_login_alert_enabled` ve `security.failed_login_alert_email` anahtarlarıyla tutulur.
- Gönderim kayıtları mevcut `notification_logs` tablosunu kullanır.

Canlı durumu:

- 2026-05-02 tarihinde canlıya alındı.

## V1.7 - Yayın: 2026-05-02

Başlık: Gerçek Web Push Bildirimleri

Özet:

Yetkili kullanıcıların cihazları Push API ile abone edilir ve yeni girişlerde panel kapalıyken de bildirim gönderilebilir.

Yenilikler:

- Push API aboneliklerini tutmak için `push_subscriptions` tablosu eklendi.
- VAPID public/private anahtarları sistem ayarlarında otomatik üretilip saklanır.
- Telefon bildirimi yetkisi olan kullanıcılar cihazlarını gerçek Web Push için abone edebilir.
- Kullanıcı kartında giriş ve çıkış Web Push bildirimleri ayrı ayrı seçilebilir.
- Service worker `push` event dinleyerek panel kapalıyken gelen bildirimleri gösterir.
- Yeni giriş ve çıkış kaydı oluştuğunda destekleyen cihazlara gerçek Web Push gönderimi yapılır.
- Desteklemeyen cihazlarda mevcut açık-panel polling bildirimi yedek olarak çalışmaya devam eder.

DB notu:

- `push_subscriptions` tablosu eklendi.
- `users.mobile_notification_entry_enabled` ve `users.mobile_notification_exit_enabled` kolonları eklendi.
- `mobile_notification_logs.event_type` kolonu eklendi.
- `app_settings` içine `web_push.vapid_public_key`, `web_push.vapid_private_key`, `web_push.vapid_subject` ayarları ilk kullanımda otomatik yazılır.

Canlı durumu:

- 2026-05-02 tarihinde canlıya alındı.

## V1.6 - Yayın: 2026-05-02

Başlık: Canlı panel otomatik güncelleme

Özet:

Yeni giriş, çıkış ve canlı akış kayıtları sayfa yenilemeden panele düşer.

Yenilikler:

- Canlı panel heartbeat kontrolü artık canlı veri imzası döndürür.
- Sayaçlar, bekleyen onaylar, içeride olanlar ve canlı akış bölümleri arka planda yenilenir.
- Yeni giriş ve çıkış kayıtları için manuel sayfa yenileme ihtiyacı azaltıldı.
- Yeni eklenen satırlardaki sayaç, detay ve çıkış işlemleri otomatik bağlanır.
- Heartbeat ağır bildirim ve süre aşımı işlerini kilitli ve aralıklı çalıştırmaya devam eder.

DB notu:

- DB şema değişikliği yok.

Canlı durumu:

- 2026-05-02 tarihinde canlıya alındı.

## V1.5 - Yayın: 2026-05-02

Başlık: Performans iyileştirmeleri

Özet:

Canlı panel açılışı ve arka plan kontrol işleri hafifletildi.

Yenilikler:

- Canlı panel açılırken süre aşımı motoru doğrudan çalıştırılmayacak şekilde düzenlendi.
- Heartbeat arka plan işi kilitlenerek aynı anda birden fazla kullanıcıda tekrar tekrar çalışması engellendi.
- Heartbeat kontrol aralığı 20 saniyeye çıkarılarak DB yükü azaltıldı.
- Dashboard istatistik sorguları indeks dostu tarih aralığı kullanacak şekilde düzenlendi.
- Kayıtlı kişi önerilerinde son ziyaret bilgisi daha verimli sorgulanacak şekilde iyileştirildi.
- Giriş çıkış kayıtları sorgusunda tarih filtresi iç sorgulara taşınarak büyük veri taraması azaltıldı.
- POST formlarında tekrar tıklamayı önlemek için 5 saniyelik bekleme kilidi ve buton geri sayımı eklendi.

DB notu:

- DB şema değişikliği yok.

Canlı durumu:

- 2026-05-02 tarihinde canlıya alındı.

## V1.4 - Yayın: 2026-05-02

Başlık: Hesap güvenliği ve rezervasyonsuz giriş akışı

Özet:

Kullanıcı şifre değiştirme, yeni kullanıcı giriş maili, rezervasyonsuz giriş oda notu ve hareket bazlı kayıt listesi eklendi.

Yenilikler:

- Giriş yapan kullanıcılar üst menüden kendi şifrelerini değiştirebilir.
- Yeni kullanıcı oluşturulduğunda giriş linki, kullanıcı adı ve geçici şifre profesyonel mail olarak gönderilir.
- Rezervasyonsuz Giriş kategorisinde seçilen departman yöneticisine oda ve not formu linki gönderilir.
- Oda ve not formu kaydedildiğinde bilgi tüm departman yöneticilerine mail olarak dağıtılır.
- Giriş Çıkış Kayıtları ekranı artık giriş ve çıkış hareketlerini ayrı satırlar halinde gösterir.

DB notu:

- `reservationless_reviews` tablosu eklendi.

Canlı durumu:

- 2026-05-02 tarihinde canlıya alındı.

## V1.3 - Yayın: 2026-05-02

Başlık: Mobil telefon bildirimleri

Özet:

Yetki verilen kullanıcılar telefondan giriş yaptığında bildirim izni alır ve yeni giriş kayıtlarında cihaz bildirimi görür.

Yenilikler:

- Kullanıcı yönetimine Telefon / PWA bildirimi yetkisi eklendi.
- Yetki verilen kullanıcı giriş yaptığında cihaz bildirimi izni isteyen kullanıcı dostu bant gösterilir.
- Yeni giriş kaydı oluşturulduğunda yetkili kullanıcılar için mobil bildirim kuyruğu oluşur.
- Telefon veya PWA açıkken bildirim kuyruğu otomatik kontrol edilir ve sistem bildirimi gösterilir.
- Bildirim tıklanınca canlı panele yönlendirme yapılır.

DB notu:

- `users.mobile_notification_enabled` kolonu eklendi.
- `mobile_notification_logs` tablosu eklendi.

Canlı durumu:

- 2026-05-02 tarihinde canlıya alındı.

## V1.2 - Yayın: 2026-05-02

Başlık: Sürüm, öneri ve bilgilendirme iyileştirmeleri

Özet:

Versiyon duyuruları, kullanıcı önerileri, şifre sıfırlama uyarıları ve sürüm geçmişi ekranları geliştirildi.

Yenilikler:

- Aktif ve e-posta adresi kayıtlı kullanıcılara sürüm yenilik maili gönderilebilir.
- Aynı sürüm için tekrar mail gitmemesi amacıyla son bildirilen sürüm sistem ayarlarında tutulur.
- Mail bildirimi mevcut Mail kanal ayarlarını ve bildirim kuyruğunu kullanır.
- Canlı sürüm yayınında bildirim gönderimi için `bin/version-notify.php` komutu eklendi.
- Kullanıcıların üst menüden öneri, hata veya eğitim ihtiyacı gönderebileceği pencere eklendi.
- Yönetim paneline kullanıcı önerilerini filtreleme, durumlandırma ve silme ekranı eklendi.
- Kullanıcı önerileri kaydedildiğinde `info@bigabilisim.com` adresine otomatik mail bildirimi gönderilir.
- Şifre sıfırlama mail kanalı hazır değilse kullanıcıya daha net uyarı gösterilir.
- Giriş ekranındaki sürüm kutusu son 5 sürümü gösterecek şekilde düzenlendi.
- Tüm sürüm geçmişini düz metin olarak gösteren ayrı sayfa eklendi.

DB notu:

- `user_suggestions` tablosu eklendi.

Kullanım:

- Ön izleme: `php bin/version-notify.php`
- Yayında mail kuyruğuna ekleme: `php bin/version-notify.php --send`
- Kuyruğa ekleyip hemen gönderim denemesi: `php bin/version-notify.php --send --process`

Canlı durumu:

- 2026-05-02 tarihinde canlıya alındı.

## V1.1 - Yayın: 2026-05-02

Başlık: Kullanım kılavuzu ve şifre sıfırlama

Yenilikler:

- Giriş ekranına Kullanım Kılavuzu butonu eklendi.
- Kılavuz, kullanıcı girişi olmadan görüntülenebilir hale getirildi.
- Şifremi Unuttum ekranı ve e-posta ile şifre sıfırlama akışı eklendi.
- Şifre sıfırlama bağlantıları 60 dakika geçerli ve tek kullanımlık olacak şekilde düzenlendi.
- Şifremi Unuttum butonuna outline balık simgesi eklendi.
- Giriş ekranına güncel sürüm yeniliklerini gösteren kutu eklendi.

DB notu:

- `password_reset_tokens` tablosu eklendi.

Canlı durumu:

- 2026-05-02 tarihinde canlıya alındı.

## V1 - Yayın: 2026-05-02

Başlık: İlk canlı referans sürüm

Kapsam:

- Canlı giriş ve çıkış takibi.
- Kategori, departman, kullanıcı ve yetki yönetimi.
- Mail, Telegram ve WhatsApp kanal ayarları.
- Bildirim kuralları ve eskalasyon zinciri.
- Kayıt listesi, PDF rapor ve planlı raporlar.
- Kara liste, uyarı listesi ve kayıtlı kişiler ekranı.
- Otomatik yedekleme ve kurulum sihirbazı.
- Kullanıcı bazlı yönetim paneli yetkileri.

Canlı durumu:

- Canlı referans sürüm.
