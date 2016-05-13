<?php

function is_company_order($db, $company_id, $order_id) {
    if (!$db || !is_numeric($company_id) || !is_numeric($order_id)) {
        return false;
    }

    $order_id = intval($order_id);
    $company_id = intval($company_id);
    
    $result = $db->count('orders', ['AND' => ['id' => $order_id, 'company' => $company_id]]);
    if ($result > 0) {
        return true;
    }

    return false;
}

function get_order($db, $order_id) {
    if (!$db || !is_numeric($order_id)) {
        return null;
    }

    $order_id = intval($order_id);
    $result = $db->get('orders', '*', ['id' => $order_id]);
    if ($result) {
        return $result;
    }

    return null;
}

function filter_order_query($redis, $company_id, $get) {
    if (!$redis || !is_numeric($company_id) ||  !is_array($get)) {
        return null;
    }

    $company_id = intval($company_id);
    
    $data = null;
    
    if (isset($get['datetype']) && is_numeric($get['datetype'])) {
        $datetype = intval($get['datetype']);
        $datetype_list = [1, 2, 3];
        if (in_array($datetype, $datetype_list, true)) {
            $data['datetype'] = $datetype;
        } else {
            $data['datetype'] = 1;
        }
    } else {
        $data['datetype'] = 1;
    }

    if (isset($get['start']) && is_string($get['start'])) {
        $start = $get['start'];
        if (is_date($start)) {
            $data['start'] = $start;
        } else {
            $data['start'] = date('Y-m-d 08:00:00');
        }
    } else {
        $data['start'] = date('Y-m-d 08:00:00');
    }

    if (isset($get['end']) && is_string($get['end'])) {
        $end = $get['end'];
        if (is_date($end)) {
            $data['end'] = $end;
        } else {
            $data['end'] = date('Y-m-d 20:00:00');
        }
    } else {
        $data['end'] = date('Y-m-d 20:00:00');
    }

    if (isset($get['status']) && is_numeric($get['status'])) {
        $status = intval($get['status']);
        $status_list = [1, 2, 3, 4, 5];
        if (in_array($status, $status_list, true)) {
            $data['status'] = $status;
        } else {
            $data['status'] = false;
        }
    } else {
        $data['status'] = false;
    }

    if (isset($get['creator']) && is_string($get['creator'])) {
        $creator = str_replace(' ', '', $get['creator']);
        if ($creator != '0') {
            if (is_company_agent($redis, $creator, $company_id)) {
                $data['creator'] = $creator;
            } else {
                $data['creator'] = false;
            }
        } else {
            $data['creator'] = false;
        }
    } else {
        $data['creator'] = false;
    }

    if (isset($get['orderid']) && is_numeric($get['orderid'])) {
        $orderid = intval($get['orderid']);
        if ($orderid > 0) {
            $data['id'] = $orderid;
        } else {
            $data['id'] = false;
        }
    } else {
        $data['id'] = false;
    }

    if (isset($get['export']) && is_string($get['export'])) {
        $export = str_replace(' ', '', $get['export']);
        if ($export === 'on') {
            $data['export'] = true;
        } else {
            $data['export'] = false;
        }
    } else {
        $data['export'] = false;
    }
    
    return $data;
}

function is_date($date, $format = 'Y-m-d H:i:s') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

function the_order_query($db, $redis, $company_id, $data) {
    if (!$db || !$redis || !is_numeric($company_id) || !is_array($data)) {
        return null;
    }

    $company_id = intval($company_id);
    
    $where = ['company' => $company_id];
    $order_by = 'create_time ASC';

    if ($data['id'] != false) {
        $where['id'] = $data['id'];
        $result = $db->select('orders', '*', ['AND' => $where, 'ORDER' => $order_by]);
        if ($result) {
            return $result;
        }
        return null;
    }
        
    switch ($data['datetype']) {
    case 1:
        $where['create_time[<>]'] = [$data['start'], $data['end']];
        $order_by = 'create_time ASC';
        break;
    case 2:
        $where['quality_time[<>]'] = [$data['start'], $data['end']];
        $order_by = 'quality_time ASC';
        break;
    case 3:
        $where['delivery_time[<>]'] = [$data['start'], $data['end']];
        $order_by = 'delivery_time ASC';
        break;
    } 
    
    if ($data['status'] != false) {
        $where['status'] = $data['status'];
    }

    if ($data['creator'] != false) {
        $where['creator'] = $data['creator'];
    }

    $result = $db->select('orders', '*', ['AND' => $where, 'ORDER' => $order_by, 'LIMIT' => 5000]);
    if ($result) {
        return $result;
    }

    return null;
}

function get_all_product($db, $company_id) {
    if (!$db || !is_numeric($company_id)) {
        return null;
    }

    $company_id = intval($company_id);
    $result = $db->select('product', '*', ['company' => $company_id, 'ORDER' => ['id ASC']]);
    if ($result) {
        return $result;
    }

    return null;
}

function filter_order_create($db, $company_id, $post) {
    if (!$db || !is_numeric($company_id) || !is_array($post)) {
        return null;
    }

    $company_id = intval($company_id);

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

    if (isset($post['product']) && is_numeric($post['product'])) {
        $product_id = intval($post['product']);
        if (is_company_product($db, $company_id, $product_id)) {
            $data['product'] = $product_id;
        } else {
            return null;
        }
    } else {
        return null;
    }

    if (isset($post['phone']) && is_string($post['phone'])) {
        $phone = str_replace(' ', '', $post['phone']);
        $len = mb_strlen($phone);
        if ($len > 0) {
            $data['phone'] = $phone;
        } else {
            return null;
        }
    } else {
        return null;
    }

    if (isset($post['telephone']) && is_string($post['telephone'])) {
        $telephone = str_replace(' ', '', $post['telephone']);
        $len = mb_strlen($telephone);
        if ($len > 0) {
            $data['telephone'] = $telephone;
        } else {
            $data['telephone'] = 'null';
        }
    } else {
        $data['telephone'] = 'null';
    }

    if (isset($post['number']) && is_numeric($post['number'])) {
        $number = intval($post['number']);
        if ($number > 0) {
            $data['number'] = $number;
        } else {
            $data['number'] = 1;
        }
    } else {
        $data['number'] = 1;
    }

    if (isset($post['address']) && is_string($post['address'])) {
        $address = str_replace(' ', '', $post['address']);
        $len = mb_strlen($address);
        if ($len > 0) {
            $data['address'] = $address;
        } else {
            return null;
        }
    } else {
        return null;
    }

    if (isset($post['remark']) && is_string($post['remark'])) {
        $remark = str_replace(' ', '', $post['remark']);
        $len = mb_strlen($remark);
        if ($len > 0) {
            $data['remark'] = $remark;
        } else {
            $data['remark'] = 'null';
        }
    } else {
        $data['remark'] = 'null';
    }

    return $data;
}

function is_company_product($db, $company_id, $product_id) {
    if (!$db || !is_numeric($company_id) || !is_numeric($product_id)) {
        return false;
    }

    $company_id = intval($company_id);
    $product_id = intval($product_id);

    $result = $db->count('product', ['AND' => ['id' => $product_id, 'company' => $company_id]]);
    if ($result > 0) {
        return true;
    }

    return false;
}

function the_order_create($db, $company_id, $uid, $data) {
    if (!$db || !is_numeric($company_id) || !is_array($data)) {
        return false;
    }

    $uid = strval($uid);
    $company_id = intval($company_id);

    $order['name'] = $data['name'];
    $order['phone'] = $data['phone'];
    $order['telephone'] = $data['telephone'];
    $order['product'] = $data['product'];
    $order['number'] = $data['number'];
    $order['address'] = $data['address'];
    $order['comment'] = $data['remark'];
    $order['company'] = $company_id;
    $order['creator'] = $uid;
    $order['quality'] = 'null';
    $order['reason'] = 'null';
    $order['status'] = 1;
    $order['express_id'] = 'null';
    $order['logistics_status'] = 'null';
    $datetime = date('Y-m-d H:i:s');
    $order['create_time'] = $datetime;
    $order['quality_time'] = $datetime;
    $order['delivery_time'] = $datetime;

    $result = $db->insert('orders', $order);

    return true;
}

function filter_order_edit($db, $company_id, $post) {
    if (!$db || !is_numeric($company_id) || !is_array($post)) {
        return null;
    }

    $company_id = intval($company_id);

    $data = null;
    
    if (isset($post['name']) && is_string($post['name'])) {
        $name = str_replace(' ', '', $post['name']);
        $len = mb_strlen($name);
        if ($len > 0) {
            $data['name'] = $name;
        }
    }

    if (isset($post['phone']) && is_string($post['phone'])) {
        $phone = str_replace(' ', '', $post['phone']);
        $len = mb_strlen($phone);
        if ($len > 0) {
            $data['phone'] = $phone;
        }
    }

    if (isset($post['product']) && is_numeric($post['product'])) {
        $product = intval($post['product']);
        if (is_company_product($db, $company_id, $product)) {
            $data['product'] = $product;
        }
    }

    if (isset($post['telephone']) && is_string($post['telephone'])) {
        $telephone = str_replace(' ', '', $post['telephone']);
        $len = mb_strlen($telephone);
        if ($len > 0) {
            $data['telephone'] = $telephone;
        }
    }

    if (isset($post['number']) && is_numeric($post['number'])) {
        $number = intval($post['number']);
        if ($number > 0) {
            $data['number'] = $number;
        }
    }

    if (isset($post['address']) && is_string($post['address'])) {
        $address = str_replace(' ', '', $post['address']);
        $len = mb_strlen($address);
        if ($len > 0) {
            $data['address'] = $address;
        }
    }

    if (isset($post['comment']) && is_string($post['comment'])) {
        $comment = str_replace(' ', '', $post['comment']);
        $len = mb_strlen($comment);
        if ($len > 0) {
            $data['comment'] = $comment;
        }
    }

    if (isset($post['express_id']) && is_string($post['express_id'])) {
        $express_id = str_replace(' ', '', $post['express_id']);
        $len = mb_strlen($express_id);
        if ($len > 0) {
            $data['express_id'] = $express_id;
        }
    }

    if (isset($post['status']) && is_numeric($post['status'])) {
        $status = intval($post['status']);
        $status_list = [1, 2, 3, 4, 5];
        if (in_array($status, $status_list, true)) {
            $data['status'] = $status;
        }
    }

    if (isset($post['reason']) && is_string($post['reason'])) {
        $reason = str_replace(' ', '', $post['reason']);
        $len = mb_strlen($reason);
        if ($len > 0) {
            $data['reason'] = $reason;
        }
    }
    
    return $data;
}

function the_edit_order($db, $order_id, $data) {
    if (!$db || !is_numeric($order_id) || !is_array($data)) {
        return false;
    }

    $order_id = intval($order_id);

    $db->update('orders', $data, ['id' => $order_id]);

    return true;
}

function get_order_record($db, $company_id, $called) {
    if (!$db || !is_numeric($company_id) || !is_string($called)) {
        return null;
    }

    $company_id = strval($company_id);
    
    $result = $db->get('cdr', ['start_stamp', 'bleg_uuid'], ['AND' => ['OR' => ['caller_id_number' => $called, 'caller_id_number' => '0'.$called, 'destination_number' => $called, 'destination_number' => '0'.$called], 'accountcode' => $company_id, 'billsec[>]' => 0], 'ORDER' => ['billsec DESC']]);
    if (is_array($result)) {
        return date('Y/m/d/', strtotime(substr($result['start_stamp'], 0, 10))).$result['bleg_uuid'].'.wav';
    }

    return null;
}

