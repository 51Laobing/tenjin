<?php
require_once '../config.php';
require_once $app.'/libs/redis.php';

$redis = redis(REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DB);

if (isset($_GET['uid']) && is_numeric($_GET['uid'])) {
    $uid = intval(str_replace(' ', '', $_GET['uid']));
    $last_called = get_last_called($redis, $uid);
    if ($last_called != null) {
       echo $last_called;
       exit;
    }
}
echo '0';
exit;

function get_last_called($redis, $uid) {
    if (!$redis || !is_numeric($uid)) {
        return null;
    }

    $uid = intval($uid);
    $reply = $redis->hGet('agent.'.$uid, 'last_called');
    return intval($reply);
}
