USE hotel_security;

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON p.code = 'visits.department_verify'
WHERE r.code IN ('owner', 'general_manager', 'operation_manager');
