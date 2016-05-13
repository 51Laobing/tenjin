<?php
require '../../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/module/company.php';
require $app.'/module/user.php';

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

if (isset($_GET['action']) && isset($_GET['uid'])) {
    if ($_GET['action'] === 'remove' && is_string($_GET['uid'])) {
        $uid = str_replace(' ', '', $_GET['uid']);
        the_user_remove($db, $redis, $uid);
    }
    
    redirect('admin/users.php');
} else if (isset($_POST['action']) && $_POST['action'] === 'create') {
    $data = filter_user_create($_POST);
    if ($data != null) {
        the_user_create($db, $redis, $data['uid'], $data['password'], $data['company']);
    }
    
    redirect('admin/users.php');
} else {
    $data['user'] = $user;
    $data['alert_sound'] = get_sound_not_reviewed($db);
    $data['users'] = get_all_user($db);
    $data['companys'] = fetch_all_company($db);
    
    if ($data['users'] != null) {
        $count = count($data['users']);
        for ($i = 0; $i < $count; $i++) {
            $data['users'][$i]['company_name'] = get_company_name($db, $data['users'][$i]['company']);
        }
    }

    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/admin.users.html', $data);
}

function get_sound_not_reviewed($db) {
    if (!$db) {
        return 0;
    }

    $result = $db->count('sounds', ['status' => 0]);

    return $result;
}

