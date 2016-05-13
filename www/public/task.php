<?php
require '../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/module/task.php';

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

$action = isset($_GET['action']) ? $_GET['action'] : null;
$task = isset($_GET['task']) ? $_GET['task'] : null;

if ($action === 'start' && !is_null($task)) {
    if (is_company_task($redis, $user['company'], $task)) {
        task_start($redis, $user['company'], $task);
    }
    redirect('task.php');
} else if ($action === 'stop' && !is_null($task)) {
    if (is_company_task($redis, $user['company'], $task)) {
        task_stop($redis, $user['company'], $task);
    }
    redirect('task.php');
} else if ($action === 'remove' && !is_null($task)) {
    if (is_company_task($redis, $user['company'], $task)) {
        task_remove($redis, $user['company'], $task);
    }
} else {
    $taskpool = get_taskpool($redis, $user['company']);
    $tasks = [];

    if ($taskpool != null) {
        foreach ($taskpool as $task) {
            $task = get_task($redis, $task);
            if ($task != null) {
                $task['run'] = is_task_run($redis, $user['company'], $task['id']);
                $tasks[] = $task;
            }
        }
    }

    $data['user'] = $user;
    $data['tasks'] = $tasks;

    $radius = redis(RADIUS_HOST, RADIUS_PORT, RADIUS_PASSWORD, RADIUS_DB);
    $data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;
    
    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/task.html', $data);
}

