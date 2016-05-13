<?php
require '../../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/module/task.php';
require $app.'/module/sounds.php';

class_detect($user['type'], '1');

if (isset($_POST['name'])) {
    $data = filter_task_upload($redis, $user['company'], $_POST);
    if ($data != null) {
        // check task pool max number
        if (get_taskpool_total($redis, $user['company']) < 18) {
            $file = filter_task_upload_file($_FILES);
            if ($file != null) {
                task_create($redis, $user['company'], $data, $file);
            }
        }
    }
    redirect('task.php');
} else {
    $db = new medoo([
        'database_type' => 'pgsql',
        'database_name' => PGSQL_DB,
        'server' => PGSQL_HOST,
        'port' => PGSQL_PORT,
        'username' => PGSQL_USER,
        'password' => PGSQL_PASSWORD,
        'charset' => 'utf8'
    ]);

    $data['user'] = $user;
    $data['sounds'] = get_all_pass_sound($db, $user['company']);

    $radius = redis(RADIUS_HOST, RADIUS_PORT, RADIUS_PASSWORD, RADIUS_DB);
    $data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;
    
    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/task.add.html', $data);
}

