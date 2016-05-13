<?php
require_once '../config.php';
require_once $app.'/libs/redis.php';

$redis = redis(REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DB);

if (isset($_GET['company']) && is_numeric($_GET['company'])) {
    $company_id = intval($_GET['company']);
    if ($company_id > 0) {
        $task_id = get_company_task($redis, $company_id);
        $sound = get_task_sound($redis, $task_id);
        if ($sound != null) {
            echo $sound;
            exit;
        }
    }
}
echo 'null';

function get_company_task($redis, $company_id) {
    if (!$redis || !is_numeric($company_id)) {
        return 0;
    }

    $company_id = intval($company_id);
    
    $reply = $redis->hGet('company.'.$company_id, 'task');
    $task_id = intval($reply);
    if ($task_id > 0) {
        return $task_id;
    }

    return 0;
}


function get_task_sound($redis, $task_id) {
    if (!$redis || $task_id < 1) {
        return null;
    }

    $reply = $redis->hMGet('task.'.$task_id, ['play', 'sound']);
    $play = intval($reply['play']);
    if ($play === 1) {
        $sound_id = intval($reply['sound']);
        if ($sound_id > 0) {
            $reply = $redis->hGet('sound.'.$sound_id, 'file');
            if ($reply) {
                return $reply;
            }
        }
    }

    return null;
}

