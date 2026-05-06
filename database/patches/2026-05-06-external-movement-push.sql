ALTER TABLE users
  ADD COLUMN IF NOT EXISTS mobile_notification_external_movement_enabled TINYINT(1) NOT NULL DEFAULT 0
  AFTER mobile_notification_department_enabled;

ALTER TABLE mobile_notification_logs
  MODIFY COLUMN event_type ENUM(
    'entry',
    'exit',
    'department_question',
    'department_reminder',
    'escalation',
    'external_movement_exit'
  ) NOT NULL DEFAULT 'entry';

UPDATE users
SET mobile_notification_enabled = 1,
    mobile_notification_external_movement_enabled = 1
WHERE deleted_at IS NULL
  AND status = 'active'
  AND (
    LOWER(full_name) LIKE '%oğuz%'
    OR LOWER(full_name) LIKE '%oguz%'
    OR LOWER(username) LIKE '%oguz%'
    OR LOWER(email) LIKE '%oguz%'
    OR LOWER(full_name) LIKE '%banu%'
    OR LOWER(username) LIKE '%banu%'
    OR LOWER(email) LIKE '%banu%'
  );
