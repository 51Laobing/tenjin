<?php

// redis database
function redis($host, $port = 6379, $password = NULL, $db = 0) {
    $redis = new Redis();
    $redis->connect($host, $port);
    if (!is_null($password)) {
        $redis->auth($password);
    }
    $redis->select($db);
    return $redis;
}