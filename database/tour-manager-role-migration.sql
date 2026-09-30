-- Run once on an existing Kizza Tours database to enable the Tour Manager role.
ALTER TABLE admin_users
    MODIFY COLUMN role ENUM('super_admin', 'admin', 'editor', 'manager') DEFAULT 'admin';
