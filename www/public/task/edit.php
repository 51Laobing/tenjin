<?php
require '../../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/module/task.php';
require $app.'/module/sounds.php';

class_detect($user['type'], '1');

if (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $db = new medoo([
        'database_type' => 'pgsql',
        'database_name' => PGSQL_DB,
        'server' => PGSQL_HOST,
        'port' => PGSQL_PORT,
        'username' => PGSQL_USER,
        'password' => PGSQL_PASSWORD,
        'charset' => 'utf8'
    ]);

    $task_id = intval($_POST['id']);
    $data = filter_task_edit($redis, $task_id, $user['company'], $_POST);
    if ($data != null) {
        if (is_company_task($redis, $user['company'], $task_id)) {
            task_edit($redis, $task_id, $data);
        }
    }
    
    redirect('task.php');
} else {

    $task = isset($_GET['task']) ? $_GET['task'] : null;
    if (is_null($task)) {
        redirect('task.php');
    }

    $task = get_task($redis, $task);
    if (is_null($task)) {
        redirect('task.php');
    }

    $data['user'] = $user;
    $data['task'] = $task;

    $db = new medoo([
        'database_type' => 'pgsql',
        'database_name' => PGSQL_DB,
        'server' => PGSQL_HOST,
        'port' => PGSQL_PORT,
        'username' => PGSQL_USER,
        'password' => PGSQL_PASSWORD,
        'charset' => 'utf8'
    ]);

    $data['sounds'] = get_all_pass_sound($db, $user['company']);

    $radius = redis(RADIUS_HOST, RADIUS_PORT, RADIUS_PASSWORD, RADIUS_DB);
    $data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;
    
    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/task.edit.html', $data);
}

