<?php

function get_agent($redis, $agent) {
    if (!$redis || !is_numeric($agent)) {
        return null;
    }

    $reply = $redis->hMGet('agent.'.$agent, ['name', 'password', 'type', 'callerid', 'company', 'icon', 'status', 'last_login', 'last_ipaddr']);

    // check redis return value
    foreach ($reply as $rep) {
        if ($rep === false) {
            return null;
        }
    }

    // format return value
    $reply['uid'] = $agent;
    $reply['type'] = intval($reply['type']);
    $reply['company'] = intval($reply['company']);
    $reply['status'] = intval($reply['status']);
    $reply['img'] = str_replace('jpg', 'png', $reply['icon']);
    $reply['last_login'] = date('Y-m-d H:i:s', intval($reply['last_login']));
    
    return $reply;
}

function get_all_agent($db, $redis, $company) {
    if (!$db || !is_numeric($company)) {
        return null;
    }

    $company = intval($company);
    $result = $db->select('agent', 'uid', ['company' => $company, 'ORDER' => ['uid ASC']]);

    $agents = null;
    
    if (is_array($result) && count($result) > 0) {
        foreach ($result as $uid) {
            $agent = get_agent($redis, $uid);
            if ($agent != null) {
                $agents[] = $agent;
            }
        }
    }

    return $agents;
}

function agent_lock($db, $redis, $uid) {
    if (!$db || !$redis || !is_numeric($uid)) {
        return false;
    }

    $result = $db->update('agent', ['status' => 0], ['uid' => $uid]);
    if ($result) {
        $redis->hSet('agent.'.$uid, 'status', '0');
        return true;
    }

    return false;
}

function agent_unlock($db, $redis, $uid) {
    if (!$db || !$redis || !is_numeric($uid)) {
        return false;
    }

    $result = $db->update('agent', ['status' => 1], ['uid' => $uid]);
    if ($result) {
        $redis->hSet('agent.'.$uid, 'status', '1');
        return true;
    }

    return false;
}

function the_agent_edit($db, $redis, $esl, $uid, $company_id, $data) {
    if (!$db || !$redis || !$esl || !is_numeric($uid) || !is_numeric($company_id) || !is_array($data)) {
        return false;
    }

    $result = $db->get('agent', ['password', 'callerid'], ['uid' => $uid]);

    if (isset($data['password'])) {
        if ($result['password'] === $data['password']) {
            unset($data['password']);
        }
    }

    if (isset($data['callerid'])) {
        if ($result['callerid'] === $data['callerid']) {
            unset($data['callerid']);
        }
    }
    
    $result = $db->update('agent', $data, ['uid' => $uid]);
    if ($result !== false) {
        $redis->hMSet('agent.'.$uid, $data);
        if (isset($data['password']) || isset($data['callerid'])) {
            agent_update_xml($uid, $company_id, $data);
            sofia_profile_rescan($esl, 'internal');
        }

        return true;
    }
    
    return false;
}

function agent_update_xml($uid, $company_id, $data) {
    if (!is_numeric($uid) || !is_numeric($company_id) || !is_array($data)) {
        return false;
    }

    $xml = "<include>\n";
    $xml .= "  <user id=\"".$uid."\">\n";
    $xml .= "    <params>\n";
    $xml .= "      <param name=\"password\" value=\"".$data['password']."\"/>\n";
    $xml .= "    </params>\n";
    $xml .= "    <variables>\n";
    $xml .= "    <variable name=\"toll_allow\" value=\"domestic,international,local\"/>\n";
    $xml .= "    <variable name=\"accountcode\" value=\"".$company_id."\"/>\n";
    $xml .= "    <variable name=\"user_context\" value=\"default\"/>\n";
    $xml .= "    <variable name=\"effective_caller_id_name\" value=\"Extension ".$uid."\"/>\n";
    $xml .= "    <variable name=\"effective_caller_id_number\" value=\"".$uid."\"/>\n";
    $xml .= "    <variable name=\"outbound_caller_id_name\" value=\"".$data['callerid']."\"/>\n";
    $xml .= "    <variable name=\"outbound_caller_id_number\" value=\"".$data['callerid']."\"/>\n";
    $xml .= "    <variable name=\"callgroup\" value=\"techsupport\"/>\n";
    $xml .= "    </variables>\n";
    $xml .= "  </user>\n";
    $xml .= "</include>\n";

    $path = '/usr/local/freeswitch/conf/directory/default/'.$uid.'.xml';
    $file = fopen($path, "w");
    if ($file) {
        fwrite($file, $xml);
        fclose($file);
        return true;
    }

    return false;
}

function sofia_profile_rescan($esl, $profile) {
    if (!$esl || !is_string($profile)) {
        return false;
    }

    // check profile
    $profile_list = ['internal', 'external'];
    if (!in_array($profile, $profile_list, true)) {
        return false;
    }

    // send esl command
    $esl->send('bgapi sofia profile '.$profile.' rescan');
    return true;
}

function get_callcenter_agent_status($db, $uid) {
    if (!$db) {
        return false;
    }

    $uid = strval($uid);
    $result = $db->get('agents', ['status', 'state'], ['name' => $uid]);
    if (is_array($result)) {
        switch ($result['status']) {
        case 'Logged Out':
        case 'On Break':
            return 1;
        case 'Available':
        case 'Available (On Demand)':
            if ($result['state'] === 'Idle') {
                return 2;
            } else {
                return 3;
            }
        }
    }

    return false;
}

function callcenter_agent_stop($esl, $uid) {
    if (!$esl) {
        return false;
    }

    $cmd = 'bgapi callcenter_config agent set state '.$uid.' Idle';
    $esl->send($cmd);

    return true;
}

function callcenter_agent_start($esl, $uid) {
    if (!$esl) {
        return false;
    }

    $cmd = 'bgapi callcenter_config agent set state '.$uid.' Waiting';
    $esl->send($cmd);

    return true;
}

function get_curr_called($db, $uid) {
    if (!$db) {
        return null;
    }

    $uid = strval($uid);
    
    $result = $db->get('basic_calls', ['cid_num', 'callee_num'], ['OR' => ['cid_num' => $uid, 'callee_num' => $uid]]);
    if ($result) {
        if ($result['cid_num'] === $uid) {
            return $result['callee_num'];
        } else {
            return $result['cid_num'];
        }
    }

    return null;
}

function get_numbers_attribution($phone) {
    if (!is_string($phone)) {
        return '';
    }

    $ch = curl_init();
    $url = 'http://127.0.0.1:8080/number.php?phone='.$phone;
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch , CURLOPT_URL , $url);
    $res = curl_exec($ch);
    return $res;
}

function get_agent_today_all_order($db, $uid) {
    if (!$db) {
        return null;
    }

    $uid = strval($uid);
    
    $result = $db->select('orders', '*', ['AND' => ['creator' => $uid, 'create_time[<>]' => [date('Y-m-d 08:00:00'), date('Y-m-d 20:00:00')]], 'ORDER' => ['create_time ASC']]);
    
    if ($result) {
        return $result;
    }

    return null;
}


function filter_agent_edit($data) {
    if (!is_array($data)) {
        return null;
    }

    $r = null;

    $icons = array(
        '001.jpg',
        '002.jpg',
        '003.jpg',
        '004.jpg',
        '005.jpg',
        '006.jpg',
        '007.jpg',
        '008.jpg',
        '009.jpg',
        '010.jpg',
        '011.jpg',
        '012.jpg',
        '013.jpg',
        '014.jpg',
        '100.jpg',
        '101.jpg',
        '102.jpg',
        '103.jpg',
        '104.jpg',
        '105.jpg',
        '106.jpg',
        '107.jpg',
        '108.jpg',
        '109.jpg',
        '110.jpg'
    );
    
    if (isset($data['icon']) && is_string($data['icon'])) {
        if (in_array($data['icon'], $icons, true)) {
            $r['icon'] = $data['icon'];
        }
    }

    if (isset($data['name']) && is_string($data['name'])) {
        $name = str_replace(' ', '', $data['name']);
        $len = mb_strlen($name);
        if ($len > 0 && $len < 15) {
            $r['name'] = $name;
        }
    }

    if (isset($data['password']) && is_string($data['password'])) {
        $password = str_replace(' ', '', $data['password']);
        $len = mb_strlen($password);
        
        $weak_password = ['12345678', '123456789', '1234567890', '654321', '7654321',
                          '87654321', '987654321', '0987654321', '00000000', '11111111',
                          '22222222', '33333333', '44444444', '55555555', '66666666',
                          '77777777', '88888888', '99999999', 'password', 'abcdefgh'];
        if ($len > 7 && $len < 19 && !in_array($password, $weak_password, true)) {
            $r['password'] = $password;
        }
    }

    $r['type'] = 2;

    if (isset($data['callerid']) && is_string($data['callerid'])) {
        $callerid = str_replace(' ', '', $data['callerid']);
        $len = mb_strlen($callerid);
        if ($len > 0 && $len < 24) {
            $r['callerid'] = $callerid;
        }
    }

    return $r;
}

