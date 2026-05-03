USE hotel_security;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS mobile_notification_department_enabled TINYINT(1) NOT NULL DEFAULT 1
  AFTER mobile_notification_exit_enabled;

ALTER TABLE mobile_notification_logs
  MODIFY COLUMN event_type ENUM(
    'entry',
    'exit',
    'department_question',
    'department_reminder',
    'escalation'
  ) NOT NULL DEFAULT 'entry';
