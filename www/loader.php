<?php
require_once 'config.php';
require_once $app.'/libs/redis.php';
require_once $app.'/module/auth.php';
require_once $app.'/module/response.php';

$redis = redis(REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DB);

if (!is_login($redis)) {
    redirect('login.php');
}

$user = get_session($redis);

if ($user === false) {
    redirect('login.php');
}

function get_session($redis) {
    if (!$redis) {
        return false;
    }

    $uid = $_COOKIE['uid'];
    if ($redis->exists('agent.'.$uid)) {
        return $redis->hGetAll('agent.'.$uid);
    } else if ($redis->exists('user.'.$uid)) {
        return $redis->hGetAll('user.'.$uid);
    }

    return false;
}

function class_detect($type, $class) {
    if (is_numeric($type)) {
        if ($type !== $class) {
            switch ($type) {
            case '0':
                redirect('admin/company.php');
                exit;
            case '1':
                redirect('status.php');
                exit;
            case '2':
                redirect('user.php');
                exit;
            default:
                redirect('login.php');
                exit;
            }
        }
    }
}

function is_money_enough($redis, $radius, $company_id) {
    if (!$redis || !$radius || !is_numeric($company_id)) {
        return false;
    }

    $company_id = intval($company_id);

    $billing = $redis->hGet('company.'.$company_id, 'billing');
    if ($billing != false) {
	$reply = $radius->hGet($billing, 'money');
	if ($reply != false) {
            $money = intval($reply);
            if ($money > MONEY_LIMIT) {
		return true;
            }
	}
    }

    return false;
}

