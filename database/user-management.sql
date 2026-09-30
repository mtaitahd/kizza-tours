-- Kizza Tours user management and activity log migration.
-- Take a database backup before applying. This script is additive and should be run once.

ALTER TABLE admin_users
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
    ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_active;


CREATE TABLE admin_permissions (
    permission_code VARCHAR(64) NOT NULL PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    module VARCHAR(40) NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO admin_permissions (permission_code, label, module, sort_order) VALUES
('view_dashboard', 'View dashboard', 'dashboard', 1),
('manage_bookings', 'Manage bookings', 'bookings', 10),
('manage_tours', 'Manage tours', 'tours', 20),
('manage_destinations', 'Manage destinations', 'destinations', 30),
('manage_gallery', 'Manage gallery', 'gallery', 40),
('manage_testimonials', 'Manage testimonials', 'testimonials', 50),
('manage_faqs', 'Manage FAQs', 'faqs', 60),
('manage_inquiries', 'Manage inquiries', 'inquiries', 70),
('manage_quotes', 'Manage quotes', 'quotes', 80),
('manage_pages', 'Manage pages', 'pages', 90),
('manage_media', 'Manage image tools', 'media', 100),
('manage_sitemap', 'Manage sitemap', 'sitemap', 110),
('manage_settings', 'Manage settings', 'settings', 120);

CREATE TABLE admin_user_permissions (
    admin_id INT NOT NULL,
    permission_code VARCHAR(64) NOT NULL,
    granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (admin_id, permission_code),
    KEY idx_admin_permission_code (permission_code, admin_id),
    CONSTRAINT fk_admin_user_permissions_admin
        FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_admin_user_permissions_permission
        FOREIGN KEY (permission_code) REFERENCES admin_permissions(permission_code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Preserve the current access of existing staff accounts. Newly created users
-- receive only permissions selected by the owner.
INSERT INTO admin_user_permissions (admin_id, permission_code)
SELECT u.id, p.permission_code
FROM admin_users u
CROSS JOIN admin_permissions p
WHERE u.role <> 'super_admin';

CREATE TABLE admin_activity_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor_admin_id INT NULL,
    actor_name VARCHAR(100) NOT NULL,
    action VARCHAR(40) NOT NULL,
    module VARCHAR(40) NOT NULL,
    record_id INT NULL,
    record_title VARCHAR(255) NULL,
    metadata LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activity_time_actor (created_at, actor_admin_id),
    KEY idx_activity_actor_time (actor_admin_id, created_at),
    KEY idx_activity_time_module_action (created_at, module, action),
    KEY idx_activity_module_time (module, created_at),
    KEY idx_activity_target (module, record_id),
    CONSTRAINT fk_admin_activity_actor
        FOREIGN KEY (actor_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
