<?php
require_once '../config.php';
require_once $app.'/libs/redis.php';

$redis = redis(REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DB);

if (isset($_GET['company']) && is_numeric($_GET['company'])) {
    $company_id = intval(str_replace(' ', '', $_GET['company']));
    $task_id = get_current_task($redis, $company_id);
    if ($task_id != null) {
        $task = get_task($redis, $task_id);
        if ($task != null) {
            if ($task['type'] === '3') {
                $called = get_number($redis, $task_id);
                echo $called;

                if (isset($_GET['agent'])) {
                    last_called($redis, $_GET['agent'], $called);
                }
                exit;
            }
        }
    }

}
echo '00000000000';
exit;

function get_current_task($redis, $company_id) {
    if (!$redis || !is_numeric($company_id)) {
        return null;
    }

    $company_id = intval($company_id);
    $reply = $redis->hGet('company.'.$company_id, 'task');
    $task_id = intval($reply);
    if ($task_id > 0) {
        return $task_id;
    }

    return null;
}

function get_task($redis, $task_id) {
    if (!$redis || !is_numeric($task_id)) {
        return null;
    }

    $task_id = intval($task_id);
    $reply = $redis->hMGet('task.'.$task_id, ['type']);
    if (is_array($reply)) {
        if (!in_array(false, $reply, true)) {
            return $reply;
        }
    }

    return null;
}

function get_number($redis, $task_id) {
    if (!$redis || !is_numeric($task_id)) {
        return '00000000000';
    }

    $task_id = intval($task_id);
    $reply = $redis->lPop('data.'.$task_id);
    if ($reply) {
        return $reply;
    }

    return '00000000000';
}

function last_called($redis, $agent, $called) {
    if (!$redis || !is_numeric($agent) || !is_string($called)) {
        return false;
    }

    $agent = intval($agent);
    if ($agent > 1000) {
        $redis->hSet('agent.'.$agent, 'last_called', $called);
        return true;
    }

    return false;
}

