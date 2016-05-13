<?php

function fetch_queue_agents($db, $company_id) {
    if (!$db || !is_numeric($company_id)) {
        return null;
    }

    $company_id = intval($company_id);
    $queue = $company_id.'@queue';
    $result = $db->query("select name, status, state, last_bridge_start, no_answer_count, calls_answered, talk_time from agents where name in(select agent from tiers where queue = '".$queue."') and status in('Available', 'Available (On Demand)', 'On Break') ORDER BY name ASC")->fetchAll();

    if ($result) {
        return $result;
    }

    return null;
}

function fetch_agent($redis, $agent) {
    if (!$redis || !is_numeric($agent)) {
        return null;
    }

    $reply = $redis->hMGet('agent.'.$agent, ['name', 'icon']);
    if (is_array($reply)) {
        foreach ($reply as $rep) {
            if ($rep === false) {
                return null;
            }
        }
        return $reply;
    } else {
        return null;
    }
}

function get_company_current_task($redis, $company_id) {
    $task = ['name' => 'No Task', 'type' => 0, 'completion_rate' => 0];

    if (!$redis || !is_numeric($company_id)) {
        return $task;
    }

    $company_id = intval($company_id);
    
    $reply = $redis->hGet('company.'.$company_id, 'task');
    if ($reply != false) {
        $task_id = intval($reply);
        if ($task_id < 1) {
            return $task;
        }
        
        $reply = $redis->hMGet('task.'.$task_id, ['name', 'type', 'total']);
        if ($reply['name'] != false) {
            $task['name'] = $reply['name'];
        }

        if ($reply['type'] != false) {
            $task['type'] = intval($reply['type']);
        }

        // get task total
        $total = 0;
        if ($reply['total'] != false) {
            $total = $reply['total'];
        }

        // get task remainder
        $remainder = 0;
        $reply = $redis->lLen('data.'.$task_id);
        $remainder = intval($reply);

        if ($total > 0) {
            $task['completion_rate'] = intval((($total - $remainder) / $total) * 100.0);
        }
    }

    return $task;
}

function is_agent_talking($db, $agent_id) {
    if (!$db || !is_numeric($agent_id)) {
        return false;
    }

    $agent_id = strval($agent_id);
    
    $result = $db->get('channels', 'uuid', ['OR' => ['cid_num' => $agent_id, 'callee_num' => $agent_id]]);
    if ($result) {
        return true;
    }
    
    return false;
}

function get_call_concurrent($db, $company_id) {
    if (!$db || !is_numeric($company_id)) {
        return 0;
    }

    $company_id = strval($company_id);

    $result = $db->count('channels', ['initial_cid_num' => $company_id]);

    return $result;
}

function get_playback($db, $company_id) {
    if (!$db || !is_numeric($company_id)) {
    return 0;
    }

    $company_id = strval($company_id);

    $result = $db->count('channels', ['AND' => ['application' => 'playback', 'initial_cid_num' => $company_id]]);

    return $result;
}
