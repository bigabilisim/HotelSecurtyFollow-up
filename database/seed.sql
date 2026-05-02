USE hotel_security;

INSERT INTO roles (code, name, description, is_system) VALUES
  ('owner', 'Patron', 'Tesis sahibi veya üst yönetim kullanıcısı.', 1),
  ('general_manager', 'Genel Müdür', 'Tüm rapor ve kritik bildirimleri görebilen yönetici.', 1),
  ('operation_manager', 'Operasyon Müdürü', 'Operasyon süreçlerini, raporları ve kritik bildirimleri takip eder.', 1),
  ('night_manager', 'Gece Müdürü', 'Gece vardiyasında giriş çıkışları, doğrulamaları ve raporları takip eder.', 1),
  ('department_manager', 'Departman Müdürü', 'Kendi departmanına gelen ziyaretçileri doğrular.', 1),
  ('security', 'Güvenlik Personeli', 'Giriş ve çıkış kaydı oluşturur.', 1),
  ('admin', 'Sistem Yöneticisi', 'Kurulum, kullanıcı, kategori, bildirim ve yedek ayarlarını yönetir.', 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  is_system = VALUES(is_system);

INSERT INTO permissions (code, name, module, description) VALUES
  ('dashboard.view', 'Paneli Görüntüle', 'dashboard', 'Canlı güvenlik panelini görüntüler.'),
  ('visits.create_entry', 'Giriş Kaydı Oluştur', 'visits', 'Ziyaretçi girişi oluşturur.'),
  ('visits.create_exit', 'Çıkış Kaydı Oluştur', 'visits', 'Ziyaretçi çıkışı oluşturur.'),
  ('visits.view_all', 'Tüm Kayıtları Gör', 'visits', 'Tüm giriş çıkış kayıtlarını görüntüler.'),
  ('visits.department_verify', 'Departman Doğrulaması Yap', 'visits', 'Süre aşımı sorusunu cevaplar.'),
  ('categories.manage', 'Kategori Yönet', 'categories', 'Ziyaretçi kategorilerini tanımlar.'),
  ('departments.manage', 'Departman Yönet', 'departments', 'Departman ve amir bilgilerini tanımlar.'),
  ('users.manage', 'Kullanıcı Yönet', 'users', 'Kullanıcı, rol ve yetki ayarlarını yönetir.'),
  ('notifications.manage', 'Bildirim Kuralı Yönet', 'notifications', 'Telegram, WhatsApp ve mail kurallarını yönetir.'),
  ('reports.view', 'Rapor Gör', 'reports', 'Günlük ve aylık raporları görüntüler.'),
  ('reports.manage', 'Rapor Ayarı Yönet', 'reports', 'Rapor planları ve alıcılarını yönetir.'),
  ('backups.manage', 'Yedek Yönet', 'backups', 'Yedek alma ve geri yükleme işlemlerini yönetir.'),
  ('settings.manage', 'Sistem Ayarı Yönet', 'settings', 'Kurulum sihirbazı ve genel sistem ayarlarını yönetir.'),
  ('visitors.manage', 'Kayıtlı Kişiler Paneli', 'visitors', 'Kayıtlı kişi kartlarını yönetir.'),
  ('watchlist.manage', 'Kara / Uyarı Listesi Paneli', 'watchlist', 'Kara liste ve uyarı listesi kayıtlarını yönetir.'),
  ('notification_rules.manage', 'Bildirim Kuralları Paneli', 'notifications', 'Bildirim kuralı panelini yönetir.'),
  ('mail_templates.manage', 'Mail Şablonları Paneli', 'notifications', 'Mail şablonları panelini yönetir.'),
  ('notification_channels.manage', 'Kanal Ayarları Paneli', 'notifications', 'Mail, Telegram ve WhatsApp kanal ayarlarını yönetir.')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  module = VALUES(module),
  description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p
WHERE r.code = 'admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN (
  'dashboard.view',
  'visits.view_all',
  'reports.view',
  'notifications.manage',
  'notification_rules.manage',
  'mail_templates.manage',
  'notification_channels.manage'
)
WHERE r.code = 'owner';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN (
  'dashboard.view',
  'visits.view_all',
  'reports.view',
  'notifications.manage',
  'notification_rules.manage',
  'mail_templates.manage',
  'notification_channels.manage',
  'departments.manage'
)
WHERE r.code = 'general_manager';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN (
  'dashboard.view',
  'visits.view_all',
  'reports.view',
  'departments.manage',
  'notifications.manage',
  'notification_rules.manage',
  'mail_templates.manage',
  'notification_channels.manage'
)
WHERE r.code = 'operation_manager';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN (
  'dashboard.view',
  'visits.create_entry',
  'visits.create_exit',
  'visits.view_all',
  'visits.department_verify',
  'reports.view'
)
WHERE r.code = 'night_manager';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN (
  'dashboard.view',
  'visits.department_verify',
  'reports.view'
)
WHERE r.code = 'department_manager';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN (
  'dashboard.view',
  'visits.create_entry',
  'visits.create_exit',
  'visits.view_all'
)
WHERE r.code = 'security';

INSERT INTO departments (code, name, email, phone, status) VALUES
  ('GENEL', 'Genel Müdürlük', NULL, NULL, 'active'),
  ('GUVENLIK', 'Güvenlik', NULL, NULL, 'active'),
  ('SATINALMA', 'Satın Alma', NULL, NULL, 'active'),
  ('TEKNIK', 'Teknik Servis', NULL, NULL, 'active'),
  ('IK', 'İnsan Kaynakları', NULL, NULL, 'active'),
  ('ONBURO', 'Ön Büro', NULL, NULL, 'active')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  status = VALUES(status);

INSERT INTO visitor_categories (
  code,
  name,
  color,
  max_duration_minutes,
  warning_before_minutes,
  requires_department_approval,
  escalation_after_minutes,
  is_notification_enabled,
  is_quick_access,
  quick_access_order,
  escalation_level_1_user_id,
  escalation_level_1_after_minutes,
  escalation_level_2_user_id,
  escalation_level_2_after_minutes,
  escalation_level_3_user_id,
  escalation_level_3_after_minutes,
  status
) VALUES
  ('VIP', 'VIP', '#6d4bc2', NULL, 0, 0, 0, 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active'),
  ('REZERVASYONSUZ_GIRIS', 'Rezervasyonsuz Giriş', '#0f766e', 30, 5, 1, 5, 1, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, 'active'),
  ('TEDARIKCI', 'Tedarikçi', '#c36a13', 60, 10, 1, 5, 1, 1, 2, NULL, NULL, NULL, NULL, NULL, NULL, 'active'),
  ('GUNUBIRLIK', 'Günübirlik', '#2563eb', 480, 30, 1, 10, 1, 1, 3, NULL, NULL, NULL, NULL, NULL, NULL, 'active'),
  ('ACENTA', 'Acenta', '#7c3aed', 120, 15, 1, 5, 1, 1, 4, NULL, NULL, NULL, NULL, NULL, NULL, 'active'),
  ('IS_GORUSMESI', 'İş Görüşmesi', '#0f766e', 60, 10, 1, 5, 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active'),
  ('ZIYARETCI', 'Ziyaretçi', '#0f766e', 120, 15, 1, 5, 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active'),
  ('TEKNIK_SERVIS', 'Teknik Servis', '#334155', 90, 10, 1, 5, 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active'),
  ('ANIMASYON', 'Animasyon', '#db2777', 180, 15, 1, 5, 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active'),
  ('DENETCI', 'Denetçi', '#b3261e', 30, 5, 1, 3, 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  color = VALUES(color),
  max_duration_minutes = VALUES(max_duration_minutes),
  warning_before_minutes = VALUES(warning_before_minutes),
  requires_department_approval = VALUES(requires_department_approval),
  escalation_after_minutes = VALUES(escalation_after_minutes),
  is_notification_enabled = VALUES(is_notification_enabled),
  is_quick_access = VALUES(is_quick_access),
  quick_access_order = VALUES(quick_access_order),
  escalation_level_1_user_id = VALUES(escalation_level_1_user_id),
  escalation_level_1_after_minutes = VALUES(escalation_level_1_after_minutes),
  escalation_level_2_user_id = VALUES(escalation_level_2_user_id),
  escalation_level_2_after_minutes = VALUES(escalation_level_2_after_minutes),
  escalation_level_3_user_id = VALUES(escalation_level_3_user_id),
  escalation_level_3_after_minutes = VALUES(escalation_level_3_after_minutes),
  status = VALUES(status);

INSERT INTO notification_channels (code, name, is_enabled, config_json) VALUES
  ('mail', 'Mail', 1, '{"driver":"php_mail","configured":false,"from_email":"security@hotel.local","from_name":"Otel Güvenlik"}'),
  ('telegram', 'Telegram', 1, '{"driver":"telegram_bot_api","configured":false,"bot_token":"","parse_mode":""}'),
  ('whatsapp', 'WhatsApp', 1, '{"driver":"whatsapp_provider","configured":false,"provider":"meta_cloud","api_version":"v20.0","phone_number_id":"","access_token":"","endpoint":""}')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  is_enabled = VALUES(is_enabled),
  config_json = VALUES(config_json);

INSERT INTO notification_rules (category_id, name, event_type, priority, message_template, is_active)
SELECT c.id, CONCAT(c.name, ' giriş bildirimi'), 'entry', 100,
       '{visitor_name} {category_name} kategorisiyle giriş yaptı. Departman: {department_name}. Giriş saati: {entry_at}.',
       1
FROM visitor_categories c
WHERE c.code IN ('VIP', 'DENETCI')
ON DUPLICATE KEY UPDATE
  priority = VALUES(priority),
  message_template = VALUES(message_template),
  is_active = VALUES(is_active);

INSERT IGNORE INTO notification_rule_channels (rule_id, channel_id)
SELECT nr.id, nc.id
FROM notification_rules nr
INNER JOIN visitor_categories c ON c.id = nr.category_id
INNER JOIN notification_channels nc ON nc.code IN ('mail', 'telegram', 'whatsapp')
WHERE nr.event_type = 'entry'
  AND c.code IN ('VIP', 'DENETCI');

INSERT INTO notification_rule_recipients (rule_id, recipient_type, role_id)
SELECT nr.id, 'role', r.id
FROM notification_rules nr
INNER JOIN visitor_categories c ON c.id = nr.category_id
INNER JOIN roles r ON r.code IN ('owner', 'general_manager')
WHERE nr.event_type = 'entry'
  AND c.code IN ('VIP', 'DENETCI')
  AND NOT EXISTS (
    SELECT 1
    FROM notification_rule_recipients nrr
    WHERE nrr.rule_id = nr.id
      AND nrr.recipient_type = 'role'
      AND nrr.role_id = r.id
  );

INSERT INTO notification_rules (category_id, name, event_type, priority, message_template, is_active)
SELECT NULL, 'Süre aşımı departman sorusu', 'department_question', 80,
       '{question_text} Ziyaretçi: {visitor_name}. Departman: {department_name}. Giriş: {entry_at}.',
       1
WHERE NOT EXISTS (
  SELECT 1 FROM notification_rules WHERE name = 'Süre aşımı departman sorusu' AND event_type = 'department_question' AND category_id IS NULL
);

INSERT INTO notification_rules (category_id, name, event_type, priority, message_template, is_active)
SELECT NULL, 'Departman hayır eskalasyonu', 'department_answer_no', 50,
       '{visitor_name} için departman cevabı olumsuz. Yönetici kontrolü gerekiyor. Departman: {department_name}.',
       1
WHERE NOT EXISTS (
  SELECT 1 FROM notification_rules WHERE name = 'Departman hayır eskalasyonu' AND event_type = 'department_answer_no' AND category_id IS NULL
);

INSERT INTO notification_rules (category_id, name, event_type, priority, message_template, is_active)
SELECT NULL, 'Departman cevapsız eskalasyonu', 'department_no_response', 60,
       '{visitor_name} için departmandan cevap alınamadı. Yönetici kontrolü gerekiyor. Departman: {department_name}.',
       1
WHERE NOT EXISTS (
  SELECT 1 FROM notification_rules WHERE name = 'Departman cevapsız eskalasyonu' AND event_type = 'department_no_response' AND category_id IS NULL
);

INSERT INTO notification_rules (category_id, name, event_type, priority, message_template, is_active)
SELECT NULL, 'Gün sonu rapor bildirimi', 'daily_report', 120,
       '{report_name} hazırlandı. Dönem: {period_start} - {period_end}. {report_summary}. Dosya: {file_path}',
       1
WHERE NOT EXISTS (
  SELECT 1 FROM notification_rules WHERE name = 'Gün sonu rapor bildirimi' AND event_type = 'daily_report' AND category_id IS NULL
);

INSERT INTO notification_rules (category_id, name, event_type, priority, message_template, is_active)
SELECT NULL, 'Ay sonu rapor bildirimi', 'monthly_report', 120,
       '{report_name} hazırlandı. Dönem: {period_start} - {period_end}. {report_summary}. Dosya: {file_path}',
       1
WHERE NOT EXISTS (
  SELECT 1 FROM notification_rules WHERE name = 'Ay sonu rapor bildirimi' AND event_type = 'monthly_report' AND category_id IS NULL
);

INSERT IGNORE INTO notification_rule_channels (rule_id, channel_id)
SELECT nr.id, nc.id
FROM notification_rules nr
INNER JOIN notification_channels nc ON nc.code IN ('mail', 'telegram', 'whatsapp')
WHERE nr.name IN (
  'Süre aşımı departman sorusu',
  'Departman hayır eskalasyonu',
  'Departman cevapsız eskalasyonu',
  'Gün sonu rapor bildirimi',
  'Ay sonu rapor bildirimi'
);

INSERT INTO notification_rule_recipients (rule_id, recipient_type)
SELECT nr.id, 'department_manager'
FROM notification_rules nr
WHERE nr.name = 'Süre aşımı departman sorusu'
  AND NOT EXISTS (
    SELECT 1
    FROM notification_rule_recipients nrr
    WHERE nrr.rule_id = nr.id
      AND nrr.recipient_type = 'department_manager'
  );

INSERT INTO notification_rule_recipients (rule_id, recipient_type, role_id)
SELECT nr.id, 'role', r.id
FROM notification_rules nr
INNER JOIN roles r ON r.code IN ('owner', 'general_manager')
WHERE nr.name IN (
  'Departman hayır eskalasyonu',
  'Departman cevapsız eskalasyonu',
  'Gün sonu rapor bildirimi',
  'Ay sonu rapor bildirimi'
)
  AND NOT EXISTS (
    SELECT 1
    FROM notification_rule_recipients nrr
    WHERE nrr.rule_id = nr.id
      AND nrr.recipient_type = 'role'
      AND nrr.role_id = r.id
  );

INSERT INTO report_schedules (name, report_type, run_time, day_of_month, output_format, is_active) VALUES
  ('Gün sonu giriş çıkış raporu', 'daily', '23:30:00', NULL, 'html', 1),
  ('Haftalık giriş çıkış raporu', 'weekly', '23:40:00', NULL, 'html', 1),
  ('Ay sonu giriş çıkış raporu', 'monthly', '23:45:00', 1, 'html', 1)
ON DUPLICATE KEY UPDATE
  run_time = VALUES(run_time),
  output_format = VALUES(output_format),
  is_active = VALUES(is_active);

INSERT INTO backup_jobs (
  name,
  frequency,
  run_time,
  backup_target,
  target_config_json,
  retention_days,
  include_database,
  include_uploads,
  mail_enabled,
  mail_recipient_name,
  mail_recipient_email,
  is_active
) VALUES (
  'Günlük otomatik sistem yedeği',
  'daily',
  '23:30:00',
  'local',
  '{"path":"storage/backups","compress":true}',
  30,
  1,
  1,
  1,
  '',
  '',
  1
)
ON DUPLICATE KEY UPDATE
  frequency = VALUES(frequency),
  run_time = VALUES(run_time),
  backup_target = VALUES(backup_target),
  target_config_json = VALUES(target_config_json),
  retention_days = VALUES(retention_days),
  include_database = VALUES(include_database),
  include_uploads = VALUES(include_uploads),
  mail_enabled = VALUES(mail_enabled),
  is_active = VALUES(is_active);

INSERT INTO app_settings (setting_key, setting_value, is_encrypted) VALUES
  ('app.name', 'Otel Güvenlik Sistemi', 0),
  ('app.domain', 'security.hotel.local', 0),
  ('app.logo_text', 'OG', 0),
  ('setup.completed', '0', 0)
ON DUPLICATE KEY UPDATE
  setting_value = VALUES(setting_value),
  is_encrypted = VALUES(is_encrypted);

INSERT IGNORE INTO app_settings (setting_key, setting_value, is_encrypted) VALUES
  ('escalation.level_1_user_id', '0', 0),
  ('escalation.level_1_after_minutes', '5', 0),
  ('escalation.level_2_user_id', '0', 0),
  ('escalation.level_2_after_minutes', '10', 0),
  ('escalation.level_3_user_id', '0', 0),
  ('escalation.level_3_after_minutes', '15', 0),
  ('security.failed_login_alert_enabled', '0', 0),
  ('security.failed_login_alert_email', '', 0);
