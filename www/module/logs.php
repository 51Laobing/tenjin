<?php

function log_last_login($db, $redis, $uid, $ipaddr) {
    if (!$db || !$redis || !is_string($uid) || !is_string($ipaddr)) {
        return false;
    }

    $db->update('agent', ['last_login' => date('Y-m-d H:i:s',time()), 'last_ipaddr' => $ipaddr], ['uid' => $uid]);
    $db->update('users', ['last_login' => date('Y-m-d H:i:s',time()), 'last_ipaddr' => $ipaddr], ['uid' => $uid]);

    $key = null;
    
    $reply = $redis->exists('agent.'.$uid);
    if ($reply === true) {
        $key = 'agent.'.$uid;
    } else {
        $reply = $redis->exists('user.'.$uid);
        if ($reply === true) {
            $key = 'user.'.$uid;
        }
    }
    
    if ($key != null) {
        $redis->hMSet($key, ['last_login' => time(), 'last_ipaddr' => $ipaddr]);
    }
}