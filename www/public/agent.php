<?php
require '../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/module/company.php';
require $app.'/module/agent.php';

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
    if ($_GET['action'] === 'unlock' && isset($_GET['uid'])) {
        if (is_company_agent($redis, $_GET['uid'], $user['company'])) {
            agent_unlock($db, $redis, $_GET['uid']);
        }
    } else if ($_GET['action'] === 'lock' && isset($_GET['uid'])) {
        if (is_company_agent($redis, $_GET['uid'], $user['company'])) {
            agent_lock($db, $redis, $_GET['uid']);
        }
    }
    redirect('agent.php');
} else {
    $data['user'] = $user;
    $data['agents'] = get_all_agent($db, $redis, $user['company']);

    $radius = redis(RADIUS_HOST, RADIUS_PORT, RADIUS_PASSWORD, RADIUS_DB);
    $data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;
    
    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/agent.html', $data);
}
