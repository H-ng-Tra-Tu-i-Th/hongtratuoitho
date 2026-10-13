CREATE TABLE IF NOT EXISTS members (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 phone VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 display_name VARCHAR(100) NOT NULL DEFAULT '',
 delivery_address VARCHAR(255) NOT NULL DEFAULT '',
 phone_verified_at DATETIME NULL,
 phone_verified_by INT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_member_phone(phone),
 KEY idx_member_verified(phone_verified_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS member_ipos_profiles (
 member_id BIGINT UNSIGNED PRIMARY KEY,
 source_brand VARCHAR(100) NOT NULL,
 external_member_id VARCHAR(128) NOT NULL DEFAULT '',
 name VARCHAR(100) NOT NULL DEFAULT '',
 birthday VARCHAR(10) NOT NULL DEFAULT '',
 tier_name VARCHAR(120) NOT NULL DEFAULT '',
 points DECIMAL(18,4) NULL,
 last_synced_at DATETIME NOT NULL,
 CONSTRAINT fk_member_ipos_profile FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS member_ipos_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 member_id BIGINT UNSIGNED NOT NULL,
 source_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 pos_parent VARCHAR(100) NOT NULL,
 pos_id VARCHAR(100) NOT NULL,
 tran_id VARCHAR(128) NOT NULL,
 occurred_at DATETIME NOT NULL,
 pos_name VARCHAR(160) NOT NULL DEFAULT '',
 reported_amount DECIMAL(18,2) NOT NULL,
 currency CHAR(3) NOT NULL DEFAULT 'VND',
 items_json LONGTEXT NOT NULL,
 source_channels_json LONGTEXT NOT NULL,
 sale_type VARCHAR(32) NOT NULL,
 last_synced_at DATETIME NOT NULL,
 UNIQUE KEY uq_member_source_transaction(source_key),
 KEY idx_member_ipos_transactions(member_id,occurred_at),
 CONSTRAINT fk_member_ipos_transaction FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS member_sync_states (
 member_id BIGINT UNSIGNED PRIMARY KEY,
 status VARCHAR(32) NOT NULL DEFAULT 'never',
 message_key VARCHAR(64) NOT NULL DEFAULT 'member_sync_never',
 last_attempt_at DATETIME NULL,
 last_success_at DATETIME NULL,
 config_fingerprint CHAR(64) NOT NULL DEFAULT '',
 CONSTRAINT fk_member_sync_state FOREIGN KEY(member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS member_rate_limits (
 bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 attempts INT UNSIGNED NOT NULL,
 expires_at BIGINT UNSIGNED NOT NULL,
 KEY idx_member_rate_expiry(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
