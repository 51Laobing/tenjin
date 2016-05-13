<?php
require '../../../loader.php';
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

if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $data = filter_user_edit($_POST);
    if ($data != null) {
        the_user_edit($db, $redis, $data['uid'], $data['password']);
    }

    redirect('admin/users.php');
} else if (isset($_GET['uid']) && is_string($_GET['uid'])) {
    $uid = str_replace(' ', '', $_GET['uid']);
    $users = get_user($db, $uid);
    if ($users != null) {
        $users['company_name'] = get_company_name($db, $users['company']);
    }

    $data['users'] = $users;

    $data['user'] = $user;
    $data['alert_sound'] = get_sound_not_reviewed($db);
    $data['companys'] = fetch_all_company($db);

    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/admin.users.edit.html', $data);
} else {
    redirect('admin/users.php');
}

function get_sound_not_reviewed($db) {
    if (!$db) {
        return 0;
    }

    $result = $db->count('sounds', ['status' => 0]);

    return $result;
}

