<?php
require_once '../config.php';
require_once $app.'/libs/redis.php';

$redis = redis(REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DB);

$company_id = (isset($_GET['company_id']) && is_numeric($_GET['company_id'])) ? intval($_GET['company_id']) : 0;

if ($company_id > 0) {
   $reply = $redis->hGet('company.'.$company_id, 'task');
   $task_id = intval($reply);
   if ($task_id > 0) {
       echo 'true';
       exit;
   }
}
echo 'false';
