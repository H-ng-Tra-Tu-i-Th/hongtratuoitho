<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../lib/bootstrap.php';
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
try{
    set_setting('push_cron_last_run',date('Y-m-d H:i:s'));
    echo json_encode(hqp_process_queue(50,40),JSON_UNESCAPED_SLASHES).PHP_EOL;
    // Keep only the latest 30 days of transport diagnostics (never delete orders).
    db()->exec("DELETE FROM push_delivery_jobs WHERE created_at<DATE_SUB(NOW(),INTERVAL 30 DAY) AND status NOT IN ('queued','retry','sending') LIMIT 1000");
}catch(Throwable $e){hqp_log('cron_error',$e);fwrite(STDERR,"push_queue_error\n");exit(1);}
