<?php
require_once '../config.php';
require_once $app.'/libs/redis.php';

$redis = redis(REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DB);

if (is_method('get')) {
    $attr = str_replace(' ', '', $_GET['get']);
    switch ($attr) {
    case 'type':
        echo get_agent($redis, $_GET['uid'], 'type');
        break;
    case 'callerid':
        echo get_agent($redis, $_GET['uid'], 'callerid');
        break;
    case 'company':
        echo get_agent($redis, $_GET['uid'], 'company');
        break;
    case 'status':
        $status = intval(get_agent($redis, $_GET['uid'], 'status'));
        if ($status === 1) {
            echo 'true';
        } else {
            echo 'false';
        }
        break;
    default:
        echo 'null';
    }
}

function is_method($method) {
    return isset($_GET[$method]);
}

function get_agent($redis, $uid, $attr) {
    if (!$redis) {
        return 'null';
    }

    $reply = $redis->hMGet('agent.'.$uid, ['type', 'callerid', 'company', 'status']);
    
    switch ($attr) {
    case 'type':
        return $reply['type'] != false ? $reply['type'] : 'null';
    case 'callerid':
        return $reply['callerid'] != false ? $reply['callerid'] : 'null';
    case 'company':
        return $reply['company'] != false ? $reply['company'] : 'null';
    case 'status':
        return $reply['status'] != false ? $reply['status'] : '0';
    default:
        return 'null';
    }
}

