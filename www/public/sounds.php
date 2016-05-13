<?php
require '../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/module/sounds.php';
require $app.'/module/company.php';

class_detect($user['type'], '1');

$db = new medoo([
    'database_type' => 'pgsql',
    'database_name' => PGSQL_DB,
    'server' => PGSQL_HOST,
    'port' => PGSQL_PORT,
    'username' => PGSQL_USER,
    'password' => PGSQL_PASSWORD,
    'charset' => 'utf8'
]);

if (isset($_GET['action'])) {
    if ($_GET['action'] === 'remove') {
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $id = intval($_GET['id']);
            if (is_company_sound($db, $user['company'], $id)) {
                sound_remove($db, $id);
            }
        }
    }
    redirect('sounds.php');
} else if (isset($_POST['action'])) {
    if ($_POST['action'] === 'new') {
        $data = filter_sound_upload($_POST, $_FILES);
        if ($data != null) {
            $file = uniqid().'.wav';
            if (move_uploaded_file($data['tmp_file'], '/var/www/public/sounds/'.$file)) {
                $data['file'] = $file;
                $data['company'] = $user['company'];
                $data['duration'] = 0;
                
                $company = get_company($redis, $user['company']);
                if ($company != null) {
                    $data['status'] = ($company['sound_check'] === 0) ? 1 : 0;
                } else {
                    $data['status'] = 0;
                }

                $data['create_time'] = date('Y-m-d H:i:s', time());
                $data['operator'] = $user['uid'];
                $data['ip_addr'] = $_SERVER["REMOTE_ADDR"];
                unset($data['tmp_file']);

                sound_create($db, $data);
                sync_to_redis($db, $redis, $user['company']);
            }
        }
    }
    redirect('sounds.php');
} else {
    $data['user'] = $user;
    $data['sounds'] = get_all_sound($db, $user['company']);

    $radius = redis(RADIUS_HOST, RADIUS_PORT, RADIUS_PASSWORD, RADIUS_DB);
    $data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;
    
    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/sounds.html', $data);
}

function sync_to_redis($db, $redis, $company_id) {
    if (!$db || !$redis || !is_numeric($company_id)) {
        return false;
    }

    $company_id = intval($company_id);
    $result = $db->select('sounds', '*', ['company' => $company_id]);
    if (is_array($result)) {
        foreach ($result as $sound) {
            $redis->hMSet('sound.'.$sound['id'], $sound);
        }
        return true;
    }

    return false;
}