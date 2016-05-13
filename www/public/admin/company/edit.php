<?php
require '../../../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/module/company.php';

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

if (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $company_id = intval($_POST['id']);
    $data = filter_company_edit($_POST);
    if ($data != null) {
        the_company_edit($db, $redis, $company_id, $data);
    }
    redirect('admin/company.php');
} else if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $company_id = intval($_GET['id']);

    $data['user'] = $user;
    $data['alert_sound'] = get_sound_not_reviewed($db);
    $data['company'] = fetch_company($db, $company_id);
                     
    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/admin.company.edit.html', $data);
} else {
    redirect('admin/company.php');
}

function get_sound_not_reviewed($db) {
    if (!$db) {
        return 0;
    }

    $result = $db->count('sounds', ['status' => 0]);

    return $result;
}

