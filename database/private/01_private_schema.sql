CREATE DATABASE IF NOT EXISTS fsp_match_private
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fsp_match_private;

CREATE TABLE IF NOT EXISTS user_private_data (
    user_id INT UNSIGNED PRIMARY KEY,
    email_ciphertext TEXT NOT NULL,
    email_lookup CHAR(64) NOT NULL UNIQUE,
    full_name_ciphertext TEXT NULL,
    phone_ciphertext TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_private_email_lookup (email_lookup)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS private_audit_log (
    audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_private_audit_user_time (user_id, created_at)
) ENGINE=InnoDB;
