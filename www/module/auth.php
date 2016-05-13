<?php

function is_login($redis) {
    if (!$redis) {
        return false;
    }

    // check uid and token
    if (!isset($_COOKIE['uid']) || !isset($_COOKIE['token'])) {
        return false;
    }
    
    $uid = $_COOKIE['uid'];
    $token = $_COOKIE['token'];
    
    $reply = $redis->hMGet('session.'.$uid, ['token', 'ip_addr']);
    if (($reply['token'] === $token) && ($reply['ip_addr'] === $_SERVER["REMOTE_ADDR"])) {
        return true;
    }

    return false;
}

function check($redis, $user, $password) {
    if (!$redis || !is_string($user) || !is_string($password)) {
        return false;
    }
    
    $reply = $redis->hGet('agent.'.$user, 'password');
    if ($reply === $password) {
        return true;
    }
    
    $password = sha1(md5($password));
    
    $reply = $redis->hGet('user.'.$user, 'password');
    if ($reply === $password) {
        return true;
    }
    
    return false;
}

function login($db, $redis, $uid, $ipaddr) {
    if (!$db || !$redis) {
        return false;
    }
    
    $token = token(128, 0);

    $reply = $redis->hMSet('session.'.$uid, ['token' => $token, 'ip_addr' => $ipaddr]);
    $redis->setTimeout('session.'.$uid, 43200);

    setcookie("uid", $uid, time() + 43200);
    setcookie("token", $token, time() + 43200);
    
    return true;
}

function logout($redis, $uid) {
    setcookie("uid", 'null', time() + 1);
    setcookie("token", 'null', time() + 1);

    $redis->delete('session.'.$uid);
}

function token($length = 5, $type = 0) {
    $arr = array(1 => "0123456789", 2 => "abcdefghijklmnopqrstuvwxyz", 3 => "ABCDEFGHIJKLMNOPQRSTUVWXYZ", 4 => "~@#$%^&*(){}[]|");
    
    if ($type == 0) {
        array_pop($arr);
        $string = implode("", $arr);
    } elseif ($type == "-1") {
        $string = implode("", $arr);
    } else {
        $string = $arr[$type];
    }
    
    $count = strlen($string) - 1;
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $string[rand(0, $count)];
    }
    
    return $code;
}

function is_company_agent($redis, $uid, $company_id) {
    if (!$redis || !is_numeric($uid) || !is_numeric($company_id)) {
        return false;
    }

    $company_id = intval($company_id);
    
    $reply = $redis->hGet('agent.'.$uid, 'company');
    
    if (intval($reply) === $company_id) {
        return true;
    }

    return false;
}