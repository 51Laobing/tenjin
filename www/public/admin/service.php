<?php
require '../../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/module/service.php';

class_detect($user['type'], '0');

$db = new medoo([
    'database_type' => 'pgsql',
    'database_name' => PGSQL_DB,
    'server' => PGSQL_HOST,
    'port' => PGSQL_PORT,
    'username' => PGSQL_USER,
    'password' => PGSQL_PASSWORD,
    'charset' => 'utf8'
]);

$pbx = new medoo([
    'database_type' => 'pgsql',
    'database_name' => PBX_DB,
    'server' => PBX_HOST,
    'port' => PBX_PORT,
    'username' => PBX_USER,
    'password' => PBX_PASSWORD,
    'charset' => 'utf8'
]);

if (isset($_GET['action']) && isset($_GET['company'])) {
    $action = $_GET['action'];
    $company_id = $_GET['company'];
    if ($action === 'start') {
        service($redis, $company_id, 'start');
    } else if ($action === 'stop') {
        service($redis, $company_id, 'stop');
    }
    usleep(500000);
    redirect('admin/service.php');
} else {
    $data['user'] = $user;
    $data['alert_sound'] = get_sound_not_reviewed($db);
    $data['services'] = fetch_all_service($db, $pbx);

    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/admin.service.html', $data);
}
function get_sound_not_reviewed($db) {
    if (!$db) {
        return 0;
    }

    $result = $db->count('sounds', ['status' => 0]);

    return $result;
}

