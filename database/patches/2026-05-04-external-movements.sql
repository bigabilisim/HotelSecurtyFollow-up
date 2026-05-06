CREATE TABLE IF NOT EXISTS external_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  department_id BIGINT UNSIGNED NULL,
  person_name VARCHAR(160) NOT NULL,
  normalized_person_name VARCHAR(160) NOT NULL,
  vehicle_plate VARCHAR(40) NULL,
  destination_note VARCHAR(500) NULL,
  exit_km INT UNSIGNED NULL,
  return_km INT UNSIGNED NULL,
  status ENUM('outside', 'returned', 'cancelled') NOT NULL DEFAULT 'outside',
  exit_user_id BIGINT UNSIGNED NULL,
  return_user_id BIGINT UNSIGNED NULL,
  exit_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  return_at DATETIME NULL,
  return_note VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_external_movements_status_exit (status, exit_at),
  KEY idx_external_movements_department (department_id),
  KEY idx_external_movements_person (normalized_person_name),
  KEY idx_external_movements_vehicle (vehicle_plate),
  KEY idx_external_movements_exit_user (exit_user_id),
  KEY idx_external_movements_return_user (return_user_id),
  CONSTRAINT fk_external_movements_department
    FOREIGN KEY (department_id) REFERENCES departments(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_external_movements_exit_user
    FOREIGN KEY (exit_user_id) REFERENCES users(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_external_movements_return_user
    FOREIGN KEY (return_user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (code, name, module, description) VALUES
  ('external_movements.view', 'Dış Görev Ekranını Gör', 'external_movements', 'Dış görev hareket ekranını görüntüler.'),
  ('external_movements.create_exit', 'Dış Görev Çıkışı Ver', 'external_movements', 'Dış hizmete çıkan kişi/araç için çıkış kaydı oluşturur.'),
  ('external_movements.create_return', 'Dış Görev Girişi Ver', 'external_movements', 'Dış görevden dönen kişi/araç için giriş kaydı oluşturur.')
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  module = VALUES(module),
  description = VALUES(description);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code IN (
  'external_movements.view',
  'external_movements.create_exit',
  'external_movements.create_return'
)
WHERE r.code IN ('admin', 'security', 'night_manager', 'operation_manager');

INSERT INTO app_settings (setting_key, setting_value, is_encrypted)
VALUES ('external_movements.enabled', '0', 0)
ON DUPLICATE KEY UPDATE setting_value = setting_value;
