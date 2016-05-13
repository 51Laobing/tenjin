<?php
require '../../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/module/company.php';
require $app.'/module/user.php';
require $app.'/module/sounds.php';

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

if (isset($_GET['action']) && $_GET['action'] === 'pass') {
    $sound_id = (isset($_GET['id']) && is_numeric($_GET['id'])) ? intval($_GET['id']) : null;
    if ($sound_id != null) {
        the_pass_sound($db, $redis, $sound_id);
    }
    redirect('admin/sounds.php');
} else {
    $data['user'] = $user;
    $data['alert_sound'] = get_sound_not_reviewed($db);

    $data['sounds'] = fetch_all_sound($db);
    if ($data['sounds'] != null) {
        $count = count($data['sounds']);
        for ($i = 0; $i < $count; $i++) {
            $data['sounds'][$i]['company_name'] = get_company_name($db, $data['sounds'][$i]['company']);
        }
    }

    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/admin.sounds.html', $data);
}
function get_sound_not_reviewed($db) {
    if (!$db) {
        return 0;
    }

    $result = $db->count('sounds', ['status' => 0]);

    return $result;
}

