<?php
require '../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
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

$company = get_company($redis, $user['company']);

if ($company != null) {
    $data['company'] = $company;
} else {
    $data['company'] = ['name' => 'Unknown', 'concurrent' => 0, 'billing' => 'Unknown', 'level' => 1, 'create_time' => '1970-01-01'];
}


$data['agents'] = get_company_agent_count($db, $user['company']);

$radius = redis(RADIUS_HOST, RADIUS_PORT, RADIUS_PASSWORD, RADIUS_DB);
$account = get_company_account($radius, $data['company']['billing']);

if ($account != null) {
    $data['account'] = $account;
} else {
    $data['account'] = ['money' => 0.00, 'limitmoney' => 0.00, 'todayconsumption' => 0.00];
}
$data['user'] = $user;

$latte = new Latte\Engine;
$latte->setTempDirectory('/dev/shm');
$latte->render($app.'/view/account.html', $data);