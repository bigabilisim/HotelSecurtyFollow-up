# Bildirim Servisleri

Bu bölümde giriş ve çıkış olayları için çalışan bildirim altyapısı eklendi.

## Akış

1. Güvenlik panelinden giriş veya çıkış kaydı oluşturulur.
2. `NotificationService` ilgili olay tipine göre aktif kuralları bulur.
3. Kuralın kategorisi, koşulları, kanalları ve alıcıları çözülür.
4. Her kanal ve alıcı için `notification_logs` tablosuna kayıt açılır.
5. `bin/notifications-work.php` kuyruktaki kayıtları gönderir.

## Desteklenen Kanallar

| Kanal | Adaptör | Ayar |
| --- | --- | --- |
| Mail | `MailSender` | Panelden `php_mail` veya `smtp`; SMTP için host, port, şifreleme, kullanıcı, şifre, gönderen mail/ad |
| Telegram | `TelegramSender` | `configured`, `bot_token`, isteğe bağlı `parse_mode` |
| WhatsApp | `WhatsAppSender` | `configured`, `endpoint`, `access_token`; Meta Cloud için `api_version` ve `phone_number_id` |

Kanal ayarı tamamlanmadıysa kayıt `skipped` durumuna alınır. Böylece sistem giriş/çıkış akışını bozmaz, eksik yapılandırma da loglarda görünür.

Mail kanalı, Yönetim > Mail Şablonları ekranındaki konu ve gövde şablonlarını kullanır. Ekran tıklanabilir değişkenler ve canlı ön izleme ile temel kullanıcıya göre sadeleştirilmiştir. Bu ekran yalnızca mail metinlerini değiştirir; Telegram ve WhatsApp için bildirim kuralındaki mesaj şablonu geçerlidir.

## Kuyruk İşleyici

Elle çalıştırma:

```bash
php bin/notifications-work.php --limit=50
```

Sunucuda dakika bazlı cron örneği:

```bash
* * * * * /usr/bin/php /var/www/otel-guvenlik/bin/notifications-work.php --limit=50
```

İşleyici `queued` ve en fazla 3 denemesi olan `failed` kayıtları işler. Sonuçlar `sent`, `failed` veya `skipped` olarak `notification_logs` tablosuna yazılır.

Kayıtlar ekranından veya PDF formatlı rapor planından oluşturulan PDF dosyaları Mail kanalında ekli dosya olarak gönderilir. Bu kayıtlar da aynı `notification_logs` kuyruğundan geçer.

## Web Push Bildirimleri

V1.7 ile telefon bildirimi yetkisi olan kullanıcılar gerçek Web Push aboneliği alabilir. Tarayıcı destekliyorsa kullanıcı bildirim izni verdiğinde cihaz aboneliği `push_subscriptions` tablosuna kaydedilir. VAPID public/private anahtarları ilk kullanımda otomatik üretilir ve `app_settings` içinde saklanır.

Kullanıcı kartında giriş ve çıkış olayları ayrı ayrı seçilebilir. Yeni giriş veya çıkış kaydı oluştuğunda sistem önce Web Push endpointlerine bildirim göndermeyi dener. Desteklemeyen cihazlarda veya abonelik alınmamış kullanıcılarda eski açık-panel polling bildirimi yedek olarak devam eder.

## Süre Aşımı İşleyici

```bash
php bin/timeouts-work.php --limit=100
```

Bu komut süre yaklaşma uyarısı, departman sorusu ve cevapsızlık eskalasyonlarını üretir.

Departman sorusu ve N+ eskalasyon mesajlarında güvenli Evet/Hayır cevap bağlantıları üretilir. Mail kanalında bu bağlantılar buton olarak, Telegram kanalında inline buton olarak, WhatsApp kanalında ise mesaj metnine eklenen Evet/Hayır linkleri olarak gönderilir. Bağlantıya basıldığında ilgili doğrulama kaydı panel girişi gerektirmeden cevaplanır.

## Rapor ve Yedek İşleyicileri

```bash
php bin/reports-work.php --limit=10
php bin/backups-work.php --limit=5
```

Rapor işleyici günlük, haftalık ve aylık HTML dosyası üretir. Rapor bildirim kurallarını kuyruğa alır; ayrıca kullanıcı kartında ilgili rapor tiki açık olan aktif kullanıcılara Mail kanalı üzerinden otomatik rapor kuyruğu oluşturur. Yedek işleyici aktif yedek planlarını çalıştırır.

## Mesaj Şablonu Alanları

Kural mesajlarında şu alanlar kullanılabilir:

- `{visitor_name}`
- `{visitor_phone}`
- `{company}`
- `{vehicle_plate}`
- `{category_name}`
- `{category_code}`
- `{department_name}`
- `{department_code}`
- `{host_name}`
- `{purpose}`
- `{entry_at}`
- `{exit_at}`
- `{elapsed_minutes}`

Mail şablonlarında bunlara ek olarak `{subject}`, `{message}`, `{question_text}`, `{report_name}`, `{report_summary}`, `{period_start}`, `{period_end}` ve `{file_path}` alanları da kullanılabilir.

## Varsayılan Seed

`database/seed.sql` VIP ve Denetçi giriş bildirimleri için başlangıç kuralları oluşturur. Bu kurallar Mail, Telegram ve WhatsApp kanallarına bağlıdır; alıcı olarak Patron ve Genel Müdür rolleri atanır.

Gerçek gönderim için yönetim panelinden kullanıcı e-postaları/telefonları, Telegram Chat ID veya WhatsApp numarası gibi alıcı bilgileri ve Mail / Telegram / WhatsApp kanal ayarları doldurulmalıdır.
