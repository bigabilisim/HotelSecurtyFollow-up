CREATE DATABASE IF NOT EXISTS hotel_security
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE hotel_security;

CREATE TABLE IF NOT EXISTS app_settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value LONGTEXT NULL,
  is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(80) NOT NULL,
  name VARCHAR(140) NOT NULL,
  module VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_permissions_code (code),
  KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_role_permissions_role
    FOREIGN KEY (role_id) REFERENCES roles(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_role_permissions_permission
    FOREIGN KEY (permission_id) REFERENCES permissions(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS departments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id BIGINT UNSIGNED NULL,
  manager_user_id BIGINT UNSIGNED NULL,
  code VARCHAR(50) NOT NULL,
  name VARCHAR(140) NOT NULL,
  email VARCHAR(180) NULL,
  phone VARCHAR(40) NULL,
  status ENUM('active', 'passive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_departments_code (code),
  KEY idx_departments_parent (parent_id),
  KEY idx_departments_manager_user (manager_user_id),
  CONSTRAINT fk_departments_parent
    FOREIGN KEY (parent_id) REFERENCES departments(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  department_id BIGINT UNSIGNED NULL,
  full_name VARCHAR(160) NOT NULL,
  username VARCHAR(80) NOT NULL,
  email VARCHAR(180) NULL,
  phone VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('active', 'passive', 'locked') NOT NULL DEFAULT 'active',
  report_daily_enabled TINYINT(1) NOT NULL DEFAULT 0,
  report_weekly_enabled TINYINT(1) NOT NULL DEFAULT 0,
  report_monthly_enabled TINYINT(1) NOT NULL DEFAULT 0,
  mobile_notification_enabled TINYINT(1) NOT NULL DEFAULT 0,
  mobile_notification_entry_enabled TINYINT(1) NOT NULL DEFAULT 1,
  mobile_notification_exit_enabled TINYINT(1) NOT NULL DEFAULT 0,
  mobile_notification_department_enabled TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_username (username),
  UNIQUE KEY uk_users_email (email),
  KEY idx_users_department (department_id),
  KEY idx_users_status (status),
  CONSTRAINT fk_users_department
    FOREIGN KEY (department_id) REFERENCES departments(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @department_manager_fk_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'departments'
    AND CONSTRAINT_NAME = 'fk_departments_manager_user'
);

SET @department_manager_fk_sql := IF(
  @department_manager_fk_exists = 0,
  'ALTER TABLE departments ADD CONSTRAINT fk_departments_manager_user FOREIGN KEY (manager_user_id) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1'
);

PREPARE department_manager_fk_stmt FROM @department_manager_fk_sql;
EXECUTE department_manager_fk_stmt;
DEALLOCATE PREPARE department_manager_fk_stmt;

CREATE TABLE IF NOT EXISTS user_roles (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT fk_user_roles_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_user_roles_role
    FOREIGN KEY (role_id) REFERENCES roles(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_permission_overrides (
  user_id BIGINT UNSIGNED NOT NULL,
  permission_code VARCHAR(80) NOT NULL,
  is_allowed TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, permission_code),
  KEY idx_user_permission_overrides_code (permission_code),
  CONSTRAINT fk_user_permission_overrides_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  email VARCHAR(180) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  used_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_password_reset_tokens_hash (token_hash),
  KEY idx_password_reset_tokens_email (email),
  KEY idx_password_reset_tokens_user_status (user_id, used_at, expires_at),
  CONSTRAINT fk_password_reset_tokens_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_ip_blocks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_address VARCHAR(45) NOT NULL,
  failed_attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  total_failed_attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
  ban_count INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('watching', 'temporary', 'permanent') NOT NULL DEFAULT 'watching',
  blocked_until DATETIME NULL,
  first_failed_at DATETIME NULL,
  last_failed_at DATETIME NULL,
  last_username VARCHAR(180) NULL,
  last_user_agent VARCHAR(255) NULL,
  last_block_reason VARCHAR(255) NULL,
  released_at DATETIME NULL,
  released_by_user_id BIGINT UNSIGNED NULL,
  release_note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_login_ip_blocks_ip (ip_address),
  KEY idx_login_ip_blocks_status (status, blocked_until),
  KEY idx_login_ip_blocks_last_failed (last_failed_at),
  KEY idx_login_ip_blocks_released_by (released_by_user_id),
  CONSTRAINT fk_login_ip_blocks_released_by
    FOREIGN KEY (released_by_user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_suggestions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  user_name VARCHAR(160) NULL,
  user_email VARCHAR(180) NULL,
  suggestion_type ENUM('improvement', 'bug', 'feature', 'support') NOT NULL DEFAULT 'improvement',
  priority ENUM('normal', 'high') NOT NULL DEFAULT 'normal',
  title VARCHAR(160) NOT NULL,
  message TEXT NOT NULL,
  page_route VARCHAR(255) NULL,
  status ENUM('new', 'reviewing', 'done', 'rejected') NOT NULL DEFAULT 'new',
  handled_by_user_id BIGINT UNSIGNED NULL,
  handled_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_user_suggestions_user (user_id),
  KEY idx_user_suggestions_status (status, created_at),
  KEY idx_user_suggestions_type (suggestion_type),
  KEY idx_user_suggestions_deleted (deleted_at),
  CONSTRAINT fk_user_suggestions_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_user_suggestions_handler
    FOREIGN KEY (handled_by_user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visitor_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL,
  name VARCHAR(120) NOT NULL,
  color VARCHAR(20) NOT NULL DEFAULT '#0f766e',
  max_duration_minutes INT UNSIGNED NULL,
  warning_before_minutes INT UNSIGNED NOT NULL DEFAULT 10,
  requires_department_approval TINYINT(1) NOT NULL DEFAULT 1,
  escalation_after_minutes INT UNSIGNED NOT NULL DEFAULT 5,
  is_notification_enabled TINYINT(1) NOT NULL DEFAULT 1,
  is_quick_access TINYINT(1) NOT NULL DEFAULT 0,
  quick_access_order TINYINT UNSIGNED NULL,
  escalation_level_1_user_id BIGINT UNSIGNED NULL,
  escalation_level_1_after_minutes INT UNSIGNED NULL,
  escalation_level_2_user_id BIGINT UNSIGNED NULL,
  escalation_level_2_after_minutes INT UNSIGNED NULL,
  escalation_level_3_user_id BIGINT UNSIGNED NULL,
  escalation_level_3_after_minutes INT UNSIGNED NULL,
  status ENUM('active', 'passive') NOT NULL DEFAULT 'active',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_visitor_categories_code (code),
  KEY idx_visitor_categories_status (status),
  KEY idx_visitor_categories_quick_access (is_quick_access, quick_access_order),
  KEY idx_visitor_categories_escalation_1_user (escalation_level_1_user_id),
  KEY idx_visitor_categories_escalation_2_user (escalation_level_2_user_id),
  KEY idx_visitor_categories_escalation_3_user (escalation_level_3_user_id),
  CONSTRAINT fk_visitor_categories_created_by
    FOREIGN KEY (created_by) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visitors (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(160) NOT NULL,
  normalized_name VARCHAR(160) NOT NULL,
  phone VARCHAR(40) NULL,
  company VARCHAR(180) NULL,
  vehicle_plate VARCHAR(40) NULL,
  note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_visitors_normalized_name (normalized_name),
  KEY idx_visitors_phone (phone),
  KEY idx_visitors_vehicle_plate (vehicle_plate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS watchlist_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  list_type ENUM('blacklist', 'warning') NOT NULL DEFAULT 'warning',
  match_type ENUM('name', 'phone', 'plate') NOT NULL DEFAULT 'name',
  match_value VARCHAR(180) NOT NULL,
  normalized_value VARCHAR(180) NOT NULL,
  reason TEXT NULL,
  action_note VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_watchlist_lookup (match_type, normalized_value),
  KEY idx_watchlist_type (list_type, is_active),
  KEY idx_watchlist_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  visitor_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  department_id BIGINT UNSIGNED NULL,
  host_user_id BIGINT UNSIGNED NULL,
  host_name VARCHAR(160) NULL,
  purpose VARCHAR(255) NULL,
  appointment_status ENUM('walk_in', 'appointment') NOT NULL DEFAULT 'walk_in',
  entry_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  exit_at TIMESTAMP NULL,
  entry_user_id BIGINT UNSIGNED NULL,
  exit_user_id BIGINT UNSIGNED NULL,
  status ENUM(
    'inside',
    'exited',
    'overdue',
    'department_asked',
    'department_approved',
    'escalated',
    'cancelled'
  ) NOT NULL DEFAULT 'inside',
  max_duration_minutes_snapshot INT UNSIGNED NULL,
  department_question_sent_at TIMESTAMP NULL,
  department_answer ENUM('yes', 'no', 'no_response') NULL,
  department_answer_at TIMESTAMP NULL,
  department_answer_by_user_id BIGINT UNSIGNED NULL,
  current_escalation_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
  escalated_at TIMESTAMP NULL,
  entry_note TEXT NULL,
  exit_note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_visits_status (status),
  KEY idx_visits_entry_at (entry_at),
  KEY idx_visits_exit_at (exit_at),
  KEY idx_visits_visitor (visitor_id),
  KEY idx_visits_category (category_id),
  KEY idx_visits_department (department_id),
  CONSTRAINT fk_visits_visitor
    FOREIGN KEY (visitor_id) REFERENCES visitors(id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_visits_category
    FOREIGN KEY (category_id) REFERENCES visitor_categories(id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_visits_department
    FOREIGN KEY (department_id) REFERENCES departments(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_visits_host_user
    FOREIGN KEY (host_user_id) REFERENCES users(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_visits_entry_user
    FOREIGN KEY (entry_user_id) REFERENCES users(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_visits_exit_user
    FOREIGN KEY (exit_user_id) REFERENCES users(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_visits_department_answer_by
    FOREIGN KEY (department_answer_by_user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mobile_notification_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  visit_id BIGINT UNSIGNED NULL,
  title VARCHAR(160) NOT NULL,
  message TEXT NOT NULL,
  target_url VARCHAR(255) NULL,
  event_type ENUM('entry', 'exit', 'department_question', 'department_reminder', 'escalation') NOT NULL DEFAULT 'entry',
  status ENUM('queued', 'delivered', 'read', 'skipped') NOT NULL DEFAULT 'queued',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  delivered_at TIMESTAMP NULL,
  read_at TIMESTAMP NULL,
  deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_mobile_notifications_user_status (user_id, status, created_at),
  KEY idx_mobile_notifications_visit (visit_id),
  KEY idx_mobile_notifications_deleted (deleted_at),
  CONSTRAINT fk_mobile_notifications_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_mobile_notifications_visit
    FOREIGN KEY (visit_id) REFERENCES visits(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  endpoint_hash CHAR(64) NOT NULL,
  endpoint TEXT NOT NULL,
  public_key VARCHAR(255) NOT NULL,
  auth_token VARCHAR(255) NOT NULL,
  content_encoding VARCHAR(20) NOT NULL DEFAULT 'aes128gcm',
  user_agent VARCHAR(255) NULL,
  status ENUM('active', 'expired') NOT NULL DEFAULT 'active',
  last_seen_at TIMESTAMP NULL,
  last_success_at TIMESTAMP NULL,
  last_error_at TIMESTAMP NULL,
  error_message VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_push_subscriptions_endpoint_hash (endpoint_hash),
  KEY idx_push_subscriptions_user_status (user_id, status, deleted_at),
  CONSTRAINT fk_push_subscriptions_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservationless_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  visit_id BIGINT UNSIGNED NOT NULL,
  department_id BIGINT UNSIGNED NULL,
  requested_user_id BIGINT UNSIGNED NULL,
  response_token VARCHAR(64) NOT NULL,
  room_number VARCHAR(80) NULL,
  manager_note TEXT NULL,
  status ENUM('pending', 'submitted', 'cancelled') NOT NULL DEFAULT 'pending',
  requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  submitted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_reservationless_reviews_visit (visit_id),
  UNIQUE KEY uk_reservationless_reviews_token (response_token),
  KEY idx_reservationless_reviews_status (status, requested_at),
  KEY idx_reservationless_reviews_department (department_id),
  CONSTRAINT fk_reservationless_reviews_visit
    FOREIGN KEY (visit_id) REFERENCES visits(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_reservationless_reviews_department
    FOREIGN KEY (department_id) REFERENCES departments(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_reservationless_reviews_requested_user
    FOREIGN KEY (requested_user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visit_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  visit_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  event_type ENUM(
    'entry',
    'exit',
    'timeout_warning',
    'department_question',
    'department_answer_yes',
    'department_answer_no',
    'department_no_response',
    'escalation',
    'note'
  ) NOT NULL,
  event_note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_visit_events_visit (visit_id),
  KEY idx_visit_events_type (event_type),
  KEY idx_visit_events_created_at (created_at),
  CONSTRAINT fk_visit_events_visit
    FOREIGN KEY (visit_id) REFERENCES visits(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_visit_events_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS department_verifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  visit_id BIGINT UNSIGNED NOT NULL,
  department_id BIGINT UNSIGNED NULL,
  sent_to_user_id BIGINT UNSIGNED NULL,
  question_text VARCHAR(255) NOT NULL,
  response_token VARCHAR(64) NULL,
  escalation_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
  answer ENUM('yes', 'no', 'no_response') NULL,
  sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NULL,
  answered_at TIMESTAMP NULL,
  escalation_triggered TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_department_verifications_visit (visit_id),
  KEY idx_department_verifications_answer (answer),
  KEY idx_department_verifications_expires_at (expires_at),
  UNIQUE KEY uk_department_verifications_response_token (response_token),
  CONSTRAINT fk_department_verifications_visit
    FOREIGN KEY (visit_id) REFERENCES visits(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_department_verifications_department
    FOREIGN KEY (department_id) REFERENCES departments(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_department_verifications_sent_to_user
    FOREIGN KEY (sent_to_user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_channels (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL,
  name VARCHAR(120) NOT NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  config_json LONGTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_notification_channels_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_contacts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  department_id BIGINT UNSIGNED NULL,
  full_name VARCHAR(160) NOT NULL,
  title VARCHAR(120) NULL,
  email VARCHAR(180) NULL,
  phone VARCHAR(40) NULL,
  telegram_chat_id VARCHAR(120) NULL,
  whatsapp_number VARCHAR(40) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notification_contacts_department (department_id),
  KEY idx_notification_contacts_active (is_active),
  CONSTRAINT fk_notification_contacts_department
    FOREIGN KEY (department_id) REFERENCES departments(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_rules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id BIGINT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  event_type ENUM(
    'entry',
    'exit',
    'timeout_warning',
    'department_question',
    'department_answer_no',
    'department_no_response',
    'daily_report',
    'monthly_report'
  ) NOT NULL,
  priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  condition_json LONGTEXT NULL,
  message_template TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_notification_rules_name_event_category (name, event_type, category_id),
  KEY idx_notification_rules_category (category_id),
  KEY idx_notification_rules_event_type (event_type),
  KEY idx_notification_rules_active (is_active),
  CONSTRAINT fk_notification_rules_category
    FOREIGN KEY (category_id) REFERENCES visitor_categories(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_rule_channels (
  rule_id BIGINT UNSIGNED NOT NULL,
  channel_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (rule_id, channel_id),
  CONSTRAINT fk_notification_rule_channels_rule
    FOREIGN KEY (rule_id) REFERENCES notification_rules(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_notification_rule_channels_channel
    FOREIGN KEY (channel_id) REFERENCES notification_channels(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_rule_recipients (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rule_id BIGINT UNSIGNED NOT NULL,
  recipient_type ENUM('user', 'role', 'department_manager', 'contact', 'custom') NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  role_id BIGINT UNSIGNED NULL,
  department_id BIGINT UNSIGNED NULL,
  contact_id BIGINT UNSIGNED NULL,
  custom_name VARCHAR(160) NULL,
  custom_email VARCHAR(180) NULL,
  custom_phone VARCHAR(40) NULL,
  custom_telegram_chat_id VARCHAR(120) NULL,
  custom_whatsapp_number VARCHAR(40) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notification_rule_recipients_rule (rule_id),
  KEY idx_notification_rule_recipients_type (recipient_type),
  CONSTRAINT fk_notification_rule_recipients_rule
    FOREIGN KEY (rule_id) REFERENCES notification_rules(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_notification_rule_recipients_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_notification_rule_recipients_role
    FOREIGN KEY (role_id) REFERENCES roles(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_notification_rule_recipients_department
    FOREIGN KEY (department_id) REFERENCES departments(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_notification_rule_recipients_contact
    FOREIGN KEY (contact_id) REFERENCES notification_contacts(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rule_id BIGINT UNSIGNED NULL,
  channel_id BIGINT UNSIGNED NOT NULL,
  visit_id BIGINT UNSIGNED NULL,
  recipient_name VARCHAR(160) NULL,
  recipient_address VARCHAR(180) NOT NULL,
  subject VARCHAR(180) NULL,
  message TEXT NOT NULL,
  action_yes_url VARCHAR(500) NULL,
  action_no_url VARCHAR(500) NULL,
  attachment_path VARCHAR(500) NULL,
  attachment_name VARCHAR(180) NULL,
  status ENUM('queued', 'sent', 'failed', 'skipped') NOT NULL DEFAULT 'queued',
  provider_message_id VARCHAR(180) NULL,
  error_message TEXT NULL,
  attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  queued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notification_logs_status (status),
  KEY idx_notification_logs_visit (visit_id),
  KEY idx_notification_logs_queued_at (queued_at),
  CONSTRAINT fk_notification_logs_rule
    FOREIGN KEY (rule_id) REFERENCES notification_rules(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_notification_logs_channel
    FOREIGN KEY (channel_id) REFERENCES notification_channels(id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_notification_logs_visit
    FOREIGN KEY (visit_id) REFERENCES visits(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_schedules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  report_type ENUM('daily', 'weekly', 'monthly') NOT NULL,
  run_time TIME NOT NULL DEFAULT '23:30:00',
  day_of_month TINYINT UNSIGNED NULL,
  output_format ENUM('pdf', 'xlsx', 'csv', 'html') NOT NULL DEFAULT 'pdf',
  filters_json LONGTEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_run_at TIMESTAMP NULL,
  next_run_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_report_schedules_name (name),
  KEY idx_report_schedules_type (report_type),
  KEY idx_report_schedules_active (is_active),
  KEY idx_report_schedules_next_run (next_run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_recipients (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  schedule_id BIGINT UNSIGNED NOT NULL,
  recipient_type ENUM('user', 'role', 'department_manager', 'contact', 'custom') NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  role_id BIGINT UNSIGNED NULL,
  department_id BIGINT UNSIGNED NULL,
  contact_id BIGINT UNSIGNED NULL,
  custom_name VARCHAR(160) NULL,
  custom_email VARCHAR(180) NULL,
  custom_phone VARCHAR(40) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_report_recipients_schedule (schedule_id),
  CONSTRAINT fk_report_recipients_schedule
    FOREIGN KEY (schedule_id) REFERENCES report_schedules(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_report_recipients_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_report_recipients_role
    FOREIGN KEY (role_id) REFERENCES roles(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_report_recipients_department
    FOREIGN KEY (department_id) REFERENCES departments(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_report_recipients_contact
    FOREIGN KEY (contact_id) REFERENCES notification_contacts(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  schedule_id BIGINT UNSIGNED NULL,
  report_type ENUM('daily', 'weekly', 'monthly') NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  file_path VARCHAR(500) NULL,
  status ENUM('created', 'sent', 'failed') NOT NULL DEFAULT 'created',
  error_message TEXT NULL,
  generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_report_logs_schedule (schedule_id),
  KEY idx_report_logs_period (period_start, period_end),
  CONSTRAINT fk_report_logs_schedule
    FOREIGN KEY (schedule_id) REFERENCES report_schedules(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS backup_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  frequency ENUM('hourly', 'daily', 'weekly', 'monthly') NOT NULL DEFAULT 'daily',
  run_time TIME NOT NULL DEFAULT '23:30:00',
  backup_target ENUM('local', 'nas', 's3', 'drive') NOT NULL DEFAULT 'local',
  target_config_json LONGTEXT NULL,
  retention_days INT UNSIGNED NOT NULL DEFAULT 30,
  include_database TINYINT(1) NOT NULL DEFAULT 1,
  include_uploads TINYINT(1) NOT NULL DEFAULT 1,
  mail_enabled TINYINT(1) NOT NULL DEFAULT 0,
  mail_recipient_name VARCHAR(160) NULL,
  mail_recipient_email VARCHAR(190) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_run_at TIMESTAMP NULL,
  next_run_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_backup_jobs_name (name),
  KEY idx_backup_jobs_active (is_active),
  KEY idx_backup_jobs_next_run (next_run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS backup_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id BIGINT UNSIGNED NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  file_size_bytes BIGINT UNSIGNED NULL,
  checksum_sha256 CHAR(64) NULL,
  status ENUM('running', 'success', 'failed', 'deleted') NOT NULL DEFAULT 'running',
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at TIMESTAMP NULL,
  error_message TEXT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_backup_logs_job (job_id),
  KEY idx_backup_logs_status (status),
  KEY idx_backup_logs_started_at (started_at),
  CONSTRAINT fk_backup_logs_job
    FOREIGN KEY (job_id) REFERENCES backup_jobs(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_backup_logs_created_by
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS restore_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  backup_log_id BIGINT UNSIGNED NULL,
  restored_by_user_id BIGINT UNSIGNED NULL,
  source_file_name VARCHAR(255) NOT NULL,
  source_checksum_sha256 CHAR(64) NULL,
  status ENUM('running', 'success', 'failed') NOT NULL DEFAULT 'running',
  notes TEXT NULL,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at TIMESTAMP NULL,
  error_message TEXT NULL,
  PRIMARY KEY (id),
  KEY idx_restore_logs_backup (backup_log_id),
  KEY idx_restore_logs_status (status),
  CONSTRAINT fk_restore_logs_backup
    FOREIGN KEY (backup_log_id) REFERENCES backup_logs(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_restore_logs_user
    FOREIGN KEY (restored_by_user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  before_json LONGTEXT NULL,
  after_json LONGTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_logs_user (user_id),
  KEY idx_audit_logs_entity (entity_type, entity_id),
  KEY idx_audit_logs_created_at (created_at),
  CONSTRAINT fk_audit_logs_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
