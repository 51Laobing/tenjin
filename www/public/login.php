<?php
require '../config.php';
require $app.'/libs/latte.php';
require $app.'/libs/redis.php';
require $app.'/libs/medoo.php';
require $app.'/module/auth.php';
require $app.'/module/response.php';
require $app.'/module/logs.php';

if (!isset($_POST['username']) || !isset($_POST['password'])) {
    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/login.html');
    exit();
}

$uid = stripcslashes(trim($_POST['username']));
$password = stripcslashes(trim($_POST['password']));

$redis = redis(REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DB);

$db = new medoo([
    'database_type' => 'pgsql',
    'database_name' => PGSQL_DB,
    'server' => PGSQL_HOST,
    'port' => PGSQL_PORT,
    'username' => PGSQL_USER,
    'password' => PGSQL_PASSWORD,
    'charset' => 'utf8'
]);


$uid = str_replace(' ', '', $uid);

// check login
if (check($redis, $uid, $password)) {

    // write login logs
    log_last_login($db, $redis, $uid, $_SERVER["REMOTE_ADDR"]);    

    login($db, $redis, $uid, $_SERVER["REMOTE_ADDR"]);


    $user = null;

    if ($redis->exists('agent.'.$uid)) {
        $reply = $redis->hMGet('agent.'.$uid, ['uid', 'name', 'type', 'company']);
        if (!in_array(false, $reply, true)) {
            $user = $reply;
        }
    } else if ($redis->exists('user.'.$uid)) {
        $reply = $redis->hMGet('user.'.$uid, ['uid', 'name', 'type', 'company']);
        if (!in_array(false, $reply, true)) {
            $user = $reply;
        }
    }
    
    if ($user != null) {
        if ($user['type'] === '0') {
            redirect('admin/company.php');
        } else if ($user['type'] === '1') {
            redirect('status.php');
        } else if ($user['type'] === '2') {
            redirect('user.php');
        } else {
            usleep(500000);
            redirect('login.php');
        }
    } else {
        usleep(500000);
        redirect('login.php');
    }
} else {
    usleep(500000);
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
