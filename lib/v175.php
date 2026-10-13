<?php
/**
 * v1.7.5 schema and helpers: transfer/bank account management.
 */
function ensure_v175_schema(): void {
    static $done=false;
    if($done) return;
    $done=true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS bank_accounts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bank_name VARCHAR(120) NOT NULL,
            account_number VARCHAR(80) NOT NULL,
            account_name VARCHAR(160) NOT NULL,
            qr_image VARCHAR(255) DEFAULT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $seeded=setting('v175_bank_seeded','0')==='1';
        if(!$seeded){
            $count=(int)db()->query('SELECT COUNT(*) FROM bank_accounts')->fetchColumn();
            if($count===0){
                $st=db()->prepare('INSERT INTO bank_accounts(bank_name,account_number,account_name,qr_image,active,sort_order) VALUES(?,?,?,?,1,10)');
                $st->execute(['VietinBank','107867441574','Đỗ Đông Nghi','bank-vietinbank-default.jpg']);
            }
            set_setting('v175_bank_seeded','1');
        }
    } catch(Throwable $e) {}
}

function active_bank_accounts(): array {
    try {
        return db()->query('SELECT * FROM bank_accounts WHERE active=1 ORDER BY sort_order,id')->fetchAll();
    } catch(Throwable $e){ return []; }
}
