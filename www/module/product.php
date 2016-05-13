<?php

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

function get_product($db, $pid) {
    if (!$db || !is_numeric($pid)) {
        return null;
    }

    $pid = intval($pid);
    
    $result = $db->get('product', '*', ['id' => $pid]);
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

function product_create($db, $redis, $company_id, $data) {
    if (!$db || !$redis || !is_numeric($company_id) || !is_array($data)) {
        return false;
    }

    $company_id = intval($company_id);
    
    $result = $db->insert('product', $data);
    
    $result = $db->select('product', '*', ['company' => $company_id]);
    if (is_array($result)) {
        foreach ($result as $product) {
            $redis->hMSet('product.'.$product['id'], $product);
        }
        return true;
    }

    return false;
}

function product_remove($db, $redis, $product_id) {
    if (!$db || !$redis || !is_numeric($product_id)) {
        return false;
    }

    $product_id = intval($product_id);
    $db->delete('product', ['id' => $product_id]);
    $redis->delete('product.'.$product_id);
    
    return true;
}

function product_edit($db, $redis, $product_id, $data) {
    if (!$db || !$redis || !is_numeric($product_id) || !is_array($data)) {
        return false;
    }

    $product_id = intval($product_id);
    $data['create_time'] = date('Y-m-d H:i:s', time());
    
    $db->update('product', $data, ['id' => $product_id]);

    if ($redis->exists('product.'.$product_id)) {
        $redis->hMSet('product.'.$product_id, $data);
    }

    return false;
}

function filter_product_create($post) {
    if (!is_array($post)) {
        return null;
    }

    $data = null;

    if (isset($post['name']) && is_string($post['name'])) {
        $name = str_replace(' ', '', $post['name']);
        $len = mb_strlen($name);
        if ($len > 0 && $len <= 18) {
            $data['name'] = $name;
        } else {
            return null;
        }
    } else {
        return null;
    }

    if (isset($post['price']) && is_numeric($post['price'])) {
        $price = (float)$post['price'];
        if ($price > 0) {
            $data['price'] = round($price, 2);
        } else {
            $data['price'] = 0;
        }
    } else {
        $data['price'] = 0;
    }

    if (isset($post['inventory']) && is_numeric($post['inventory'])) {
        $inventory = intval($post['inventory']);
        if ($inventory > 0) {
            $data['inventory'] = $inventory;
        } else {
            $data['inventory'] = 9999;
        }
    } else {
        $data['inventory'] = 9999;
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

function filter_product_edit($post) {
    if (!is_array($post)) {
        return null;
    }

    $data = null;
    
    if (isset($post['name']) && is_string($post['name'])) {
        $name = str_replace(' ', '', $post['name']);
        $len = mb_strlen($name);
        if ($len > 1 && $len <= 16) {
            $data['name'] = $name;
        }
    }

    if (isset($post['price']) && is_numeric($post['price'])) {
        $price = (float)$post['price'];
        if ($price > 0) {
            $data['price'] = round($price, 2);
        }
    }

    if (isset($post['inventory']) && is_numeric($post['inventory'])) {
        $inventory = intval($post['inventory']);
        if ($inventory > 0) {
            $data['inventory'] = $inventory;
        }
    }

    if (isset($post['remark']) && is_string($post['remark'])) {
        $remark = str_replace(' ', '', $post['remark']);
        $len = mb_strlen($remark);
        if ($len > 0) {
            $data['remark'] = $remark;
        }
    }

    return $data;
}
