<?php

function get_user($db, $uid) {
    if (!$db || !is_string($uid)) {
        return null;
    }

    $result = $db->get('users', '*', ['uid' => $uid]);
    if ($result) {
        return $result;
    }

    return null;
}

function get_all_user($db) {
    if (!$db) {
        return null;
    }

    $result = $db->select('users', '*', ['ORDER' => "company ASC"]);
    if ($result) {
        return $result;
    }

    return null;
}

function get_company_all_user($db, $company_id) {
    if (!$db || !is_numeric($company_id)) {
        return null;
    }

    $company_id = intval($company_id);
    $result = $db->select('users', '*', ['company' => $company_id]);
    if ($result) {
        return $result;
    }

    return null;
}

function get_company_name($db, $company_id) {
    if (!$db || !is_numeric($company_id)) {
        return 'Unknown';
    }

    $company_id = intval($company_id);

    $result = $db->get('company', 'name', ['id' => $company_id]);
    if ($result) {
        return $result;
    }

    return 'Unknown';
}

function filter_user_create($post) {
    if (!is_array($post)) {
        return null;
    }

    $data = null;

    if (isset($post['uid']) && is_string($post['uid'])) {
        $uid = str_replace(' ', '', $post['uid']);
        $len = mb_strlen($uid);
        if ($len > 0 && $len < 24) {
            $data['uid'] = $uid;
        } else {
            return null;
        }
    } else {
        return null;
    }

    if (isset($post['password']) && is_string($post['password'])) {
        $password = str_replace(' ', '', $post['password']);
        $len = mb_strlen($password);
        if ($len > 7) {
            $data['password'] = sha1(md5($password));
        } else {
            return null;
        }
    } else {
        return null;
    }

    if (isset($post['company']) && is_numeric($post['company'])) {
        $company_id = intval($post['company']);
        if ($company_id > 0) {
            $data['company'] = $company_id;
        } else {
            return null;
        }
    } else {
        return null;
    }

    return $data;
}

function is_user_exist($db, $uid) {
    if (!$db) {
        return false;
    }

    if ($db->count('users', ['uid' => $uid]) > 0) {
        return true;
    }

    return false;
}

function the_user_create($db, $redis, $uid, $password, $company_id) {
    if (!$db || !$redis || !is_numeric($company_id)) {
        return false;
    }

    $company_id = intval($company_id);
    $create_time = date('Y-m-d H:i:s');

    if (is_user_exist($db, $uid)) {
        return false;
    }

    $user = ['uid' => $uid, 'name' => $uid, 'password' => $password, 'type' => 1,
             'company' => $company_id, 'create_time' => $create_time,
             'last_login' => $create_time, 'last_ipaddr' => '0.0.0.0'];
    
    $result = $db->insert('users', $user);


    if ($db->count('users', ['uid' => $uid]) > 0) {
        $redis->hMSet('user.'.$uid, $user);
        return true;
    }


    return false;
}

function fetch_all_company($db) {
    if (!$db) {
        return null;
    }

    $result = $db->select('company', '*', ["ORDER" => "id ASC"]);
    
    if ($result) {
        return $result;
    }

    return null;
}

function filter_user_edit($post) {
    if (!is_array($post)) {
        return null;
    }

    $data = null;

    if (isset($post['uid']) && is_string($post['uid'])) {
        $uid = str_replace(' ', '', $post['uid']);
        $len = mb_strlen($uid);
        if ($len > 0 && $len < 24) {
            $data['uid'] = $uid;
        } else {
            return null;
        }
    } else {
        return null;
    }

    if (isset($post['password']) && is_string($post['password'])) {
        $password = str_replace(' ', '', $post['password']);
        $len = mb_strlen($password);
        if ($len > 7) {
            $data['password'] = sha1(md5($password));
        } else {
            return null;
        }
    } else {
        return null;
    }

    return $data;
}

function the_user_edit($db, $redis, $uid, $password) {
    if (!$db || !$redis) {
        return false;
    }

    $db->update('users', ['password' => $password], ['uid' => $uid]);
    if ($redis->exists('user.'.$uid)) {
        $redis->hMSet('user.'.$uid, ['password' => $password]);
    }

    return true;
}