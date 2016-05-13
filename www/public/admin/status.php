<?php
require '../../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/module/company.php';
require $app.'/module/user.php';

class_detect($user['type'], '0');

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
$data['alert_sound'] = get_sound_not_reviewed($db);
$data['uptime'] = get_uptime();
$data['cpuinfo'] = get_cpuinfo();
$data['loadavg'] = get_loadavg();
$data['memory'] = get_memory();
$data['hard'] = get_hard_disk();
$data['uname'] = get_uname();

$latte = new Latte\Engine;
$latte->setTempDirectory('/dev/shm');
$latte->render($app.'/view/admin.status.html', $data);


function get_sound_not_reviewed($db) {
    if (!$db) {
        return 0;
    }

    $result = $db->count('sounds', ['status' => 0]);

    return $result;
}

function get_uptime() {
    $str = "";
    $uptime = "";
    
    if (($str = @file("/proc/uptime")) === false) {
        return "";
    }
    
    $str = explode(" ", implode("", $str));
    $str = trim($str[0]);
    $min = $str / 60;
    $hours = $min / 60;
    $days = floor($hours / 24);
    $hours = floor($hours - ($days * 24));
    $min = floor($min - ($days * 60 * 24) - ($hours * 60));

    if ($days !== 0) {
        $uptime = $days."天";
    }
    if ($hours !== 0) {
        $uptime .= $hours."小时";
    }

    $uptime .= $min."分钟";

    return $uptime;
}

function get_cpuinfo() {
    if (($str = @file("/proc/cpuinfo")) === false) {
        return false;
    }
    
    $str = implode("", $str);
    @preg_match_all("/model\s+name\s{0,}\:+\s{0,}([\w\s\)\(\@.-]+)([\r\n]+)/s", $str, $model);

    if (false !== is_array($model[1])) {
     	$core = sizeof($model[1]);
        $cpu = $model[1][0].' x '.$core.'核';
        return $cpu;
    }

    return "Unknown";
}

function get_hard_disk() {
    $total = round(@disk_total_space(".")/(1024*1024*1024),3); //总
    $avail = round(@disk_free_space(".")/(1024*1024*1024),3); //可用
    $use = $total - $avail; //已用
    $percentage = (floatval($total) != 0) ? round($avail / $total * 100, 0) : 0;

    return ['total' => $total, 'avail' => $avail, 'use' => $use, 'percentage' => $percentage];
}

function get_loadavg() {
    if (($str = @file("/proc/loadavg")) === false) {
        return 'Unknown';
    }

    $str = explode(" ", implode("", $str));
    $str = array_chunk($str, 4);
    $loadavg = implode(" ", $str[0]);

    return $loadavg;
}

function get_memory() {
    if (false === ($str = @file("/proc/meminfo"))) {
        return ['total' => 0, 'free' => 0, 'use' => 0, 'percentage' => 0];
    }
    
    $str = implode("", $str);
    preg_match_all("/MemTotal\s{0,}\:+\s{0,}([\d\.]+).+?MemFree\s{0,}\:+\s{0,}([\d\.]+).+?Cached\s{0,}\:+\s{0,}([\d\.]+).+?SwapTotal\s{0,}\:+\s{0,}([\d\.]+).+?SwapFree\s{0,}\:+\s{0,}([\d\.]+)/s", $str, $buf);
    preg_match_all("/Buffers\s{0,}\:+\s{0,}([\d\.]+)/s", $str, $buffers);
    
    $total = round($buf[1][0] / 1024, 2);
    $free = round($buf[2][0] / 1024, 2);
    $buffers = round($buffers[1][0] / 1024, 2);
    $cached = round($buf[3][0] / 1024, 2);
    $use = $total - $free - $cached - $buffers; //真实内存使用
    $percentage = (floatval($total) != 0) ? round($use / $total * 100, 0) : 0; //真实内存使用率

    return ['total' => $total, 'free' => $free, 'use' => $use, 'percentage' => $percentage];
}

function get_uname() {
    return php_uname();
}

