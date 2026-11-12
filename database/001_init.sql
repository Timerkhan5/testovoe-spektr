CREATE TABLE IF NOT EXISTS leads_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(254) NOT NULL,
    comment TEXT NULL,
    priority ENUM('low', 'normal', 'high') NOT NULL DEFAULT 'normal',
    priority_source ENUM('ai', 'fallback') NOT NULL DEFAULT 'fallback',
    classified_at DATETIME NULL,
    delivery_status ENUM('saved', 'processing', 'retry', 'sent') NOT NULL DEFAULT 'saved',
    crm_lead_id BIGINT UNSIGNED NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_error_code VARCHAR(100) NULL,
    last_error_message VARCHAR(255) NULL,
    next_retry_at DATETIME NULL,
    lease_token CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    lease_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    INDEX leads_status_id (delivery_status, id),
    INDEX leads_priority_id (priority, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    attempt_no INT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    outcome ENUM('processing', 'sent', 'retry') NOT NULL DEFAULT 'processing',
    http_status SMALLINT UNSIGNED NULL,
    error_code VARCHAR(100) NULL,
    UNIQUE (request_id, attempt_no),
    FOREIGN KEY (request_id) REFERENCES leads_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    scope VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    client_key BINARY(32) NOT NULL,
    window_start BIGINT UNSIGNED NOT NULL,
    hits INT UNSIGNED NOT NULL,
    PRIMARY KEY (scope, client_key, window_start),
    INDEX rate_limits_expiry (window_start)
) ENGINE=InnoDB;
