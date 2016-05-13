<?php
require '../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/module/company.php';
require $app.'/module/cdr.php';

class_detect($user['type'], '1');

$db = new medoo([
    'database_type' => 'pgsql',
    'database_name' => CDR_DB,
    'server' => CDR_HOST,
    'port' => CDR_PORT,
    'username' => CDR_USER,
    'password' => CDR_PASSWORD,
    'charset' => 'utf8'
]);

if (isset($_GET['action'])) {
    if ($_GET['action'] === 'query') {
        $data = filter_cdr_query($_GET);
        if ($data != null) {
            if (!$db) {
                redirect('cdr.php');
            }
            
            $record = null;
            
            $where['start_stamp[<>]'] = [$data['start'], $data['end']];
            $where['billsec[>]'] = $data['duration'];

            if ($data['caller'] != null) {
                $where['caller_id_number'] = $data['caller'];
            }

            if ($data['called'] != null) {
                $where['destination_number'] = $data['called'];
            }

            $where['accountcode'] = strval($user['company']);
            $where['destination_number[!]'] = ['service', '7', '9', '001', '002', '003', '004'];
            
            $count = 35;
            $result = $db->select('cdr', ['id', 'caller_id_number', 'destination_number', 'start_stamp', 'billsec', 'bleg_uuid'], ['AND' => $where, 'ORDER' => 'start_stamp DESC', 'LIMIT' => [$data['page'] * $count, $count]]);

            if ($result) {
                foreach ($result as $r) {
                    $r['start_stamp'] = date('Y-m-d H:i:s', strtotime(substr($r['start_stamp'], 0, 19)));
                    $r['billsec'] = gmstrftime('%H:%M:%S', (int)$r['billsec']);
                    $r['file'] = date('/Y/m/d/', strtotime($r['start_stamp'])).$r['bleg_uuid'].'.wav';
                    $record[] = $r;
                }
            }

            $data['where'] = $data;
            $data['user'] = $user;
            $data['result'] = $record;

            $radius = redis(RADIUS_HOST, RADIUS_PORT, RADIUS_PASSWORD, RADIUS_DB);
            $data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;
            
            $latte = new Latte\Engine;
            $latte->setTempDirectory('/dev/shm');
            $latte->render($app.'/view/cdr.html', $data);
        } else {
            redirect('cdr.php');
        }
    } else {
        redirect('cdr.php');
    }
} else {
    $record = null;

    $data['where']['start'] = date('Y-m-d 08:00:00', time());
    $data['where']['end'] = date('Y-m-d 20:00:00', time());
    $data['where']['duration'] = 0;
    $data['where']['caller'] = null;
    $data['where']['called'] = null;
    $data['where']['page'] = 0;
    $data['result'] = $record;
    $data['user'] = $user;

    $radius = redis(RADIUS_HOST, RADIUS_PORT, RADIUS_PASSWORD, RADIUS_DB);
    $data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;
    
    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/cdr.html', $data);
}

