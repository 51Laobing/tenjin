<?php

function get_taskpool($redis, $company) {
    if (!$redis || !is_numeric($company)) {
        return null;
    }

    $company = intval($company);

    $reply = $redis->sMembers('taskpool.'.$company);
    if (is_array($reply) && count($reply) > 0) {
        return $reply;
    }

    return null;
}

function is_company_task($redis, $company_id, $task_id) {
    if (!$redis || !is_numeric($company_id) || !is_numeric($task_id)) {
        return false;
    }

    $company_id = intval($company_id);
    $task_id = intval($task_id);

    $reply = $redis->sIsMember('taskpool.'.$company_id, "$task_id");
    if ($reply === true) {
        return true;
    }

    return false;
}

function get_task($redis, $task) {
    if (!$redis || !is_numeric($task)) {
        return null;
    }

    $task = intval($task);

    $type = $redis->hGet('task.'.$task, 'type');
    if (!$type) {
        return null;
    }

    $type = intval($type);
    $reply = null;
    
    switch ($type) {
    case 1:
    case 2:
        $reply = $redis->hMGet('task.'.$task, ['name', 'type', 'business', 'dial', 'play', 'sound', 'total', 'answer', 'complete']);
        $reply['id'] = $task;
        $reply['type'] = $type;
        $reply['name'] = $reply['name'] ? $reply['name'] : 'Unknown';

        if ($type === 1) {
            $reply['dial'] = $reply['dial'] ? $reply['dial'] : 8;
        } else {
            $reply['dial'] = $reply['dial'] ? $reply['dial'] : 80;
        }

        $reply['business'] = $reply['business'] ? intval($reply['business']) : 1;
        $reply['play'] = $reply['play'] ? intval($reply['play']) : 0;
        $reply['sound'] = $reply['sound'] ? intval($reply['sound']) : 0;
        $reply['total'] = $reply['total'] ? intval($reply['total']) : 0;
        $reply['remainder'] = get_task_remainder($redis, $reply['id']);
        $reply['answer'] = $reply['answer'] ? intval($reply['answer']) : 0;
        $reply['complete'] = $reply['complete'] ? intval($reply['complete']) : 0;
        break;
    case 3:
        $reply = $redis->hMGet('task.'.$task, ['name', 'type', 'business', 'total', 'answer']);
        $reply['id'] = $task;
        $reply['type'] = $type;
        $reply['name'] = $reply['name'] ? $reply['name'] : 'Unknown';
        $reply['business'] = $reply['business'] ? intval($reply['business']) : 1;
        $reply['total'] = $reply['total'] ? intval($reply['total']) : 0;
        $reply['remainder'] = get_task_remainder($redis, $reply['id']);
        $reply['answer'] = $reply['answer'] ? intval($reply['answer']) : 0;
        break;
    case 4:
        $reply = $redis->hMGet('task.'.$task, ['name', 'type', 'presskey', 'sound', 'total', 'answer']);
        $reply['id'] = $task;
        $reply['type'] = $type;
        $reply['name'] = $reply['name'] ? $reply['name'] : 'Unknown';
        $reply['sound'] = $reply['sound'] ? intval($reply['sound']) : 0;
        $reply['total'] = $reply['total'] ? intval($reply['total']) : 0;
        $reply['presskey'] = $reply['presskey'] ? intval($reply['presskey']) : 0;
        $reply['remainder'] = get_task_remainder($redis, $reply['id']);
        $reply['answer'] = $reply['answer'] ? intval($reply['answer']) : 0;
        break;
    }

    return $reply;
}

function is_task_run($redis, $company, $task) {
    if (!$redis || !is_numeric($company) || !is_numeric($task)) {
        return false;
    }

    $company = intval($company);
    $task = intval($task);
    
    $reply = $redis->hGet('company.'.$company, 'task');

    if (intval($reply) === $task) {
        return true;
    }

    return false;
}

function task_start($redis, $company, $task) {
    if (!$redis || !is_numeric($company) || !is_numeric($task)) {
        return false;
    }

    $company = intval($company);
    $task = intval($task);

    $reply = $redis->hSet('company.'.$company, 'task', $task);
    if ($reply) {
        return true;
    }

    return false;
}

function task_stop($redis, $company, $task) {
    if (!$redis || !is_numeric($company) || !is_numeric($task)) {
        return false;
    }

    $company = intval($company);
    $task = intval($company);

    $reply = $redis->hSet('company.'.$company, 'task', 0);
    if ($reply) {
        return true;
    }

    return false;
}

function task_remove($redis, $company, $task) {
    if (!$redis || !is_numeric($company) || !is_numeric($task)) {
        return false;
    }

    $company = intval($company);
    $task = intval($task);

    if (is_task_run($redis, $company, $task)) {
        $redis->hSet('company.'.$company, 'task', 0);
    }
    
    $redis->sRem('taskpool.'.$company, "$task");
    $redis->delete('task.'.$task);
    $redis->delete('data.'.$task);

    return false;
}

function get_task_remainder($redis, $task) {
    if (!$redis || !is_numeric($task)) {
        return 0;
    }

    $task = intval($task);
    $reply = $redis->lLen('data.'.$task);

    return intval($reply);
}

function task_edit($redis, $task_id, $data) {
    if (!$redis || !is_numeric($task_id) || !is_array($data)) {
        return false;
    }

    $task_id = intval($task_id);

    $reply = $redis->hMSet('task.'.$task_id, $data);
    if ($reply) {
        return true;
    }

    return false;
}

function filter_task_edit($redis, $task_id, $company_id, $data) {
    if (!$redis || !is_numeric($task_id) || !is_numeric($company_id) || !is_array($data)) {
        return null;
    }

    $task_id = intval($task_id);
    
    $reply = $redis->hGet('task.'.$task_id, 'type');
    if (!is_numeric($reply)) {
        return null;
    }

    $type = intval($reply);
    $temp = null;
    
    switch ($type) {
    case 1:
        $temp = filter_type_auto($redis, $company_id, $data);
        break;
    case 2:
        $temp = filter_type_fixed($redis, $company_id, $data);
        break;
    case 3:
        $temp = filter_type_manual($redis, $company_id, $data);
        break;
    case 4:
        $temp = filter_type_sound($redis, $company_id, $data);
        break;
    }

    return $temp;
}

function filter_type_auto($redis, $company_id, $data) {
    if (!$redis || !is_numeric($company_id) || !is_array($data)) {
        return null;
    }
    
    $company_id = intval($company_id);
    $temp = null;

    // check name
    if (isset($data['name']) && is_string($data['name'])) {
        $name = str_replace(' ', '', $data['name']);
        $len = mb_strlen($name, 'utf8');
        if ($len > 0) {
            $temp['name'] = $name;
        } else {
            return null;
        }
    } else {
        return null;
    }
    
    // check business type
    if (isset($data['business']) && is_numeric($data['business'])) {
        $business = intval($data['business']);
        if (is_business($business)) {
            $temp['business'] = $business;
        } else {
            $temp['business'] = 1;
        }
    } else {
        $temp['business'] = 1;
    }
    
    // check dial
    if (isset($data['dial']) && is_numeric($data['dial'])) {
        $dial = intval($data['dial']);
        if ($dial > 0 && $dial <= 24) {
            $temp['dial'] = $dial;
        } else {
            return null;
        }
    } else {
        return null;
    }
    
    // check sound
    if (isset($data['sound']) && is_numeric($data['sound'])) {
        $sound_id = intval($data['sound']);
        // check sound si company
        if (is_pass_sound($redis, $company_id, $sound_id)) {
            $temp['sound'] = $sound_id;
        } else {
            return null;
        }
    } else {
        return null;
    }
    
    // check play
    if (isset($data['play']) && is_string($data['play'])) {
        $play = str_replace(' ', '', $data['play']);
        if ($play === 'on') {
            $temp['play'] = '1';
        } else {
            $temp['play'] = '0';
        }
    } else {
        $temp['play'] = '0';
    }
    
    return $temp;
}

function filter_type_fixed($redis, $company_id, $data) {
    if (!$redis || !is_numeric($company_id) || !is_array($data)) {
        return null;
    }

    $company_id = intval($company_id);
    $temp = null;

    // check name
    if (isset($data['name']) && is_string($data['name'])) {
        $name = str_replace(' ', '', $data['name']);
        $len = mb_strlen($name, 'utf8');
        if ($len > 0) {
            $temp['name'] = $name;
        } else {
            return null;
        }
    } else {
        return null;
    }

    // check business type
    if (isset($data['business']) && is_numeric($data['business'])) {
        $business = intval($data['business']);
        if (is_business($business)) {
            $temp['business'] = $business;
        } else {
            $temp['business'] = 1;
        }
    } else {
        $temp['business'] = 1;
    }
    
    // check dial
    if (isset($data['dial']) && is_numeric($data['dial'])) {
        $dial = intval($data['dial']);
        if ($dial > 0 && $dial <= get_company_concurrent($redis, $company_id)) {
            $temp['dial'] = $dial;
        } else {
            return null;
        }
    } else {
        return null;
    }
    
    // check sound
    if (isset($data['sound']) && is_numeric($data['sound'])) {
        $sound_id = intval($data['sound']);
        // check sound si company
        if (is_pass_sound($redis, $company_id, $sound_id)) {
            $temp['sound'] = $sound_id;
        } else {
            return null;
        }
    } else {
        return null;
    }

    // check play
    if (isset($data['play']) && is_string($data['play'])) {
        $play = str_replace(' ', '', $data['play']);
        if ($play === 'on') {
            $temp['play'] = '1';
        } else {
            $temp['play'] = '0';
        }
    } else {
        $temp['play'] = '0';
    }

    return $temp;
}

function filter_type_manual($redis, $company_id, $data) {
    if (!$redis || !is_numeric($company_id) || !is_array($data)) {
        return null;
    }

    $company_id = intval($company_id);
    $temp = null;

    // check name
    if (isset($data['name']) && is_string($data['name'])) {
        $name = str_replace(' ', '', $data['name']);
        $len = mb_strlen($name, 'utf8');
        if ($len > 0) {
            $temp['name'] = $name;
        } else {
            return null;
        }
    } else {
        return null;
    }

    // check business type
    if (isset($data['business3']) && is_numeric($data['business3'])) {
        $business = intval($data['business3']);
        if (is_business($business)) {
            $temp['business'] = $business;
        } else {
            $temp['business'] = 1;
        }
    } else {
        $temp['business'] = 1;
    }
    
    return $temp;
}

function filter_type_sound($redis, $company_id, $data) {
    if (!$redis || !is_numeric($company_id) || !is_array($data)) {
        return null;
    }

    $company_id = intval($company_id);
    $temp = null;

    // check name
    if (isset($data['name']) && is_string($data['name'])) {
        $name = str_replace(' ', '', $data['name']);
        $len = mb_strlen($name, 'utf8');
        if ($len > 0 && $len <= 12) {
            $temp['name'] = $name;
        } else {
            return null;
        }
    } else {
        return null;
    }
    
    // check presskey
    if (isset($data['presskey']) && is_string($data['presskey'])) {
        $presskey = str_replace(' ', '', $data['presskey']);
        if ($presskey === 'on') {
            $temp['presskey'] = '1';
        } else {
            $temp['presskey'] = '0';
        }
    } else {
        $temp['presskey'] = '0';
    }
    
    // check sound
    if (isset($data['sound']) && is_numeric($data['sound'])) {
        $sound_id = intval($data['sound']);
        // check sound si company
        if (is_pass_sound($redis, $company_id, $sound_id)) {
            $temp['sound'] = $sound_id;
        } else {
            return null;
        }
    } else {
        return null;
    }
    
    return $temp;
}

function is_pass_sound($redis, $company_id, $sound_id) {
    if (!$redis || !is_numeric($company_id) || !is_numeric($sound_id)) {
        return false;
    }

    $company_id = intval($company_id);
    $sound_id = intval($sound_id);

    /*
      $result = $db->count('sounds', ['AND' => ['id' => $sound_id, 'company' => $company_id, 'status' => 1]]);
      if ($result === 1) {
      return true;
      }
    */
    
    $reply = $redis->hMGet('sound.'.$sound_id, ['company', 'status']);
    if ($reply['company'] === strval($company_id)) {
        if ($reply['status'] === '1') {
            return true;
        }
    }
    return false;
}

function get_company_concurrent($redis, $company_id) {
    if (!$redis || !is_numeric($company_id)) {
        return 0;
    }

    $company_id = intval($company_id);

    $reply = $redis->hGet('company.'.$company_id, 'concurrent');
    if ($reply) {
        return intval($reply);
    }

    return 0;
}

function filter_task_upload($redis, $company_id, $post) {
    if (!$redis || !is_numeric($company_id) || !is_array($post)) {
        return null;
    }
    
    $type = null;
    $data = null;
    
    if (isset($post['type']) && is_numeric($post['type'])) {
        // acceptable type list
        $types = [1, 2, 3, 4];
        
        // check task type
        if (in_array(intval($post['type']), $types, true)) {
            $type = intval($post['type']);
        } else {
            return null;
        }
    } else {
        return null;
    }

    switch ($type) {
    case 1:
        $data = filter_type_auto_upload($redis, $company_id, $post);
        break;
    case 2:
        $data = filter_type_fixed_upload($redis, $company_id, $post);
        break;
    case 3:
        $data = filter_type_manual_upload($redis, $company_id, $post);
        break;
    case 4:
        $data = filter_type_sound_upload($redis, $company_id, $post);
        break;
    }

    return $data;
}

function filter_type_auto_upload($redis, $company_id, $post) {
    if (!$redis || !is_numeric($company_id) || !is_array($post)) {
        return null;
    }
    
    $company_id = intval($company_id);
    $data = null;

    // check name
    if (isset($post['name']) && is_string($post['name'])) {
        $name = str_replace(' ', '', $post['name']);
        $len = mb_strlen($name, 'utf8');
        if ($len > 0 && $len <= 12) {
            $data['name'] = $name;
        } else {
            $data['name'] = 'Unknown';
        }
    } else {
        $data['name'] = 'Unknown';
    }
    
    // check business type
    if (isset($post['business1']) && is_numeric($post['business1'])) {
        $business = intval($post['business1']);
        if (is_business($business)) {
            $data['business'] = $business;
        } else {
            $data['business'] = 1;
        }
    } else {
        $data['business'] = 1;
    }
    
    // check dial
    if (isset($post['dial1']) && is_numeric($post['dial1'])) {
        $dial = intval($post['dial1']);
        if ($dial > 0 && $dial <= 24) {
            $data['dial'] = $dial;
        } else {
            $data['dial'] = 8;
        }
    } else {
        $data['dial'] = 8;
    }
    
    // check sound
    if (isset($post['sound1']) && is_numeric($post['sound1'])) {
        $sound_id = intval($post['sound1']);
        // check sound si company
        if (is_pass_sound($redis, $company_id, $sound_id)) {
            $data['sound'] = $sound_id;
        } else {
            return null;
        }
    } else {
        return null;
    }
    
    // check play
    if (isset($post['play1']) && is_string($post['play1'])) {
        $play = str_replace(' ', '', $post['play1']);
        if ($play === 'on') {
            $data['play'] = '1';
        } else {
            $data['play'] = '0';
        }
    } else {
        $data['play'] = '0';
    }

    $data['type'] = 1;
    
    return $data;
}

function filter_type_fixed_upload($redis, $company_id, $post) {
    if (!$redis || !is_numeric($company_id) || !is_array($post)) {
        return null;
    }

    $company_id = intval($company_id);
    $data = null;

    // check name
    if (isset($post['name']) && is_string($post['name'])) {
        $name = str_replace(' ', '', $post['name']);
        $len = mb_strlen($name, 'utf8');
        if ($len > 0 && $len <= 12) {
            $data['name'] = $name;
        } else {
            $data['name'] = 'Unknown';
        }
    } else {
        $data['name'] = 'Unknown';
    }

    // check business type
    if (isset($post['business2']) && is_numeric($post['business2'])) {
        $business = intval($post['business2']);
        if (is_business($business)) {
            $data['business'] = $business;
        } else {
            $data['business'] = 1;
        }
    } else {
        $data['business'] = 1;
    }
    
    // check dial
    if (isset($post['dial2']) && is_numeric($post['dial2'])) {
        $dial = intval($post['dial2']);
        if ($dial > 0 && $dial <= get_company_concurrent($redis, $company_id)) {
            $data['dial'] = $dial;
        } else {
            $data['dial'] = 50;
        }
    } else {
        $data['dial'] = 50;
    }
    
    // check sound
    if (isset($post['sound2']) && is_numeric($post['sound2'])) {
        $sound_id = intval($post['sound2']);
        // check sound si company
        if (is_pass_sound($redis, $company_id, $sound_id)) {
            $data['sound'] = $sound_id;
        } else {
            return null;
        }
    } else {
        return null;
    }

    // check play
    if (isset($post['play2']) && is_string($post['play2'])) {
        $play = str_replace(' ', '', $post['play2']);
        if ($play === 'on') {
            $data['play'] = '1';
        } else {
            $data['play'] = '0';
        }
    } else {
        $data['play'] = '0';
    }

    $data['type'] = 2;
    
    return $data;
}

function filter_type_manual_upload($redis, $company_id, $post) {
    if (!$redis || !is_numeric($company_id) || !is_array($post)) {
        return null;
    }

    $company_id = intval($company_id);
    $data = null;

    // check name
    if (isset($post['name']) && is_string($post['name'])) {
        $name = str_replace(' ', '', $post['name']);
        $len = mb_strlen($name, 'utf8');
        if ($len > 0 && $len <= 12) {
            $data['name'] = $name;
        } else {
            $data['name'] = 'Unknown';
        }
    } else {
        $data['name'] = 'Unknown';
    }

    // check business type
    if (isset($post['business3']) && is_numeric($post['business3'])) {
        $business = intval($post['business3']);
        if (is_business($business)) {
            $data['business'] = $business;
        } else {
            $data['business'] = 1;
        }
    } else {
        $data['business'] = 1;
    }

    $data['type'] = 3;
    
    return $data;
}

function filter_type_sound_upload($redis, $company_id, $post) {
    if (!$redis || !is_numeric($company_id) || !is_array($post)) {
        return null;
    }

    $company_id = intval($company_id);
    $data = null;

    // check name
    if (isset($post['name']) && is_string($post['name'])) {
        $name = str_replace(' ', '', $post['name']);
        $len = mb_strlen($name, 'utf8');
        if ($len > 0 && $len <= 12) {
            $data['name'] = $name;
        } else {
            $data['name'] = 'Unknown';
        }
    } else {
        $data['name'] = 'Unknown';
    }
    
    // check presskey
    if (isset($post['presskey']) && is_string($post['presskey'])) {
        $presskey = str_replace(' ', '', $post['presskey']);
        if ($presskey === 'on') {
            $data['presskey'] = '1';
        } else {
            $data['presskey'] = '0';
        }
    } else {
        $data['presskey'] = '0';
    }
    
    // check sound
    if (isset($post['sound4']) && is_numeric($post['sound4'])) {
        $sound_id = intval($post['sound4']);
        // check sound si company
        if (is_pass_sound($redis, $company_id, $sound_id)) {
            $data['sound'] = $sound_id;
        } else {
            return null;
        }
    } else {
        return null;
    }

    $data['type'] = 4;
    
    return $data;
}

function filter_task_upload_file($files) {
    if (!is_array($files)) {
        return null;
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

    if (finfo_file($finfo, $file) !== 'text/plain; charset=us-ascii') {
        return null;
    }

    finfo_close($finfo);
    
    $size = $files['file']['size'];
    if ($size > 0 && $size < 104857700) {
        $tmp_file = str_replace('/tmp', '/dev/shm', $files['file']['tmp_name']);
        move_uploaded_file($files['file']['tmp_name'], $tmp_file);
        $files['file']['tmp_name'] = $tmp_file;
        return $files;
    } else {
        return null;
    }
}

function task_create($redis, $company_id, $data, $files) {
    if (!$redis || !is_numeric($company_id) || !is_array($data) || !is_array($files)) {
        return false;
    }

    $type = $data['type'];
    $ok = false;
    
    switch ($type) {
    case 1:
        $ok = task_create_auto($redis, $company_id, $data, $files);
        break;
    case 2:
        $ok = task_create_fixed($redis, $company_id, $data, $files);
        break;
    case 3:
        $ok = task_create_manual($redis, $company_id, $data, $files);
        break;
    case 4:
        $ok = task_create_sound($redis, $company_id, $data, $files);
        break;
    }

    return $ok;
}

function task_create_auto($redis, $company_id, $data, $files) {
    if (!$redis || !is_numeric($company_id) || !is_array($data) || !is_array($files)) {
        return false;
    }

    $task_id = the_generate_taskid($redis);
    if ($task_id > 0) {
        $data = array_merge($data, ['total' => 0, 'answer' => 0, 'complete' => 0, 'create_time' => time()]);
        $reply = $redis->hMSet('task.'.$task_id, $data);
        if ($reply) {
            // add task to company task pool
            $reply = the_taskpool_add($redis, $company_id, $task_id);
            if ($reply) {
                // task upload file processing
                task_file_processing($redis, $task_id, $files);
                return true;
            }
        }
    }

    return false;
}

function task_create_fixed($redis, $company_id, $data, $files) {
    if (!$redis || !is_numeric($company_id) || !is_array($data) || !is_array($files)) {
        return false;
    }

    $task_id = the_generate_taskid($redis);
    if ($task_id > 0) {
        $data = array_merge($data, ['total' => 0, 'answer' => 0, 'complete' => 0, 'create_time' => time()]);
        $reply = $redis->hMSet('task.'.$task_id, $data);
        if ($reply) {
            // add task to company task pool
            $reply = the_taskpool_add($redis, $company_id, $task_id);
            if ($reply) {
                // task upload file processing
                task_file_processing($redis, $task_id, $files);
                return true;
            }
        }
    }

    return false;
}

function task_create_manual($redis, $company_id, $data, $files) {
    if (!$redis || !is_numeric($company_id) || !is_array($data) || !is_array($files)) {
        return false;
    }

    $task_id = the_generate_taskid($redis);
    if ($task_id > 0) {
        $data = array_merge($data, ['total' => 0, 'answer' => 0, 'create_time' => time()]);
        $reply = $redis->hMSet('task.'.$task_id, $data);
        if ($reply) {
            // add task to company task pool
            $reply = the_taskpool_add($redis, $company_id, $task_id);
            if ($reply) {
                // task upload file processing
                task_file_processing($redis, $task_id, $files);
                return true;
            }
        }
    }

    return false;
}

function task_create_sound($redis, $company_id, $data, $files) {
    if (!$redis || !is_numeric($company_id) || !is_array($data) || !is_array($files)) {
        return false;
    }

    $task_id = the_generate_taskid($redis);
    if ($task_id > 0) {
        $data = array_merge($data, ['total' => 0, 'answer' => 0, 'create_time' => time()]);
        $reply = $redis->hMSet('task.'.$task_id, $data);
        if ($reply) {
            // add task to company task pool
            $reply = the_taskpool_add($redis, $company_id, $task_id);
            if ($reply) {
                // task upload file processing
                task_file_processing($redis, $task_id, $files);
                return true;
            }
        }
    }

    return false;
}

function the_generate_taskid($redis) {
    if (!$redis) {
        return -1;
    }

    $reply = $redis->multi()->get('counter')->incr('counter')->exec();
    $task_id = intval($reply[0]);
    if ($task_id > 0) {
        return $task_id;
    }
    
    return -1;
}

function the_taskpool_add($redis, $company_id, $task_id) {
    if (!$redis || !is_numeric($company_id) || !is_numeric($task_id)) {
        return false;
    }

    $company_id = intval($company_id);
    $task_id = intval($task_id);

    $reply = $redis->sAdd('taskpool.'.$company_id, $task_id);
    if ($reply) {
        return true;
    }

    return false;
}

function task_file_processing($redis, $task_id, $files) {
    if (!$redis || !is_numeric($task_id) || !is_array($files)) {
        return false;
    }

    $task_id = intval($task_id);
    
    if (is_task_exist($redis, $task_id)) {
        task_file_upload($files['file']['tmp_name'], $task_id);
        return true;
    }

    return false;
}

function is_task_exist($redis, $task_id) {
    if (!$redis || !is_numeric($task_id)) {
        return false;
    }

    $task_id = intval($task_id);
    
    $reply = $redis->exists('task.'.$task_id);
    if ($reply) {
        return true;
    }

    return false;
}

function task_file_upload($file_name, $task_id) {
    if (!is_string($file_name) || !is_numeric($task_id)) {
        return false;
    }

    $task_id = intval($task_id);

    system('/var/www/upd '.$task_id.' '.$file_name);
    return true;
}

function get_taskpool_total($redis, $company_id) {
    if (!$redis || !is_numeric($company_id)) {
        return 9999;
    }

    $company_id = intval($company_id);
    $reply = $redis->sCard('taskpool.'.$company_id);

    return intval($reply);
}

function is_business($business) {
    if (!is_numeric($business)) {
        return false;
    }

    $business = intval($business);
    
    $business_list = [1, 2, 3, 4];
    return in_array($business, $business_list, true);
}

