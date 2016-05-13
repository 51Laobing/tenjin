<?php
require '../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/module/status.php';

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

$data['task'] = get_company_current_task($redis, $user['company']);
$online = 0;
$talking = 0;

$temp = fetch_queue_agents($pbx, $user['company']);
$queue = null;

if ($temp != null) {
    foreach ($temp as $d) {
        $a = fetch_agent($redis, $d['name']);
        if ($a != null) {
            $d['agent_name'] = $a['name'];
            $d['agent_icon'] = str_replace('jpg', 'png', $a['icon']);
        } else {
            $d['agent_name'] = 'Unknown';
            $d['agent_icon'] = '001.png';
        }

        $d['talk_time'] = gmstrftime('%H:%M:%S', $d['talk_time']);
        $d['last_bridge_start'] = date('Y-m-d H:i:s', $d['last_bridge_start']);

        // online counter
        if ($d['status'] === 'Available') {
            $online++;
        }

        /* 座席通话状态检测，无法检测振铃状态
           if (is_agent_talking($pbx, $d['name'])) {
           $d['state'] = 'In a queue call';
           }
        */
        
        // counter
        if ($d['state'] === 'In a queue call') {
            $talking++;
        }
        
        $queue[] = $d;
    }
}

$data['user'] = $user;
$data['queue'] = $queue;
$data['online'] = $online;
$data['talking'] = $talking;
$data['playback'] = get_playback($pbx, $user['company']);
$data['call_concurrent'] = get_call_concurrent($pbx, $user['company']);

$radius = redis(RADIUS_HOST, RADIUS_PORT, RADIUS_PASSWORD, RADIUS_DB);
$data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;

$latte = new Latte\Engine;
$latte->setTempDirectory('/dev/shm');
$latte->render($app.'/view/status.html', $data);
