<?php
require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/../lib/layout.php';
require_once __DIR__.'/../lib/order_workflow.php';
require_admin();
if(admin_is_operator()){http_response_code(403);exit(e(pwf('export_forbidden')));}
header('Cache-Control: private, no-store');
$error='';$from=$_GET['date_from']??date('Y-m-01');$to=$_GET['date_to']??date('Y-m-d');$account=$_GET['account']??'';
$accounts=hyg_all("SELECT DISTINCT account_code FROM transfer_payment_events WHERE account_code IS NOT NULL ORDER BY account_code");
if(($_GET['download']??'')==='1'){
    try{
        $start=htp_export_date($from);$end=htp_export_date($to);if($end<$start)throw new InvalidArgumentException('date_invalid');
        if(!is_string($account)||($account!==''&&!preg_match('/^[a-z_]+:\d{1,10}$/D',$account)))throw new InvalidArgumentException('invalid_account');
        $args=[$start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),$end->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')];
        $sql='SELECT e.*,o.order_no,o.status AS order_status,a.display_name,a.username FROM transfer_payment_events e JOIN orders o ON o.id=e.order_id LEFT JOIN admins a ON a.id=e.admin_id WHERE e.created_at>=? AND e.created_at<?';
        if($account!==''){$sql.=' AND e.account_code=?';$args[]=$account;}$sql.=' ORDER BY e.created_at,e.id';
        $st=db()->prepare($sql);$st->execute($args);
        header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="payment-reconciliation-'.$from.'-'.$to.'.csv"');header('X-Content-Type-Options: nosniff');
        $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['Event ID','Order number','Order status','Time (Asia/Ho_Chi_Minh)','Action','Account code','Bank','Account number','Account holder','Received delta','Refund delta','Transaction reference','Reason','Staff ID','Staff name'],',','"','');
        while($row=$st->fetch()){
            $recipient=json_decode($row['recipient_json']??'',true);if(!is_array($recipient))$recipient=[];
            $time=(new DateTimeImmutable($row['created_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('Y-m-d H:i:s');
            $cells=[$row['id'],$row['order_no'],status_label($row['order_status']),$time,pwf($row['event_type']),$row['account_code'],$recipient['bank_name']??'',$recipient['account_number']??'',$recipient['account_name']??'',$row['amount_delta'],$row['refund_delta'],$row['transaction_reference'],$row['reason'],$row['admin_id'],($row['display_name']?:$row['username'])??''];
            foreach($cells as $i=>$cell)if(!in_array($i,[0,9,10,13],true))$cells[$i]=htp_csv_safe($cell);
            fputcsv($out,$cells,',','"','');
        }fclose($out);exit;
    }catch(Throwable $exception){$error=$exception instanceof InvalidArgumentException?pwf($exception->getMessage()):htp('storage');}
}
admin_header(pwf('export'));?>
<?php if($error):?><div class="alert danger" role="alert"><?=e($error)?></div><?php endif;?>
<section class="panel"><h2><?=e(pwf('export'))?></h2><p>Asia/Ho_Chi_Minh · <?=e(pwf('history'))?></p><p class="muted"><?=e(pwf('refund_manual_only'))?></p><form method="get" class="form-grid"><input type="hidden" name="download" value="1"><label><?=e(pwf('date_from'))?><input type="date" name="date_from" required value="<?=e(is_string($from)?$from:'')?>"></label><label><?=e(pwf('date_to'))?><input type="date" name="date_to" required value="<?=e(is_string($to)?$to:'')?>"></label><label><?=e(pwf('account'))?><select name="account"><option value=""><?=e(pwf('filter_all'))?></option><?php foreach($accounts as $row):?><option value="<?=e($row['account_code'])?>" <?=$account===$row['account_code']?'selected':''?>><?=e($row['account_code'])?></option><?php endforeach;?></select></label><button class="btn primary" type="submit"><?=e(pwf('export'))?></button></form></section>
<?php admin_footer();?>
