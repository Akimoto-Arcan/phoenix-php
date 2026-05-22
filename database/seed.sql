-- Phoenix-PHP Seed Data
-- Populates roles, permissions, and role_permissions mappings

-- System roles
INSERT IGNORE INTO `roles` (`name`, `display_name`, `description`, `is_system`, `sort_order`) VALUES
('SuperAdmin', 'Super Administrator', 'Full system access — all permissions granted implicitly', 1, 1),
('Admin', 'Administrator', 'Administrative access to all modules', 1, 2),
('Supervisor', 'Supervisor', 'Supervisory access to production and quality', 1, 3),
('Operator', 'Operator', 'Production line operations', 1, 4),
('Inspection', 'Inspection', 'Quality control and inspection', 1, 5),
('Lab', 'Laboratory', 'Laboratory testing access', 1, 6),
('Maintenance', 'Maintenance', 'Equipment maintenance and repair', 1, 7),
('Shipping', 'Shipping', 'Shipping and logistics', 1, 8);

-- Permissions
INSERT IGNORE INTO `permissions` (`key`, `display_name`, `category`) VALUES
('production.read', 'View Production', 'production'),
('production.write', 'Edit Production', 'production'),
('production.delete', 'Delete Production', 'production'),
('inspection.read', 'View Inspection', 'inspection'),
('inspection.write', 'Edit Inspection', 'inspection'),
('inspection.delete', 'Delete Inspection', 'inspection'),
('operator.read', 'View Operator Tools', 'operator'),
('operator.write', 'Use Operator Tools', 'operator'),
('maintenance.read', 'View Maintenance', 'maintenance'),
('maintenance.write', 'Edit Maintenance', 'maintenance'),
('maintenance.delete', 'Delete Maintenance', 'maintenance'),
('users.read', 'View Users', 'users'),
('users.write', 'Edit Users', 'users'),
('users.delete', 'Delete Users', 'users'),
('dashboard.production', 'Production Dashboard', 'dashboard'),
('dashboard.qc', 'QC Dashboard', 'dashboard'),
('dashboard.maintenance', 'Maintenance Dashboard', 'dashboard'),
('dashboard.shipping', 'Shipping Dashboard', 'dashboard'),
('dashboard.users', 'Users Dashboard', 'dashboard'),
('dashboard.paperwork', 'Paperwork Dashboard', 'dashboard'),
('dashboard.analytics', 'Analytics Dashboard', 'dashboard'),
('dashboard.welcome', 'Welcome Dashboard', 'dashboard'),
('dashboard.options', 'Options Dashboard', 'dashboard');

-- Role-permission mappings
-- Admin gets everything except implicit SuperAdmin
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p WHERE r.name = 'Admin';

-- Supervisor
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'Supervisor' AND p.`key` IN (
    'production.read', 'production.write',
    'inspection.read', 'inspection.write',
    'operator.read', 'operator.write',
    'maintenance.read',
    'dashboard.production', 'dashboard.qc', 'dashboard.maintenance',
    'dashboard.users', 'dashboard.paperwork', 'dashboard.analytics',
    'dashboard.welcome', 'dashboard.options'
);

-- Operator
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'Operator' AND p.`key` IN (
    'production.read', 'operator.read', 'operator.write',
    'dashboard.production', 'dashboard.welcome', 'dashboard.options'
);

-- Inspection
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'Inspection' AND p.`key` IN (
    'inspection.read', 'inspection.write',
    'dashboard.qc', 'dashboard.paperwork', 'dashboard.welcome', 'dashboard.options'
);

-- Lab
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'Lab' AND p.`key` IN (
    'inspection.read',
    'dashboard.qc', 'dashboard.paperwork', 'dashboard.welcome', 'dashboard.options'
);

-- Maintenance
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'Maintenance' AND p.`key` IN (
    'maintenance.read', 'maintenance.write',
    'dashboard.maintenance', 'dashboard.welcome', 'dashboard.options'
);

-- Shipping
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r, `permissions` p
WHERE r.name = 'Shipping' AND p.`key` IN (
    'dashboard.shipping', 'dashboard.welcome', 'dashboard.options'
);

-- Default settings
INSERT IGNORE INTO `settings` (`key`, `value`, `type`, `category`, `description`, `is_public`) VALUES
('app.name', 'Phoenix ERP', 'string', 'general', 'Application display name', 1),
('app.version', '1.0.0', 'string', 'general', 'Installed version', 0),
('app.theme', 'dark', 'string', 'display', 'Default color theme', 1),
('auth.max_failed_attempts', '5', 'integer', 'security', 'Lock account after N failed logins', 0),
('auth.lockout_minutes', '15', 'integer', 'security', 'Account lockout duration in minutes', 0),
('auth.password_min_length', '8', 'integer', 'security', 'Minimum password length', 0),
('auth.session_timeout', '30', 'integer', 'security', 'Default session timeout in minutes', 0);
