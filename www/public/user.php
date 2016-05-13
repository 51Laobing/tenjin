<?php
require '../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/libs/esl.php';
require $app.'/module/agent.php';

class_detect($user['type'], '2');

$db = new medoo([
    'database_type' => 'pgsql',
    'database_name' => PGSQL_DB,
    'server' => PGSQL_HOST,
    'port' => PGSQL_PORT,
    'username' => PGSQL_USER,
    'password' => PGSQL_PASSWORD,
    'charset' => 'utf8'
]);

$orderdb = new medoo([
    'database_type' => 'pgsql',
    'database_name' => ORDER_DB,
    'server' => ORDER_HOST,
    'port' => ORDER_PORT,
    'username' => ORDER_USER,
    'password' => ORDER_PASSWORD,
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

if (isset($_GET['status']) && is_string($_GET['status'])) {
    $status = str_replace(' ', '', $_GET['status']);
    if ($status === 'start') {
        $esl = new eslConnection(ESL_HOST, ESL_PORT, ESL_PASSWORD);
        callcenter_agent_start($esl, $user['uid']);
        if ($esl) {
            $esl->disconnect();
        }
    } else if ($status === 'stop') {
        $esl = new eslConnection(ESL_HOST, ESL_PORT, ESL_PASSWORD);
        callcenter_agent_stop($esl, $user['uid']);
        if ($esl) {
            $esl->disconnect();
        }
    }
    usleep(500000);
    redirect('user.php');
} else {
    $data['user'] = $user;
    $data['status'] = get_callcenter_agent_status($pbx, $user['uid']);
    $task = get_curr_task($redis, $user['company']);
    if ($task > 0) {
        $task = get_task($redis, $task);
    } else {
        $task = ['type' => 0, 'name' => 'No Task', 'total' => 0, 'business' => 0, 'remainder' => 0];
    }

    $data['task'] = $task;

    $data['orders'] = get_agent_today_all_order($orderdb, $user['uid']);
    if (count($data['orders']) > 0) {
        $temp = get_all_product($db, $user['company']);
        $products = null;
        if (is_array($temp)) {
            foreach ($temp as $product) {
                $products[$product['id']] = $product;
            }
            $data['products'] = $products;
        }
    }
    
    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/user.html', $data);
}

function get_curr_task($redis, $company_id) {
    if (!$redis || !is_numeric($company_id)) {
        return 0;
    }

    $company_id = intval($company_id);
    
    $reply = $redis->hGet('company.'.$company_id, 'task');
    if ($reply) {
        return intval($reply);
    }

    return 0;
}

function get_task($redis, $task_id) {
    if (!$redis || !is_numeric($task_id)) {
        return null;
    }

    $task_id = intval($task_id);

    $type = $redis->hGet('task.'.$task_id, 'type');
    if (!$type) {
        return null;
    }

    $type = intval($type);
    $reply = null;
    
    switch ($type) {
    case 1:
    case 2:
        $reply = $redis->hMGet('task.'.$task_id, ['name', 'type', 'business', 'dial', 'play', 'sound', 'total', 'answer', 'complete']);
        $reply['id'] = $task_id;
        $reply['type'] = $type;
        $reply['name'] = $reply['name'] ? $reply['name'] : 'Unknown';

        if ($type === 1) {
            $reply['dial'] = $reply['dial'] ? $reply['dial'] : 8;
        } else {
            $reply['dial'] = $reply['dial'] ? $reply['dial'] : 80;
        }

        $reply['business'] = $reply['business'] ? intval($reply['business']) : 1;
        $reply['play'] = $reply['play'] ? intval($reply['play']) : 0;
        $reply['sound'] = $reply['sound'] ? intval($reply['sound']) : 0;
        $reply['total'] = $reply['total'] ? intval($reply['total']) : 0;
        $reply['remainder'] = get_task_remainder($redis, $reply['id']);
        $reply['answer'] = $reply['answer'] ? intval($reply['answer']) : 0;
        $reply['complete'] = $reply['complete'] ? intval($reply['complete']) : 0;
        break;
    case 3:
        $reply = $redis->hMGet('task.'.$task_id, ['name', 'type', 'business', 'total', 'answer']);
        $reply['id'] = $task_id;
        $reply['type'] = $type;
        $reply['name'] = $reply['name'] ? $reply['name'] : 'Unknown';
        $reply['business'] = $reply['business'] ? intval($reply['business']) : 1;
        $reply['total'] = $reply['total'] ? intval($reply['total']) : 0;
        $reply['remainder'] = get_task_remainder($redis, $reply['id']);
        $reply['answer'] = $reply['answer'] ? intval($reply['answer']) : 0;
        break;
    case 4:
        $reply = $redis->hMGet('task.'.$task_id, ['name', 'type', 'presskey', 'sound', 'total', 'answer']);
        $reply['id'] = $task_id;
        $reply['type'] = $type;
        $reply['name'] = $reply['name'] ? $reply['name'] : 'Unknown';
        $reply['sound'] = $reply['sound'] ? intval($reply['sound']) : 0;
        $reply['total'] = $reply['total'] ? intval($reply['total']) : 0;
        $reply['presskey'] = $reply['presskey'] ? intval($reply['presskey']) : 0;
        $reply['remainder'] = get_task_remainder($redis, $reply['id']);
        $reply['answer'] = $reply['answer'] ? intval($reply['answer']) : 0;
        break;
    }

    return $reply;
}

function get_task_remainder($redis, $task_id) {
    if (!$redis || !is_numeric($task_id)) {
        return 0;
    }

    $task_id = intval($task_id);
    $reply = $redis->lLen('data.'.$task_id);

    return intval($reply);
}

function get_all_product($db, $company_id) {
    if (!$db || !is_numeric($company_id)) {
        return null;
    }

    $company_id = intval($company_id);
    $result = $db->select('product', '*', ['company' => $company_id, 'ORDER' => ['id ASC']]);
    if ($result) {
        return $result;
    }

    return null;
}
