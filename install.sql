CREATE TABLE IF NOT EXISTS admins (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(80) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 name_vi VARCHAR(120) DEFAULT NULL,
 name_en VARCHAR(120) DEFAULT NULL,
 name_zh VARCHAR(120) DEFAULT NULL,
 sort_order INT NOT NULL DEFAULT 0,
 active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS option_profiles (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 profile_type ENUM('ice','sugar') NOT NULL,
 code VARCHAR(20) NOT NULL,
 display_name VARCHAR(120) NOT NULL,
 display_name_vi VARCHAR(120) DEFAULT NULL,
 display_name_en VARCHAR(120) DEFAULT NULL,
 display_name_zh VARCHAR(120) DEFAULT NULL,
 options_json TEXT NOT NULL,
 options_json_vi TEXT DEFAULT NULL,
 options_json_en TEXT DEFAULT NULL,
 options_json_zh TEXT DEFAULT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uniq_profile(profile_type,code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 category_id INT UNSIGNED NOT NULL,
 name VARCHAR(160) NOT NULL,
 name_vi VARCHAR(160) DEFAULT NULL,
 name_en VARCHAR(160) DEFAULT NULL,
 name_zh VARCHAR(160) DEFAULT NULL,
 price DECIMAL(10,2) NOT NULL DEFAULT 0,
 has_sizes TINYINT(1) NOT NULL DEFAULT 0,
 price_l DECIMAL(10,2) DEFAULT NULL,
 price_xl DECIMAL(10,2) DEFAULT NULL,
 image VARCHAR(255) DEFAULT NULL,
 ice_profile VARCHAR(20) NOT NULL DEFAULT 'I2',
 sugar_profile VARCHAR(20) NOT NULL DEFAULT 'S2',
 active TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 0,
 is_new TINYINT(1) NOT NULL DEFAULT 0,
 is_hot TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_product_category FOREIGN KEY(category_id) REFERENCES categories(id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS toppings (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 name_vi VARCHAR(120) DEFAULT NULL,
 name_en VARCHAR(120) DEFAULT NULL,
 name_zh VARCHAR(120) DEFAULT NULL,
 description_vi TEXT DEFAULT NULL,
 description_en TEXT DEFAULT NULL,
 description_zh TEXT DEFAULT NULL,
 price DECIMAL(10,2) NOT NULL DEFAULT 0,
 sell_separately TINYINT(1) NOT NULL DEFAULT 0,
 retail_price DECIMAL(10,2) DEFAULT NULL,
 image VARCHAR(255) DEFAULT NULL,
 is_new TINYINT(1) NOT NULL DEFAULT 0,
 is_hot TINYINT(1) NOT NULL DEFAULT 0,
 active TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_recommended_toppings (
 product_id INT UNSIGNED NOT NULL,
 topping_id INT UNSIGNED NOT NULL,
 sort_order INT NOT NULL DEFAULT 0,
 PRIMARY KEY(product_id,topping_id),
 KEY idx_prt_topping(topping_id),
 CONSTRAINT fk_prt_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE,
 CONSTRAINT fk_prt_topping FOREIGN KEY(topping_id) REFERENCES toppings(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_no VARCHAR(40) NOT NULL UNIQUE,
 customer_name VARCHAR(100) NOT NULL,
 phone VARCHAR(50) NOT NULL,
 address VARCHAR(255) NOT NULL,
 payment_method ENUM('transfer','cash') NOT NULL,
 invoice_required TINYINT(1) NOT NULL DEFAULT 0,
 invoice_company_name VARCHAR(200) DEFAULT NULL,
 invoice_tax_id VARCHAR(50) DEFAULT NULL,
 invoice_company_address VARCHAR(255) DEFAULT NULL,
 invoice_company_representative VARCHAR(120) DEFAULT NULL,
 invoice_company_phone VARCHAR(50) DEFAULT NULL,
 invoice_email VARCHAR(190) DEFAULT NULL,
 order_note TEXT NULL,
 coupon_code VARCHAR(80) DEFAULT NULL,
 subtotal_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 payment_expires_at BIGINT UNSIGNED NULL,
 status ENUM('new','accepted','done','cancelled') NOT NULL DEFAULT 'new',
 game_wallet_token VARCHAR(64) DEFAULT NULL,
 game_award_processed TINYINT(1) NOT NULL DEFAULT 0,
 game_chances_awarded INT NOT NULL DEFAULT 0,
 notification_email_status VARCHAR(20) NOT NULL DEFAULT 'pending',
 notification_email_detail VARCHAR(255) DEFAULT NULL,
 notification_email_attempts INT NOT NULL DEFAULT 0,
 notification_email_sent_at DATETIME DEFAULT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY idx_orders_payment_expiry(status,payment_method,payment_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 product_id INT UNSIGNED DEFAULT NULL,
 standalone_topping_id INT UNSIGNED DEFAULT NULL,
 product_name VARCHAR(160) NOT NULL,
 cup_size VARCHAR(10) DEFAULT NULL,
 base_price DECIMAL(10,2) NOT NULL,
 ice_choice VARCHAR(100) NOT NULL,
 sugar_choice VARCHAR(100) NOT NULL,
 toppings_text TEXT,
 unit_price DECIMAL(10,2) NOT NULL,
 discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 qty INT NOT NULL DEFAULT 1,
 line_total DECIMAL(12,2) NOT NULL,
 KEY idx_order_product(product_id),
 CONSTRAINT fk_order_item FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS game_wallets (
 wallet_token VARCHAR(64) PRIMARY KEY,
 available_chances INT NOT NULL DEFAULT 0,
 total_earned INT NOT NULL DEFAULT 0,
 total_played INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS game_plays (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 wallet_token VARCHAR(64) NOT NULL,
 completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY idx_game_wallet(wallet_token),
 CONSTRAINT fk_game_wallet FOREIGN KEY(wallet_token) REFERENCES game_wallets(wallet_token) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS customer_coupons (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 wallet_token VARCHAR(64) NOT NULL,
 code VARCHAR(80) NOT NULL UNIQUE,
 coupon_type VARCHAR(30) NOT NULL DEFAULT 'fixed_drink',
 amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 used_order_id BIGINT UNSIGNED DEFAULT NULL,
 used_at DATETIME DEFAULT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY idx_coupon_wallet(wallet_token),
 KEY idx_coupon_used(used_order_id),
 CONSTRAINT fk_coupon_wallet FOREIGN KEY(wallet_token) REFERENCES game_wallets(wallet_token) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bank_accounts (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 bank_name VARCHAR(120) NOT NULL,
 account_number VARCHAR(80) NOT NULL,
 account_name VARCHAR(160) NOT NULL,
 qr_image VARCHAR(255) DEFAULT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 sort_order INT NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
 setting_key VARCHAR(100) PRIMARY KEY,
 setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO categories(id,name,name_vi,name_en,name_zh,sort_order,active) VALUES (1,'Đề xuất','Đề xuất','Recommended','推薦飲品',10,1);
INSERT IGNORE INTO option_profiles(profile_type,code,display_name,display_name_vi,display_name_en,display_name_zh,options_json,options_json_vi,options_json_en,options_json_zh) VALUES
('ice','I1','100% Bình thường','100% Bình thường','100% Normal','100%正常','["100% Bình thường"]','["100% Bình thường"]','["100% Normal"]','["100%正常"]'),
('ice','I2','Bình thường / Ít đá / Không đá / Đá riêng','Bình thường / Ít đá / Không đá / Đá riêng','Normal / Less ice / No ice / Ice on the side','正常 / 少冰 / 去冰 / 冰塊另附','["100% Bình thường","50% Ít đá","0% Không đá","Đá riêng"]','["100% Bình thường","50% Ít đá","0% Không đá","Đá riêng"]','["100% Normal","50% Less ice","0% No ice","Ice on the side"]','["100%正常","50%少冰","0%去冰","冰塊另附"]'),
('ice','I3','Ít đá / Không đá / Đá riêng','Ít đá / Không đá / Đá riêng','Less ice / No ice / Ice on the side','少冰 / 去冰 / 冰塊另附','["50% Ít đá","0% Không đá","Đá riêng"]','["50% Ít đá","0% Không đá","Đá riêng"]','["50% Less ice","0% No ice","Ice on the side"]','["50%少冰","0%去冰","冰塊另附"]'),
('ice','I4','Không đá / Đá riêng','Không đá / Đá riêng','No ice / Ice on the side','去冰 / 冰塊另附','["0% Không đá","Đá riêng"]','["0% Không đá","Đá riêng"]','["0% No ice","Ice on the side"]','["0%去冰","冰塊另附"]'),
('sugar','S1','100% Đường','100% Đường','100% Sugar','100%全糖','["100% Đường"]','["100% Đường"]','["100% Sugar"]','["100%全糖"]'),
('sugar','S2','Đường chuẩn / 70% / 50% / 30% / Không đường','Đường chuẩn / 70% / 50% / 30% / Không đường','Full / 70% / 50% / 30% / No sugar','全糖 / 少糖 / 半糖 / 微糖 / 無糖','["100% Đường","70% Ít đường","50% Nửa đường","30% Ít ngọt","0% Không đường"]','["100% Đường","70% Ít đường","50% Nửa đường","30% Ít ngọt","0% Không đường"]','["100% Sugar","70% Less sugar","50% Half sugar","30% Light sugar","0% No sugar"]','["100%全糖","70%少糖","50%半糖","30%微糖","0%無糖"]'),
('sugar','S3','Đường chuẩn / 50% / Không đường','Đường chuẩn / 50% / Không đường','Full / 50% / No sugar','全糖 / 半糖 / 無糖','["100% Đường","50% Nửa đường","0% Không đường"]','["100% Đường","50% Nửa đường","0% Không đường"]','["100% Sugar","50% Half sugar","0% No sugar"]','["100%全糖","50%半糖","0%無糖"]'),
('sugar','S4','Đường chuẩn / 50%','Đường chuẩn / 50%','Full / 50%','全糖 / 半糖','["100% Đường","50% Nửa đường"]','["100% Đường","50% Nửa đường"]','["100% Sugar","50% Half sugar"]','["100%全糖","50%半糖"]'),
('sugar','S5','0% Không đường','0% Không đường','0% No sugar','0%無糖','["0% Không đường"]','["0% Không đường"]','["0% No sugar"]','["0%無糖"]');

INSERT IGNORE INTO bank_accounts(id,bank_name,account_number,account_name,qr_image,active,sort_order) VALUES
(1,'VietinBank','107867441574','Đỗ Đông Nghi','bank-vietinbank-default.jpg',1,10);

INSERT IGNORE INTO settings(setting_key,setting_value) VALUES
('store_name','Hồng Trà Tuổi Thơ Đặt đồ uống trực tuyến'),('store_name_vi','Hồng Trà Tuổi Thơ Đặt đồ uống trực tuyến'),('store_name_en','Hồng Trà Tuổi Thơ Online Ordering'),('store_name_zh','Hồng Trà Tuổi Thơ 線上點餐'),('currency','₫'),('transfer_note','Thông tin chuyển khoản sẽ do trụ sở cung cấp riêng'),('order_email',''),
('home_banner_active','0'),('home_banner_image',''),('home_banner_link_type','product'),('home_banner_target_id',''),
('buy4get1_enabled','1'),('buy4get1_code','MUA4TANG1'),('show_product_sales_count','1'),('theme_scheme','default'),
('order_alert_enabled','1'),('order_alert_sound',''),('order_alert_sound_source','default'),('order_alert_mode','seconds'),('order_alert_duration','20'),
('minigame_enabled','1'),('game_cups_per_chance','10'),('game_reward_amount','0'),('v175_bank_seeded','1');

CREATE TABLE IF NOT EXISTS push_subscriptions (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 endpoint TEXT NOT NULL,
 p256dh TEXT DEFAULT NULL,
 auth TEXT DEFAULT NULL,
 lang VARCHAR(8) NOT NULL DEFAULT 'vi',
 user_agent VARCHAR(255) DEFAULT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 last_success_at DATETIME DEFAULT NULL,
 last_error_at DATETIME DEFAULT NULL,
 last_error VARCHAR(255) DEFAULT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uniq_endpoint_prefix (endpoint(190)),
 KEY idx_push_active(active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
