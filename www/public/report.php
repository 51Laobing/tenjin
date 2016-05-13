<?php
require '../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/module/company.php';
require $app.'/module/cdr.php';

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

$cdr = new medoo([
    'database_type' => 'pgsql',
    'database_name' => CDR_DB,
    'server' => CDR_HOST,
    'port' => CDR_PORT,
    'username' => CDR_USER,
    'password' => CDR_PASSWORD,
    'charset' => 'utf8'
]);

$data['user'] = $user;
$radius = redis(RADIUS_HOST, RADIUS_PORT, RADIUS_PASSWORD, RADIUS_DB);

if (isset($_GET['action']) && $_GET['action'] === 'query') {
    $where = filter_report_query($_GET);
    $agents = $db->select('agent', ['uid', 'name'], ['company' => intval($user['company']), 'ORDER' => 'uid ASC']);
    
    if ($where != null) {
        $report = null;
        $result = get_cdr_report($cdr, $user['company'], $where['start'], $where['end']);

        foreach	($agents as $agent) {
            $uid = $agent['uid'];
            $report[$uid] = ['uid' => $uid, 'name' => $agent['name'], 'total' => 0, 'call_in' => 0, 'call_out' => 0, 'talktime' => 0];
            foreach ($result as $data) {
                if ($data['caller_id_number'] == $uid || $data['destination_number'] == $uid) {
                    $report[$uid]['total'] += 1;
                    if ($data['caller_id_number'] == $uid) {
                        $report[$uid]['call_out'] += 1;
                    } else {
                        $report[$uid]['call_in'] += 1;
                    }
                    $report[$uid]['talktime'] += $data['billsec'];
                }
            }
        }

        if ($where['export']) {
            header('Content-type: text/csv');
            header('Content-Disposition: attachment; filename="'.date('Y-m-d',time()).'.csv"');
            echo '座席账号,座席姓名,通话总数,呼入总数,呼出总数,通话时长,平均通话时长',"\n";
            if (is_array($report)) {
                foreach ($report as $agent) {
                    $average_talktime = $agent['total'] > 0 ? $agent['talktime'] / $agent['total'] : 0;
                    echo $agent['uid'],',',$agent['name'],',',$agent['total'],',',$agent['call_in'],',',$agent['call_out'],',',gmstrftime('%H:%M:%S', $agent['talktime']),',',gmstrftime('%H:%M:%S', $average_talktime),"\n";
                }
            }
            exit;
        }
        
        $data['where'] = $where;
        $data['report'] = $report;
        $data['error'] = false;
        $data['user'] = $user;
        $data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;
        
        $latte = new Latte\Engine;
        $latte->setTempDirectory('/dev/shm');
        $latte->render($app.'/view/report.html', $data);
    } else {
        $report = null;
        foreach ($agents as $agent) {
            $uid = $agent['uid'];
            $report[$uid] = ['name' => $agent['name'], 'total' => 0, 'call_in' => 0, 'call_out' => 0, 'talktime' => 0];
        }

        $data['where'] = ['start' => date('Y-m-d 08:00:00'), 'end' => date('Y-m-d 20:00:00'), 'export' => false];
        $data['report'] = $report;
        $data['error'] = true;
        $data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;
        
        $latte = new Latte\Engine;
        $latte->setTempDirectory('/dev/shm');
        $latte->render($app.'/view/report.html', $data);
    }
} else {
    $report = null;
    $agents = $db->select('agent', ['uid', 'name'], ['company' => intval($user['company']), 'ORDER' => 'uid ASC']);
    
    foreach ($agents as $agent) {
        $uid = $agent['uid'];
        $report[$uid] = ['name' => $agent['name'], 'total' => 0, 'call_in' => 0, 'call_out' => 0, 'talktime' => 0];
    }

    $data['where'] = ['start' => date('Y-m-d 08:00:00'), 'end' => date('Y-m-d 20:00:00'), 'export' => false];
    $data['report'] = $report;
    $data['error'] = false;
    $data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;
    
    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/report.html', $data);    
}
