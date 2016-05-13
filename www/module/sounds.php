<?php

function get_all_sound($db, $company_id) {
    if (!$db || !is_numeric($company_id)) {
        return null;
    }

    $company_id = intval($company_id);

    $result = $db->select('sounds', '*', ['company' => $company_id]);
    if ($result && is_array($result)) {
        return $result;
    }

    return null;
}

function get_all_pass_sound($db, $company) {
    if (!$db || !is_numeric($company)) {
        return null;
    }

    $company = intval($company);

    $result = $db->select('sounds', '*', ['AND' => ['company' => $company, 'status' => 1]]);
    if ($result && is_array($result)) {
        return $result;
    }

    return null;
}

function fetch_all_sound($db) {
    if (!$db) {
        return null;
    }

    $result = $db->select('sounds', '*');
    if ($result) {
        return $result;
    }

    return null;
}

function the_pass_sound($db, $redis, $sound_id) {
    if (!$db || !$redis || !is_numeric($sound_id)) {
        return false;
    }

    $db->update('sounds', ['status' => 1], ['id' => $sound_id]);
    if ($redis->exists('sound.'.$sound_id)) {
        $redis->hSet('sound.'.$sound_id, 'status', 1);
        return true;
    }

    return false;
}

function is_company_sound($db, $company, $sound) {
    if (!$db || !is_numeric($company) || !is_numeric($sound)) {
        return false;
    }

    $company = intval($company);
    $sound = intval($sound);

    $result = $db->get('sounds', 'company', ['id' => $sound]);
    if ($result === $company) {
        return true;
    }

    return false;
}

function sound_create($db, $data) {
    if (!$db || !is_array($data)) {
        return false;
    }

    $result = $db->insert('sounds', $data);
    if ($result) {
        return true;
    }

    return false;
}

function sound_remove($db, $sound) {
    if (!$db || !is_numeric($sound)) {
        return false;
    }

    $sound = intval($sound);
    if ($sound < 1) {
        return false;
    }

    $file = $db->get('sounds', 'file', ['id' => $sound]);
    
    $result = $db->delete('sounds', ['id' => $sound]);
                          
    if ($result) {
        unlink('/var/www/public/sounds/'.$file);
        return true;
    }

    return false;
}

function filter_sound_upload($data, $files) {
    if (!is_array($data) || !is_array($files)) {
        return null;
    }

    $r = null;

    // check name form
    if (!isset($data['name']) || !is_string($data['name'])) {
        return null;
    }

    $len = mb_strlen($data['name']);
    if ($len > 0 && $len < 32) {
        $r['name'] = str_replace(' ', '', $data['name']);
    } else {
        return null;
    }

    // check remark form
    if (isset($data['remark']) && is_string($data['remark'])) {
        $len = mb_strlen($data['remark']);
        if ($len > 0 && $len < 32) {
            $r['remark'] = str_replace(' ', '', $data['remark']);
        } else {
            $r['remark'] = 'null';
        }
    } else {
        $r['remark'] = 'null';
    }


    // check upload file
    if (!isset($files['file']) && !is_array($files['file'])) {
        return null;
    }

    if ($files['file']['error'] !== 0) {
        return null;
    }
    
    $file = $files['file']['tmp_name'];
    $finfo = finfo_open (FILEINFO_MIME);
    if (!$finfo) {
        return null;
    }

    if (finfo_file($finfo, $file) !== 'audio/x-wav; charset=binary') {
        return null;
    }

    finfo_close($finfo);
    
    $size = $files['file']['size'];
    if ($size > 0 && $size < 2097152) {
        $r['tmp_file'] = $files['file']['tmp_name'];
    } else {
        return null;
    }
    
    return $r;
}

