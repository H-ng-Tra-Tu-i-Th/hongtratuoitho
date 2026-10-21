<?php
/** One versioned order-status service for desktop and mobile staff actions. */
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}
require_once __DIR__.'/payment_workflow_i18n.php';
class OrderWorkflowError extends RuntimeException {}
function htow_fail(string $key): never {throw new OrderWorkflowError($key);}
function htow_schema(): void {
    static $done=false;if($done)return;$pdo=db();if($pdo->inTransaction())htow_fail('storage');
    $st=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$st->execute(['order_workflow_schema']);
    if($st->fetchColumn()!=='19.15.0'){
        $lock='htow:'.substr(hash('sha256',(string)$pdo->query('SELECT DATABASE()')->fetchColumn()),0,40);
        $guard=$pdo->prepare('SELECT GET_LOCK(?,10)');$guard->execute([$lock]);if((int)$guard->fetchColumn()!==1)htow_fail('storage');
        try{
            $st->execute(['order_workflow_schema']);if($st->fetchColumn()!=='19.15.0'){
                $check=$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='status_version'");
                if(!(int)$check->fetchColumn())$pdo->exec('ALTER TABLE orders ADD COLUMN status_version INT UNSIGNED NOT NULL DEFAULT 1');
                $check=$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='checkout_request_hash'");
                if(!(int)$check->fetchColumn())$pdo->exec('ALTER TABLE orders ADD COLUMN checkout_request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL');
                $check=$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND INDEX_NAME='uq_order_checkout_request'");
                if(!(int)$check->fetchColumn())$pdo->exec('ALTER TABLE orders ADD UNIQUE KEY uq_order_checkout_request (checkout_request_hash)');
                $pdo->exec("CREATE TABLE IF NOT EXISTS order_status_events (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,order_id BIGINT UNSIGNED NOT NULL,from_status VARCHAR(16) NOT NULL,to_status VARCHAR(16) NOT NULL,from_version INT UNSIGNED NOT NULL,to_version INT UNSIGNED NOT NULL,admin_id BIGINT UNSIGNED NOT NULL,reason VARCHAR(1000) NOT NULL,created_at DATETIME NOT NULL,KEY idx_order_status_history(order_id,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute(['order_workflow_schema','19.15.0']);
            }
        }finally{$pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);}
    }$done=true;
}
function htow_targets(string $status): array {
    // Cancellation after completion is explicit and retains the established reward policy.
    return ['new'=>['accepted','cancelled'],'accepted'=>['done','cancelled'],'done'=>['cancelled'],'cancelled'=>[]][$status]??[];
}
function htow_change(int $orderId,mixed $target,mixed $version,mixed $reason,int $adminId): void {
    htp_staff_guard($adminId);
    if(!is_string($target)||!in_array($target,['new','accepted','done','cancelled'],true))htow_fail('status_transition_error');
    if((!is_string($version)&&!is_int($version))||!preg_match('/^[1-9]\d{0,9}$/D',(string)$version))htow_fail('status_changed_error');
    try{$reason=htp_reason($reason);}catch(TransferPaymentError $e){htow_fail('reason_required');}
    $pdo=db();if($pdo->inTransaction())htow_fail('storage');$pdo->beginTransaction();
    try{
        htp_staff_locked($adminId);
        $order=hyg_one('SELECT * FROM orders WHERE id=? FOR UPDATE',[$orderId]);if(!$order)htow_fail('not_found');
        if(!admin_order_allowed($order))htow_fail('forbidden');
        if((int)$order['status_version']!==(int)$version)htow_fail('status_changed_error');
        if($order['status']===$target){$pdo->commit();return;}
        if(!in_array($target,htow_targets($order['status']),true))htow_fail('status_transition_error');
        $pdo->prepare('UPDATE orders SET status=?,status_version=status_version+1 WHERE id=? AND status_version=?')->execute([$target,$orderId,$version]);
        $pdo->prepare('INSERT INTO order_status_events(order_id,from_status,to_status,from_version,to_version,admin_id,reason,created_at) VALUES(?,?,?,?,?,?,?,UTC_TIMESTAMP())')->execute([$orderId,$order['status'],$target,$version,(int)$version+1,$adminId,$reason]);
        if($target==='cancelled'){
            $r=hyg_one('SELECT * FROM transfer_order_receipts WHERE order_id=? FOR UPDATE',[$orderId]);
            if($r){$after=$r;$after['version']=(int)$r['version']+1;$pdo->prepare('UPDATE transfer_order_receipts SET version=version+1 WHERE order_id=?')->execute([$orderId]);htp_event($orderId,'cancelled',$r,$after,$adminId,$reason);}
        }
        if($target==='done'){
            award_game_chances_for_order($orderId);
            $processed=hyg_one('SELECT game_award_processed FROM orders WHERE id=?',[$orderId]);
            if(empty($processed['game_award_processed']))htow_fail('reward_failed');
        }
        $pdo->commit();
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
}
function htow_history(int $orderId): array {return hyg_all('SELECT * FROM order_status_events WHERE order_id=? ORDER BY id DESC',[$orderId]);}
function htow_error(Throwable $error): string {
    if($error instanceof TransferPaymentError)return htp_error($error);
    if($error instanceof OrderWorkflowError){$label=pwf($error->getMessage());return $label!==$error->getMessage()?$label:htp('storage');}
    return function_exists('hds_view_error')?hds_view_error($error):htp('storage');
}
function htow_render_history(array $order): string {
    $history=htow_history((int)$order['id']);ob_start();?><section class="panel ma-panel"><p class="muted"><?=e(pwf('policy_hint'))?></p><details><summary><?=e(pwf('order_history'))?></summary><?php foreach($history as $entry):?><p><?=e(status_label($entry['from_status']))?> → <?=e(status_label($entry['to_status']))?> · <?=e(hds_view_time($entry['created_at']))?> · <?=e(pwf('staff'))?> #<?=(int)$entry['admin_id']?> · <?=e($entry['reason'])?></p><?php endforeach;?></details></section><?php return ob_get_clean();
}
/** Shared filters use real receipt balances, never fulfillment status as a paid indicator. */
function htp_list_query(array $filters,int $limit=200): array {
    $where=[];$args=[];$status=$filters['status']??'';$payment=$filters['payment_state']??'';
    $adminStore = admin_store_id();
    $storeId = $adminStore !== null ? $adminStore : (!empty($filters['store_id']) ? (int)$filters['store_id'] : null);
    if ($storeId !== null && $storeId > 0) {
        $where[] = 'o.dispatch_store_id = ?';
        $args[] = $storeId;
    }
    if(is_string($status)&&in_array($status,['new','accepted','done','cancelled'],true)){$where[]='o.status=?';$args[]=$status;}
    $state="CASE WHEN o.status='cancelled' AND COALESCE(r.received_amount-r.refunded_amount,0)>0 THEN 'refund_pending' WHEN o.status='cancelled' AND COALESCE(r.received_amount,0)>0 AND r.received_amount=r.refunded_amount THEN 'refunded' WHEN r.order_id IS NULL THEN 'legacy' WHEN r.received_amount-r.refunded_amount>r.amount THEN 'overpaid' WHEN r.amount=0 AND r.received_amount=r.refunded_amount THEN 'not_required' WHEN r.amount>0 AND r.received_amount-r.refunded_amount>=r.amount THEN 'confirmed' WHEN r.received_amount-r.refunded_amount>0 THEN 'partial' WHEN r.report_state='requested' THEN 'requested' WHEN r.report_state='rejected' THEN 'rejected' ELSE 'unpaid' END";
    if(is_string($payment)&&in_array($payment,['unpaid','requested','partial','confirmed','overpaid','refund_pending','refunded','legacy','not_required','rejected'],true)){$where[]='('.$state.')=?';$args[]=$payment;}
    else{$where[]="(o.payment_method != 'transfer' OR o.total_amount <= 0 OR ($state) NOT IN ('unpaid', 'requested'))";}
    $sql="SELECT o.*,r.status AS receipt_status,r.version AS receipt_version,r.amount AS receipt_due,r.received_amount,r.refunded_amount,r.report_state,r.ledger_only,$state AS financial_state FROM orders o LEFT JOIN transfer_order_receipts r ON r.order_id=o.id".($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY o.id DESC LIMIT '.max(1,min(500,$limit));
    return hyg_all($sql,$args);
}
function htow_status_counts(?int $storeId = null): array {
    $counts = ['new' => 0, 'accepted' => 0, 'done' => 0, 'cancelled' => 0];
    $adminStore = admin_store_id();
    $storeId = $adminStore !== null ? $adminStore : $storeId;
    $where = "(o.payment_method != 'transfer' OR o.total_amount <= 0 OR r.status IN ('confirmed', 'overpaid') OR r.received_amount >= r.amount)";
    $args = [];
    if ($storeId !== null && $storeId > 0) {
        $where .= " AND o.dispatch_store_id = ?";
        $args[] = $storeId;
    }
    $st = db()->prepare("SELECT o.status, COUNT(*) c FROM orders o LEFT JOIN transfer_order_receipts r ON r.order_id=o.id WHERE $where GROUP BY o.status");
    $st->execute($args);
    foreach ($st->fetchAll() as $r) {
        if (isset($counts[$r['status']])) {
            $counts[$r['status']] = (int)$r['c'];
        }
    }
    return $counts;
}
function htp_render_list_filters(string $path,array $filters,bool $export=true): string {
    ob_start();?><section class="panel ma-panel"><form method="get" action="<?=e($path)?>" class="form-grid"><input type="hidden" name="status" value="<?=e(is_string($filters['status']??'')?$filters['status']??'':'')?>"><label><?=e(pwf('payment_state'))?><select name="payment_state"><option value=""><?=e(pwf('filter_all'))?></option><?php foreach(['unpaid','requested','partial','confirmed','overpaid','refund_pending','refunded','legacy','not_required','rejected'] as $key):?><option value="<?=e($key)?>" <?=($filters['payment_state']??'')===$key?'selected':''?>><?=e(pwf($key))?></option><?php endforeach;?></select></label><button class="btn ma-btn" type="submit"><?=e(pwf('filter'))?></button></form><?php if($export&&!admin_is_operator()):?><p><a class="btn ma-btn" href="<?=e(base_url('admin/payment_export.php'))?>"><?=e(pwf('export'))?></a></p><?php endif;?></section><?php return ob_get_clean();
}
function htp_export_date(mixed $value): DateTimeImmutable {
    if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value))throw new InvalidArgumentException('date_invalid');
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('Asia/Ho_Chi_Minh'));
    if(!$date||$date->format('Y-m-d')!==$value)throw new InvalidArgumentException('date_invalid');return $date;
}
function htp_csv_safe(mixed $value): string {
    $text=(string)($value??'');
    // Spreadsheet formula/control prefixes must remain plain text even after leading spaces.
    if(preg_match('/^[\x00-\x20]*[=+\-@]/',$text)||preg_match('/^[\t\r\n]/',$text))return "'".$text;return $text;
}

/**
 * Automatically cancel unpaid pending orders that have exceeded the 15-minute hold time.
 * Can be run from CLI cron job (bin/auto-cancel-expired-orders.php) or lazily during checkout checks.
 *
 * @param int $limit Maximum number of expired orders to process in one execution.
 * @return int Number of orders successfully cancelled.
 */
function htow_cancel_expired_orders(int $limit = 50): int {
    $pdo = db();
    // Orders with status 'new', payment_method = 'transfer', created > 15 mins ago,
    // where transfer_order_receipts has received_amount <= 0 or receipt status in ('pending', 'requested')
    $sql = "SELECT o.id, o.order_no, o.status_version 
            FROM orders o
            LEFT JOIN transfer_order_receipts r ON r.order_id = o.id
            WHERE o.status = 'new'
              AND o.payment_method = 'transfer'
              AND o.created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)
              AND COALESCE(r.received_amount, 0) <= 0
              AND COALESCE(r.status, 'pending') NOT IN ('confirmed', 'overpaid')
            ORDER BY o.id ASC
            LIMIT " . max(1, min(500, (int)$limit));
    
    $expiredOrders = hyg_all($sql);
    if (!$expiredOrders) {
        return 0;
    }

    $cancelledCount = 0;
    $reason = 'Tự động hủy đơn do quá hạn thời gian thanh toán (15 phút)';

    foreach ($expiredOrders as $item) {
        $orderId = (int)$item['id'];
        $expectedVersion = (int)$item['status_version'];

        $inTx = $pdo->inTransaction();
        if (!$inTx) {
            $pdo->beginTransaction();
        }

        try {
            $order = hyg_one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if ($order && $order['status'] === 'new' && (int)$order['status_version'] === $expectedVersion) {
                $r = hyg_one('SELECT * FROM transfer_order_receipts WHERE order_id = ? FOR UPDATE', [$orderId]);
                if (!$r || ((float)($r['received_amount'] ?? 0) <= 0 && !in_array($r['status'] ?? '', ['confirmed', 'overpaid'], true))) {
                    $newVersion = $expectedVersion + 1;
                    $pdo->prepare('UPDATE orders SET status = "cancelled", status_version = ? WHERE id = ? AND status_version = ?')
                        ->execute([$newVersion, $orderId, $expectedVersion]);

                    $pdo->prepare('INSERT INTO order_status_events(order_id, from_status, to_status, from_version, to_version, admin_id, reason, created_at) VALUES(?, "new", "cancelled", ?, ?, 0, ?, UTC_TIMESTAMP())')
                        ->execute([$orderId, $expectedVersion, $newVersion, $reason]);

                    if ($r) {
                        $pdo->prepare('UPDATE transfer_order_receipts SET version = version + 1 WHERE order_id = ?')->execute([$orderId]);
                    }

                    $cancelledCount++;
                }
            }
            if (!$inTx && $pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if (!$inTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    return $cancelledCount;
}

/**
 * Automated in-app cron: Runs on background web requests every 60 seconds without blocking.
 * Cancels pending transfer orders older than 15 minutes automatically like Node.js.
 */
function htow_auto_maintenance(): void {
    $now = time();
    $lastRun = (int)setting('htow_expired_orders_last_run', '0');
    // Run at most once every 60 seconds
    if ($now - $lastRun < 60) {
        return;
    }

    $pdo = db();
    if ($pdo->inTransaction()) {
        return;
    }

    // Non-blocking named lock (0-second wait) so concurrent web requests never wait
    $lockName = 'htow-auto-maintenance:' . substr(hash('sha256', (string)$pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 32);
    $guard = $pdo->prepare('SELECT GET_LOCK(?, 0)');
    $guard->execute([$lockName]);
    if ((int)$guard->fetchColumn() !== 1) {
        return;
    }

    try {
        $lastRun = (int)setting('htow_expired_orders_last_run', '0');
        if ($now - $lastRun < 60) {
            return;
        }

        set_setting('htow_expired_orders_last_run', (string)$now);
        htow_cancel_expired_orders(50);
    } catch (Throwable $ignored) {
    } finally {
        $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
    }
}


