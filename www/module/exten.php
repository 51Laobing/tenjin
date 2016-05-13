<?php

function get_exten_reg_info($db, $exten) {
    if (!$db || !is_numeric($exten)) {
        return null;
    }

    $result = $db->get('sip_registrations', ['sip_user', 'ping_status', 'network_ip', 'network_port', 'user_agent'], ['sip_user' => $exten]);
    if ($result) {
        return $result;
    }

    return ['sip_user' => $exten, 'ping_status' => 'unregister', 'network_ip' => '0.0.0.0', 'network_port' => 'null', 'user_agent' => 'Unknown Device Name'];
}

function exten_unregister($esl, $user) {
    if (!$esl || !is_string($user)) {
        return false;
    }

    $esl->send('bgapi sofia profile internal flush_inbound_reg '.$user);
    return true;
}

