<?php
require '../../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/libs/esl.php';
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

if (isset($_POST['action']) && $_POST['action'] === 'create') {
    $data = filter_create_company($_POST);
    if ($data != null) {
        $esl = new eslConnection(ESL_HOST, ESL_PORT, ESL_PASSWORD);
        the_create_company($db, $redis, $esl, $data);
        if ($esl) {
            $esl->disconnect();
        }
    }
    redirect('admin/company.php');
} else if (isset($_GET['action']) && $_GET['action'] === 'remove') {
    $company_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($company_id > 0) {
        $esl = new eslConnection(ESL_HOST, ESL_PORT, ESL_PASSWORD);
        the_company_remove($db, $redis, $esl, $company_id);
        if ($esl) {
            $esl->disconnect();
        }
    }
    redirect('admin/company.php');
} else {
    $data['user'] = $user;
    $data['companys'] = get_all_company($db);
    $data['total'] = the_total_company($data['companys']);
    $data['alert_sound'] = get_sound_not_reviewed($db);
    
    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/admin.company.html', $data);
}

function the_total_company($companys) {
    $total['companys'] = count($companys);
    $total['agents'] = 0;
    $total['concurrents'] = 0;
    $total['online'] = 0;
    
    if (!is_array($companys)) {
        return $total;
    }

    foreach ($companys as $company) {
        $total['agents'] += $company['agents'];
        $total['concurrents'] += $company['concurrent'];
        $total['online'] += $company['online'];
    }
    
    return $total;
}

function get_sound_not_reviewed($db) {
    if (!$db) {
        return 0;
    }

    $result = $db->count('sounds', ['status' => 0]);

    return $result;
}

