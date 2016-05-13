<?php

function fetch_all_service($db, $pbx) {
    if (!$db || !$pbx) {
        return null;
    }

    $result = $db->select('company', '*');
    if (is_array($result)) {
        $count = count($result);
        for ($i = 0; $i < $count; $i++) {
            $service = get_service($pbx, $result[$i]['id']);
            $result[$i]['curr_concurrent'] = $service['curr_concurrent'];
            $result[$i]['pid'] = $service['pid'];
            $result[$i]['status'] = $service['status'];
        }

        return $result;
    }

    return null;
}


function get_service($pbx, $company_id) {
    $data = ['curr_concurrent' => 0, 'pid' => 0, 'status' => 0];
    if (!$pbx || !is_numeric($company_id)) {
        return $data;
    }

    $company_id = intval($company_id);

    // get company curr concurrent
    $data['curr_concurrent'] = get_call_concurrent($pbx, $company_id);


    // get company service pid
    $pid_file = '/var/service/'.$company_id.'.pid';
    if (file_exists($pid_file)) {
        $file = fopen($pid_file ,  "r" );
        if ($file) {
            $pid = fgets($file, 4096);
            if ($pid !== false) {
                $data['pid'] = intval($pid);
            }
            fclose($file);
        }

        // get company service status
        if ($data['pid'] > 0) {
            $dir = '/proc/'.$data['pid'];
            if(is_dir($dir)) {
                $data['status'] = 1;
            }
        }
    }

    return $data;
}

function get_call_concurrent($pbx, $company_id) {
    if (!$pbx || !is_numeric($company_id)) {
        return 0;
    }

    $company_id = strval($company_id);

    $result = $pbx->count('channels', ['initial_cid_num' => $company_id]);

    return $result;
}

function is_company_exist($redis, $company_id) {
    if (!$redis || !is_numeric($company_id)) {
        return false;
    }

    $company_id = intval($company_id);
    if ($redis->exists('company.'.$company_id)) {
        return true;
    }

    return false;
}

function service($redis, $company_id, $action) {
    if (!$redis || !is_numeric($company_id) || !is_string($action)) {
        return false;
    }

    $action_list = ['start', 'stop'];
    if (!in_array($action, $action_list, true)) {
        return false;
    }

    $company_id = intval($company_id);
    if (!is_company_exist($redis, $company_id)) {
        return false;
    }
    
    if ($action === 'start') {
        $server = '/usr/bin/server -d';
        $config = '/etc/config.conf';
        $cmd = $server.' -f '.$config.' -c '.$company_id;
        system($cmd);
        return true;
    } else if ($action === 'stop') {
        // get company service pid
        $pid_file = '/var/service/'.$company_id.'.pid';
        $file = fopen($pid_file ,  "r" );
        if ($file) {
            $pid = fgets($file, 4096);
            if (($pid !== false) && (intval($pid) > 1)) {
                if (is_dir('/proc/'.$pid)) {
                    system('/usr/bin/kill -9 '.$pid);
                    fclose($file);
                    return true;
                }
            }
            fclose($file);
        }
    }
    
    return false;
}

