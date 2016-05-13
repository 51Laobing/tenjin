<?php

function filter_cdr_query($data) {
    if (!is_array($data)) {
        return null;
    }

    $r = null;
    
    if (isset($data['start']) && is_string($data['start'])) {
        $start = $data['start'];
        if (is_date($start)) {
            $r['start'] = $start;
        } else {
            $r['start'] = date('Y-m-d H:i:s');
        }
    } else {
        $r['start'] = date('Y-m-d H:i:s');
    }

    if (isset($data['end']) && is_string($data['end'])) {
        $end = $data['end'];
        if (is_date($end)) {
            $r['end'] = $end;
        } else {
            $r['end'] = date('Y-m-d H:i:s');
        }
    } else {
        $r['end'] = date('Y-m-d H:i:s');
    }

    if (isset($data['duration']) && is_numeric($data['duration'])) {
        $duration = intval($data['duration']);
        if ($duration === 0 || $duration === 180 || $duration === 300 || $duration === 900 || $duration === 1800) {
            $r['duration'] = $duration;
        } else {
            $r['duration'] = 0;
        }
    } else {
        $r['duration'] = 0;
    }

    if (isset($data['caller']) && is_string($data['caller'])) {
        $caller = str_replace(' ', '', $data['caller']);
        $len = mb_strlen($caller);
        if ($len > 0 && $len < 16 ) {
            $r['caller'] = $caller;
        } else {
            $r['caller'] = null;
        }
    } else {
        $r['caller'] = null;
    }

    if (isset($data['called']) && is_string($data['called'])) {
        $called = str_replace(' ', '', $data['called']);
        $len = mb_strlen($called);
        if ($len > 0 && $len < 16 ) {
            $r['called'] = $called;
        } else {
            $r['called'] = null;
        }
    } else {
        $r['called'] = null;
    }

    if (isset($data['page']) && is_numeric($data['page'])) {
        $page = intval($data['page']);
        if ($page >= 0) {
            $r['page'] = $page;
        } else {
            $r['page'] = 0;
        }
    } else {
        $r['page'] = 0;
    }
    
    return $r;
}

function is_date($date, $format = 'Y-m-d H:i:s') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

function filter_report_query($data) {
    if (!is_array($data)) {
        return null;
    }

    $r = null;
    
    if (isset($data['start']) && is_string($data['start'])) {
        $start = $data['start'];
        if (is_date($start)) {
            $r['start'] = $start;
        } else {
            $r['start'] = date('Y-m-d 08:00:00');
        }
    } else {
        $r['start'] = date('Y-m-d 08:00:00');
    }

    if (isset($data['end']) && is_string($data['end'])) {
        $end = $data['end'];
        if (is_date($end)) {
            $r['end'] = $end;
        } else {
            $r['end'] = date('Y-m-d 20:00:00');
        }
    } else {
        $r['end'] = date('Y-m-d 20:00:00');
    }

    $start = strtotime($r['start']);
    $end = strtotime($r['end']);
    if (($end - $start) > 86400) {
        return null;
    }
    
    if (isset($data['export']) && is_string($data['export'])) {
        $export = str_replace(' ', '', $data['export']);
        if ($export === 'on') {
            $r['export'] = true;
        } else {
            $r['export'] = false;
        }
    } else {
        $r['export'] = false;
    }
    
    return $r;
}

function get_cdr_report($db, $company_id, $start, $end) {
    if (!$db || !is_numeric($company_id)) {
        return null;
    }

    $company_id = strval($company_id);
    $data = NULL;

    $result = $db->select('cdr', ['caller_id_number', 'destination_number', 'billsec'], ['AND' => ['accountcode' => $company_id, 'destination_number[!]' => ['service', '7', '9', '001', '002', '003', '004'], 'billsec[>]' => 0, 'start_stamp[<>]' => [$start, $end]]]);

    return $result;
}
