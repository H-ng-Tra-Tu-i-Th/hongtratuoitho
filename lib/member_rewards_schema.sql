CREATE TABLE IF NOT EXISTS game_wallets (
 wallet_token VARCHAR(64) PRIMARY KEY,
 member_id BIGINT UNSIGNED NULL,
 claimed_member_id BIGINT UNSIGNED NULL,
 claimed_wallet_token VARCHAR(64) NULL,
 claimed_at DATETIME NULL,
 claimed_chances INT NOT NULL DEFAULT 0,
 claimed_earned INT NOT NULL DEFAULT 0,
 claimed_played INT NOT NULL DEFAULT 0,
 available_chances INT NOT NULL DEFAULT 0,
 total_earned INT NOT NULL DEFAULT 0,
 total_played INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_game_wallet_member(member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
