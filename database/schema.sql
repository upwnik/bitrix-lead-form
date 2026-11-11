
CREATE DATABASE IF NOT EXISTS bitrix_leads
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bitrix_leads;

CREATE TABLE leads (
                       id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                       uuid CHAR(36) NOT NULL,
                       name VARCHAR(255) NOT NULL,
                       phone VARCHAR(30) NOT NULL,
                       email VARCHAR(255) NOT NULL,
                       comment TEXT NULL,
                       priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
                       status ENUM('saved', 'processing', 'sent', 'retry', 'error') DEFAULT 'saved',
                       bitrix_lead_id BIGINT UNSIGNED NULL,
                       attempts INT UNSIGNED DEFAULT 0,
                       last_error TEXT NULL,
                       next_retry_at DATETIME NULL,
                       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                       sent_at DATETIME NULL,

                       PRIMARY KEY (id),
                       UNIQUE KEY uq_leads_uuid (uuid),
                       INDEX idx_leads_status (status),
                       INDEX idx_leads_created_at (created_at),
                       INDEX idx_leads_next_retry_at (next_retry_at)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
