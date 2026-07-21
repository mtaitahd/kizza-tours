-- Admin OTP table for two-factor login authentication
CREATE TABLE IF NOT EXISTS admin_otp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE,
    INDEX idx_admin_otp (admin_id, otp_code, used, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
