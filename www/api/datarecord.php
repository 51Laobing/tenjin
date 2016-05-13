<?php
require_once '../config.php';
require_once $app.'/libs/redis.php';

$redis = redis(REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DB);

if (isset($_GET['company']) && is_numeric($_GET['company'])) {
    $company_id = intval($_GET['company']);
    if (isset($_GET['item']) && is_string($_GET['item'])) {
        $item = str_replace(' ', '', $_GET['item']);
        switch ($item) {
        case 'answer':
            the_task_record($redis, $company_id, 'answer');
            break;
        case 'complete':
            the_task_record($redis, $company_id, 'complete');
            break;
        }
    }
}

function the_task_record($redis, $company_id, $item) {
    if (!$redis || !is_numeric($company_id) || !is_string($item)) {
        return false;
    }

    $company_id = intval($company_id);
    $reply = $redis->hGet('company.'.$company_id, 'task');
    $task_id = intval($reply);
    if ($task_id > 0) {
        $redis->hIncrBy('task.'.$task_id, $item, 1);
        return true;
    }
    
    return false;
}

