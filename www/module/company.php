<?php

function get_company($redis, $company_id) {
    if (!$redis || !is_numeric($company_id)) {
        return null;
    }

    $company_id = intval($company_id);

    $reply = $redis->hMGet('company.'.$company_id, ['id', 'name', 'concurrent', 'billing', 'level', 'create_time', 'sound_check']);
    if (is_array($reply)) {
        // check return value
        if (in_array(false, $reply, true)) {
            return null;
        }

        // format return value
        $reply['concurrent'] = intval($reply['concurrent']);
        $reply['level'] = intval($reply['level']);
        $reply['sound_check'] = intval($reply['sound_check']);
        
        return $reply;
    } else {
        return null;
    }
}

function fetch_company($db, $company_id) {
    if (!$db || !is_numeric($company_id)) {
        return null;
    }

    $company_id = intval($company_id);
    
    $result = $db->get('company', '*', ['id' => $company_id]);
    if ($result) {
        return $result;
    }

    return null;
}

function get_all_company($db) {
    if (!$db) {
        return null;
    }

    $result = $db->select('company', '*', ["ORDER" => "id ASC"]);
    $companys = null;
    
    if (is_array($result)) {
        foreach ($result as $company) {
            $company['agents'] = get_company_agent_count($db, $company['id']);
            $company['online'] = 0;
            $companys[] = $company;
        }
    }

    return $companys;
}

function get_company_agent_count($db, $company) {
    if (!$db || !is_numeric($company)) {
        return 0;
    }

    $company = intval($company);
    
    $result = $db->count('agent', ['company' => $company]);
    if (is_numeric($result)) {
        return $result;
    }

    return 0;
}

function get_company_account($redis, $billing) {
    if (!$redis || !is_string($billing)) {
        return null;
    }

    $reply = $redis->hmGet($billing, array('money', 'limitmoney', 'todayconsumption'));
    if (is_array($reply)) {
        return $reply;
    }

    return null;
}

function filter_create_company($post) {
    if (!is_array($post)) {
        return null;
    }

    $data = null;
    
    if (isset($post['company_name']) && is_string($post['company_name'])) {
        $company_name = str_replace(' ', '', $post['company_name']);
        $len = mb_strlen($company_name);
        if ($len > 0) {
            $data['company_name'] = $company_name;
        } else {
            return null;
        }
    } else {
        return null;
    }

    if (isset($post['agents']) && is_numeric($post['agents'])) {
        $agents = intval($post['agents']);
        if ($agents > 0 && $agents <= 120) {
            $data['agents'] = $agents;
        } else {
            return null;
        }
    } else {
        return null;
    }

    if (isset($post['gw_user']) && is_string($post['gw_user'])) {
        $gw_user = str_replace(' ', '', $post['gw_user']);
        $len = mb_strlen($gw_user);
        if ($len > 0) {
            $data['gw_user'] = $gw_user;
        } else {
            return null;
        }
    } else {
        return null;
    }

    if (isset($post['gw_password']) && is_string($post['gw_password'])) {
        $gw_password = str_replace(' ', '', $post['gw_password']);
        $len = mb_strlen($gw_password);
        if ($len > 0) {
            $data['gw_password'] = $gw_password;
        } else {
            return null;
        }
    } else {
        return null;
    }

    if (isset($post['gw_ip']) && is_string($post['gw_ip'])) {
        $gw_ip = str_replace(' ', '', $post['gw_ip']);
        $len = mb_strlen($post['gw_ip']);
        if (filter_var($gw_ip, FILTER_VALIDATE_IP)) {
            $data['gw_ip'] = $gw_ip;
        } else {
            return null;
        }
    } else {
        return null;
    }

    if (isset($post['concurrent']) && is_numeric($post['concurrent'])) {
        $concurrent = intval($post['concurrent']);
        if ($concurrent > 0) {
            $data['concurrent'] = $concurrent;
        } else {
            return null;
        }
    } else {
        return null;
    }

    if (isset($post['billing']) && is_string($post['billing'])) {
        $billing = str_replace(' ', '', $post['billing']);
        $len = mb_strlen($billing);
        if ($len > 0) {
            $data['billing'] = $billing;
        } else {
            $data['billing'] = 'unknown';
        }
    } else {
        $data['billing'] = 'unknown';
    }

    if (isset($post['sound_check']) && is_string($post['sound_check'])) {
        $sound_check = str_replace(' ', '', $post['sound_check']);
        if ($sound_check === 'on') {
            $data['sound_check'] = 1;
        } else {
            $data['sound_check'] = 0;
        }
    } else {
        $data['sound_check'] = 0;
    }

    return $data;
}

function the_create_company($db, $redis, $esl, $data) {
    if (!$db || !$redis || !is_array($data)) {
        return false;
    }

    $company_id = null;
    $create_time = date('Y-m-d H:i:s');
    
    $result = $db->query("insert into company(name, concurrent, billing, level, create_time, sound_check)".
                         " values('{$data['company_name']}', {$data['concurrent']}, '{$data['billing']}', 1, '{$create_time}', {$data['sound_check']}) returning id")->fetchAll();
    
    if (isset($result[0]['id'])) {
        $company_id = intval($result[0]['id']);
    } else {
        return false;
    }

    if ($company_id < 1) {
        return false;
    }
    
    $result = $db->get('company', '*', ['id' => $company_id]);
    if (is_array($result)) {
        $redis->hMSet('company.'.$company_id, $result);
    }
    
    // create company all agent
    $password = substr(md5(time()), 0, 8);
    $agents = null;
    
    for ($i = 1; $i <= $data['agents']; $i++) {
        $agents[$i] = $uid = strval($company_id * 1000 + $i);
        the_create_agent($db, $redis, $uid, $password, $company_id);
    }
    sofia_profile_internal_rescan($esl);

    the_queue_create($company_id);
    the_agents_create($company_id, $agents);
    the_tiers_create($company_id, $agents);



    
    // create company gateway
    the_create_gateway($db, $esl, $company_id, $data['gw_user'], $data['gw_password'], $data['gw_ip']);

    reloadxml($esl);
    
    sleep(1);
    callcenter_config_queue_load($esl, $company_id);
    callcenter_config_agent_reload($esl, $company_id, $agents);
    callcenter_config_tier_reload($esl, $company_id, $agents);
    
    return true;
}

function the_create_agent($db, $redis, $uid, $password, $company_id) {
    if (!$db || !$redis || !is_numeric($company_id)) {
        return false;
    }

    $company_id = intval($company_id);

    $agent['uid'] = $uid;
    $agent['name'] = $uid;
    $agent['password'] = $password;
    $agent['type'] = 2;
    $agent['callerid'] = $uid;
    $agent['company'] = $company_id;
    $agent['icon'] = '001.jpg';
    $agent['status'] = 1;
    $agent['last_login'] = '1970-01-01 08:00:00';
    $agent['last_ipaddr'] = '0.0.0.0';
    
    $result = $db->insert('agent', $agent);
    
    $xml = "<include>\n";
    $xml .= "  <user id=\"".$uid."\">\n";
    $xml .= "    <params>\n";
    $xml .= "      <param name=\"password\" value=\"".$password."\"/>\n";
    $xml .= "    </params>\n";
    $xml .= "    <variables>\n";
    $xml .= "      <variable name=\"toll_allow\" value=\"domestic,international,local\"/>\n";
    $xml .= "      <variable name=\"accountcode\" value=\"".$company_id."\"/>\n";
    $xml .= "      <variable name=\"user_context\" value=\"default\"/>\n";
    $xml .= "      <variable name=\"effective_caller_id_name\" value=\"Extension ".$uid."\"/>\n";
    $xml .= "      <variable name=\"effective_caller_id_number\" value=\"".$uid."\"/>\n";
    $xml .= "      <variable name=\"outbound_caller_id_name\" value=\"".$uid."\"/>\n";
    $xml .= "      <variable name=\"outbound_caller_id_number\" value=\"".$uid."\"/>\n";
    $xml .= "      <variable name=\"callgroup\" value=\"techsupport\"/>\n";
    $xml .= "    </variables>\n";
    $xml .= "  </user>\n";
    $xml .= "</include>\n";

    $path = '/usr/local/freeswitch/conf/directory/default/'.$uid.'.xml';
    $file = fopen($path, "w");
    if ($file) {
        fwrite($file, $xml);
        fclose($file);
    }

    $agent['last_login'] = '0';
    
    $redis->hMSet('agent.'.$uid, $agent);

    return true;
}

function the_create_gateway($db, $esl, $company_id, $username, $password, $ipaddr) {
    if (!$db || !$esl || !is_numeric($company_id)) {
        return false;
    }

    $company_id = intval($company_id);

    $result = $db->insert('gateway', ['username' => $username, 'password' => $password, 'ip_addr' => $ipaddr, 'company' => $company_id, 'registered' => 1]);

    $xml = "<include>\n";
    $xml .= "  <gateway name=\"trunk.".$company_id.".gw\">\n";
    $xml .= "    <param name=\"realm\" value=\"".$ipaddr."\"/>\n";
    $xml .= "    <param name=\"username\" value=\"".$username."\"/>\n";
    $xml .= "    <param name=\"password\" value=\"".$password."\"/>\n";
    $xml .= "    <param name=\"proxy\" value=\"".$ipaddr."\"/>\n";
    $xml .= "    <param name=\"register\" value=\"true\"/>\n";
    $xml .= "  </gateway>\n";
    $xml .= "</include>\n";

    $path = '/usr/local/freeswitch/conf/sip_profiles/external/trunk.'.$company_id.'.xml';
    $file = fopen($path, "w");
    if ($file) {
        fwrite($file, $xml);
        fclose($file);
    }
    sofia_profile_external_rescan($esl);

    return false;
}

function sofia_profile_internal_rescan($esl) {
    if (!$esl) {
        return false;
    }

    $esl->send('bgapi sofia profile internal rescan');
    
    return true;
}

function sofia_profile_external_rescan($esl) {
    if (!$esl) {
        return false;
    }

    $esl->send('bgapi sofia profile external rescan');

    return true;
}

function the_company_remove($db, $redis, $esl, $company_id) {
    if (!$db || !$redis || !$esl || !is_numeric($company_id)) {
        return false;
    }

    $company_id = intval($company_id);

    // remove company gateway
    the_gateway_remove($db, $esl, $company_id);
    
    // remove company all sound
    the_sound_remove($db, $redis, $company_id);

    // remove company all user
    $result = $db->select('users', 'uid', ['company' => $company_id]);
    if (is_array($result)) {
        foreach ($result as $uid) {
            the_user_remove($db, $redis, $uid);
        }
    }

    // remove company all product
    $result = $db->select('product', 'id', ['company' => $company_id]);
    if (is_array($result)) {
        foreach ($result as $product_id) {
            the_product_remove($db, $redis, $product_id);
        }
    }



    // remove mod_callcenter queue
    the_queue_remove($esl, $company_id);

    // get company all agent list
    $agents = $db->select('agent', 'uid', ['company' => $company_id]);
    
    // remove mod_callcenter all tiers
    the_tiers_remove($esl, $company_id, $agents);
    
    // remove comapny and mod_callcenter all agent
    the_agent_remove($db, $redis, $esl, $company_id);
    sofia_profile_internal_rescan($esl);

    // remove company
    $db->delete('company', ['id' => $company_id]);
    $redis->delete('company.'.$company_id);
    $redis->delete('taskpool.'.$company_id);
    
    return true;
}

function the_gateway_remove($db, $esl, $company_id) {
    if (!$db || !$esl || !is_numeric($company_id)) {
        return false;
    }

    // remove gateway for database
    $result = $db->delete('gateway', ['company' => $company_id]);

    // remove gateway xml file
    unlink('/usr/local/freeswitch/conf/sip_profiles/external/trunk.'.$company_id.'.xml');
    
    // esl kill gateway
    $company_id = intval($company_id);
    $cmd = 'bgapi sofia profile external killgw trunk.'.$company_id.'.gw';
    $esl->send($cmd);

    return true;
}

function the_agent_remove($db, $redis, $esl, $company_id) {
    if (!$db || !$redis || !$esl || !is_numeric($company_id)) {
        return false;
    }

    $company_id = intval($company_id);
    
    $result = $db->select('agent', 'uid', ['company' => $company_id]);
    if (is_array($result)) {
        foreach ($result as $uid) {
            $redis->delete('agent.'.$uid);
            unlink('/usr/local/freeswitch/conf/directory/default/'.$uid.'.xml');
        }

        $db->delete('agent', ['company' => $company_id]);

        $file = '/usr/local/freeswitch/conf/agents/agent.'.$company_id.'.xml';
        unlink($file);
    
        foreach ($result as $uid) {
            $cmd = 'bgapi callcenter_config agent del '.$uid;
            $esl->send($cmd);
        }

        return true;
    }
    
    return false;
}

function the_user_remove($db, $redis, $uid) {
    if (!$db || !$redis || !is_string($uid)) {
        return false;
    }

    $db->delete('users', ['uid' => $uid]);
    $redis->delete('user.'.$uid);
    $redis->delete('session.'.$uid);

    return true;
}

function the_sound_remove($db, $redis, $company_id) {
    if (!$db || !$redis || !is_numeric($company_id)) {
        return false;
    }

    $company_id = intval($company_id);
    
    $result = $db->select('sounds', ['id', 'file'], ['company' => $company_id]);
    if (is_array($result)) {
        foreach ($result as $sound) {
            $redis->delete('sound.'.$sound['id']);
            unlink('/var/www/public/sounds/'.$sound['file']);
        }
        
        $db->delete('sounds', ['company' => $company_id]);
        return true;
    }

    return false;
}

function the_product_remove($db, $redis, $product_id) {
    if (!$db || !$redis || !is_numeric($product_id)) {
        return false;
    }

    $product_id = intval($product_id);

    $db->delete('product', ['id' => $product_id]);
    $redis->delete('product.'.$product_id);

    return true;
}

function filter_company_edit($post) {
    if (!is_array($post)) {
        return null;
    }

    $data = null;
    
    if (isset($post['name']) && is_string($post['name'])) {
        $name = str_replace(' ', '', $post['name']);
        $len = mb_strlen($name);
        if ($len > 0) {
            $data['name'] = $name;
        } else {
            return null;
        }
    } else {
        return null;
    }

    if (isset($post['level']) && is_numeric($post['level'])) {
        $level = intval($post['level']);
        $level_list = [1, 2, 3];
        if (in_array($level, $level_list, true)) {
            $data['level'] = $level;
        } else {
            $data['level'] = 1;
        }
    } else {
        $data['level'] = 1;
    }
    
    if (isset($post['concurrent']) && is_numeric($post['concurrent'])) {
        $concurrent = intval($post['concurrent']);
        if ($concurrent > 0) {
            $data['concurrent'] = $concurrent;
        } else {
            return null;
        }
    } else {
        return null;
    }

    if (isset($post['billing']) && is_string($post['billing'])) {
        $billing = str_replace(' ', '', $post['billing']);
        $len = mb_strlen($billing);
        if ($len > 0) {
            $data['billing'] = $billing;
        } else {
            $data['billing'] = 'unknown';
        }
    } else {
        $data['billing'] = 'unknown';
    }

    if (isset($post['sound_check']) && is_string($post['sound_check'])) {
        $sound_check = str_replace(' ', '', $post['sound_check']);
        if ($sound_check === 'on') {
            $data['sound_check'] = 1;
        } else {
            $data['sound_check'] = 0;
        }
    } else {
        $data['sound_check'] = 0;
    }

    return $data;
}

function the_company_edit($db, $redis, $company_id, $data) {
    if (!$db || !$redis || !is_numeric($company_id) || !is_array($data)) {
        return false;
    }

    $company_id = intval($company_id);

    if ($db->count('company', ['id' => $company_id]) > 0) {
        $db->update('company', $data, ['id' => $company_id]);
        if ($data['sound_check'] === 0) {
            $db->update('sounds', ['status' => 1], ['company' => $company_id]);
        }
        $redis->hMSet('company.'.$company_id, $data);
        return true;
    }

    return false;
}

function the_queue_create($company_id) {
    if (!is_numeric($company_id)) {
        return false;
    }

    $company_id = intval($company_id);

    $xml = "<include>\n";
    $xml .= '  <queue name="'.$company_id.'@queue">'."\n";
    $xml .= '    <param name="strategy" value="longest-idle-agent"/>'."\n";
    $xml .= '    <param name="moh-sound" value="$${hold_music}"/>'."\n";
    $xml .= '    <param name="record-template" value="$${recordings_dir}/${strftime(%Y/%m/%d}/${uuid}.wav"/>'."\n";
    $xml .= '    <param name="time-base-score" value="system"/>'."\n";
    $xml .= '    <param name="max-wait-time" value="30"/>'."\n";
    $xml .= '    <param name="max-wait-time-with-no-agent" value="30"/>'."\n";
    $xml .= '    <param name="max-wait-time-with-no-agent-time-reached" value="5"/>'."\n";
    $xml .= '    <param name="tier-rules-apply" value="false"/>'."\n";
    $xml .= '    <param name="tier-rule-wait-second" value="300"/>'."\n";
    $xml .= '    <param name="tier-rule-wait-multiply-level" value="true"/>'."\n";
    $xml .= '    <param name="tier-rule-no-agent-no-wait" value="false"/>'."\n";
    $xml .= '    <param name="discard-abandoned-after" value="60"/>'."\n";
    $xml .= '    <param name="abandoned-resume-allowed" value="false"/>'."\n";
    $xml .= '  </queue>'."\n";
    $xml .= "</include>\n";
    
    $path = '/usr/local/freeswitch/conf/queues/queue.'.$company_id.'.xml';
    $file = fopen($path, "w");
    if ($file) {
        fwrite($file, $xml);
        fclose($file);
        return true;
    }

    return false;
}

function the_agents_create($company_id, $agents) {
    if (!is_numeric($company_id) || !is_array($agents)) {
        return false;
    }

    $company_id = intval($company_id);
    
    $xml = "<include>\n";
    foreach ($agents as $uid) {
        $xml .= '  <agent name="'.$uid.'" type="callback" contact="user/'.$uid.'" status="Logged Out" max-no-answer="24" wrap-up-time="5" reject-delay-time="0" busy-delay-time="5"'." />\n";
    }
    $xml .= "</include>\n";

    $path = '/usr/local/freeswitch/conf/agents/agent.'.$company_id.'.xml';
    $file = fopen($path, "w");
    if ($file) {
        fwrite($file, $xml);
        fclose($file);
        return true;
    }

    return false;
}

function the_tiers_create($company_id, $agents) {
    if (!is_numeric($company_id) || !is_array($agents)) {
        return false;
    }

    $company_id = intval($company_id);
    
    $xml = "<include>\n";
    foreach ($agents as $uid) {
        $xml .= '  <tier agent="'.$uid.'" queue="'.$company_id.'@queue" level="1" position="1"/>'."\n";
    }
    $xml .= "</include>\n";

    $path = '/usr/local/freeswitch/conf/tiers/tiers.'.$company_id.'.xml';
    $file = fopen($path, "w");
    if ($file) {
        fwrite($file, $xml);
        fclose($file);
        return true;
    }

    return false;
}

function callcenter_config_queue_load($esl, $company_id) {
    if (!$esl || !is_numeric($company_id)) {
        return false;
    }

    $company_id = intval($company_id);
    $cmd = 'bgapi callcenter_config queue load '.$company_id.'@queue';
    $esl->send($cmd);
    
    return true;
}

function callcenter_config_agent_reload($esl, $company_id, $agents) {
    if (!$esl || !is_numeric($company_id) || !is_array($agents)) {
        return false;
    }

    $company_id = intval($company_id);
    
    foreach ($agents as $uid) {
        $cmd = 'bgapi callcenter_config agent reload '.$uid;
        $esl->send($cmd);
    }

    return true;    
}

function callcenter_config_tier_reload($esl, $company_id, $agents) {
    if (!$esl || !is_numeric($company_id) || !is_array($agents)) {
        return false;
    }

    $company_id = intval($company_id);
    
    foreach ($agents as $uid) {
        $cmd = 'bgapi callcenter_config tier reload '.$company_id.'@queue '.$uid;
        $esl->send($cmd);
    }

    return true;    
}

function reloadxml($esl) {
    if (!$esl) {
        return false;
    }

    $esl->send('bgapi reloadxml');

    return true;
}

function the_queue_remove($esl, $company_id) {
    if (!$esl || !is_numeric($company_id)) {
        return false;
    }

    $company_id = intval($company_id);

    $file = '/usr/local/freeswitch/conf/queues/queue.'.$company_id.'.xml';
    unlink($file);
    
    $cmd = 'bgapi callcenter_config queue unload '.$company_id.'@queue';
    $esl->send($cmd);

    return true;
}

function the_tiers_remove($esl, $company_id, $agents) {
    if (!$esl || !is_numeric($company_id) || !is_array($agents)) {
        return false;
    }

    $company_id = intval($company_id);
    $file = '/usr/local/freeswitch/conf/tiers/tiers.'.$company_id.'.xml';
    unlink($file);
    
    foreach ($agents as $uid) {
        $cmd = 'bgapi callcenter_config tier del '.$company_id.'@queue '.$uid;
        $esl->send($cmd);
    }

    return true;
}
