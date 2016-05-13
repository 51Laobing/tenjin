<?php
require '../../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/libs/esl.php';
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


if (isset($_GET['uid'])) {
    if (is_numeric($_GET['uid'])) {
        $uid = str_replace(' ', '', (string)$_GET['uid']);
        if (is_company_agent($redis, $uid, $user['company'])) {
            $agent = get_agent($redis, $uid);
            if ($agent === null) {
                redirect('agent.php');
            }

            $data['user'] = $user;
            $data['agent'] = $agent;

            $radius = redis(RADIUS_HOST, RADIUS_PORT, RADIUS_PASSWORD, RADIUS_DB);
            $data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;
    
            $latte = new Latte\Engine;
            $latte->setTempDirectory('/dev/shm');
            $latte->render($app.'/view/agent.edit.html', $data);
        } else {
            redirect('agent.php');
        }
    } else {
        redirect('agent.php');
    }
} else if (isset($_POST['uid'])) {
    if (is_numeric($_POST['uid'])) {
        $uid = $_POST['uid'];
        if (is_company_agent($redis, $uid, $user['company'])) {
            $data = filter_agent_edit($_POST);
            if ($data != null) {
                $esl = new eslConnection(ESL_HOST, ESL_PORT, ESL_PASSWORD);
                $res = the_agent_edit($db, $redis, $esl, $uid, $user['company'], $data);
                if ($res) {
                    $esl->disconnect();
                    redirect('agent.php');
                }
            }
            redirect('agent/edit.php?uid='.$uid);
        } else {
            redirect('agent.php');
        }
    } else {
        redirect('agent.php');
    }
} else {
    redirect('agent.php');
}

