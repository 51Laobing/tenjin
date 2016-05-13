<?php
require '../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/libs/esl.php';
require $app.'/module/agent.php';
require $app.'/module/exten.php';

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

$pbx = new medoo([
    'database_type' => 'pgsql',
	'database_name' => PBX_DB,
    'server' => PBX_HOST,
    'port' => PBX_PORT,
    'username' => PBX_USER,
    'password' => PBX_PASSWORD,
    'charset' => 'utf8'
]);


if (isset($_GET['action'])) {
    if ($_GET['action'] === 'unregister') {
        if (isset($_GET['exten']) && is_numeric($_GET['exten'])) {
            $exten = str_replace(' ', '', $_GET['exten']);
            if (is_company_agent($redis, $exten, $user['company'])) {
                $result = $pbx->get('sip_registrations', ['sip_user', 'sip_host'], ['sip_user' => $exten]);
                if ($result) {
                    $uri = $result['sip_user'].'@'.$result['sip_host'];
                    $esl = new eslConnection(ESL_HOST, ESL_PORT, ESL_PASSWORD);
                    exten_unregister($esl, $uri);
                    if ($esl) {
                        $esl->disconnect();
                    }
                }
            }
        }
    }
    redirect('exten.php');
} else {
    $data['user'] = $user;
    $data['agents'] = get_all_agent($db, $redis, $user['company']);

    $extens = null;

    if ($data['agents'] != null) {
        foreach ($data['agents'] as $agent) {
            $exten = get_exten_reg_info($pbx, $agent['uid']);
            $extens[$exten['sip_user']] = $exten;
            $extens[$exten['sip_user']]['name'] = $agent['name'];
        }
    }
    $data['extens'] = $extens;

    $radius = redis(RADIUS_HOST, RADIUS_PORT, RADIUS_PASSWORD, RADIUS_DB);
    $data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;
    
    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/exten.html', $data);
}

