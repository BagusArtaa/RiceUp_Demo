-- Migration: Add payment channels, payment proof, and payment_channel_id to existing database
-- Run this in phpMyAdmin on an existing rice_up database

CREATE TABLE IF NOT EXISTS payment_channels (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    channel_type ENUM('bank_transfer','qris') NOT NULL,
    bank_name VARCHAR(100) NULL,
    account_number VARCHAR(50) NULL,
    account_holder VARCHAR(120) NULL,
    qris_image_path VARCHAR(255) NULL,
    instructions TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payment_channels_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders ADD COLUMN payment_channel_id INT UNSIGNED NULL AFTER payment_method;
ALTER TABLE orders ADD COLUMN payment_proof_path VARCHAR(255) NULL AFTER payment_channel_id;

