<?php
require '../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/module/order.php';

class_detect($user['type'], '1');

$db = new medoo([
    'database_type' => 'pgsql',
    'database_name' => PGSQL_DB,
    'server' => PGSQL_HOST,
    'port' => PGSQL_PORT,
    'username' => PGSQL_USER,
    'password' => PGSQL_PASSWORD,
    'charset' => 'utf8'
]);

$orderdb = new medoo([
    'database_type' => 'pgsql',
    'database_name' => ORDER_DB,
    'server' => ORDER_HOST,
    'port' => ORDER_PORT,
    'username' => ORDER_USER,
    'password' => ORDER_PASSWORD,
    'charset' => 'utf8'
]);

if (isset($_GET['sub']) && $_GET['sub'] === 'ok') {
    $where = filter_order_query($redis, $user['company'], $_GET);

    $data['orders'] = the_order_query($orderdb, $redis, $user['company'], $where);

    $data['user'] = $user;
    $temp = get_all_product($db, $user['company']);
    $products = null;
    if (is_array($temp)) {
        foreach ($temp as $product) {
            $products[$product['id']] = $product;
        }
    }
    
    if ($where['export']) {
        header('Content-type: text/csv');
        header('Content-Disposition: attachment; filename="'.date('Y-m-d',time()).'.csv"');
        echo '订单编号,客户姓名,手机号码,固定电话,商品名称,商品价格,商品数量,收货地址,备注信息,订单状态,快递单号,物流状态,审核备注,下单座席,质检员,下单时间,审核时间',"\n";
        foreach ($data['orders'] as $order) {
            if ($order['status'] == 1) {
                $status = '待审核';
            } else if ($order['status'] === 2) {
                $status = '已通过';
            } else if ($order['status'] === 3) {
                $status = '不通过';
            } else if ($order['status'] === 4) {
                $status = '已发货';
            } else if ($order['status'] === 5) {
                $status = '已发货';
            } else {
                $status = '未 知';
            }
            echo $order['id'],',',$order['name'],',',$order['phone'],',',$order['telephone'],',',$products[$order['product']]['name'],',',$products[$order['product']]['price'],',',$order['number'],',',$order['address'],',',$order['comment'],',',$status,',',$order['express_id'],',',$order['logistics_status'],',',$order['reason'],',',$order['creator'],',',$order['quality'],',',$order['create_time'],',',$order['quality_time'],',',"\n";
        }
        exit;
    }
    
    $data['products'] = $products;
    $data['where'] = $where;
    $data['agents'] = fetch_all_agent($db, $user['company']);

    $radius = redis(RADIUS_HOST, RADIUS_PORT, RADIUS_PASSWORD, RADIUS_DB);
    $data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;
    
    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/order.html', $data);
} else {
    $data['user'] = $user;
    $data['orders'] = null;
    $data['where'] = ['datetype' => 1, 'start' => date('Y-m-d 08:00:00'), 'end' => date('Y-m-d 20:00:00'), 'status' => false, 'creator' => false, 'id' => "", 'export' => false];
    $data['agents'] = fetch_all_agent($db, $user['company']);

    $radius = redis(RADIUS_HOST, RADIUS_PORT, RADIUS_PASSWORD, RADIUS_DB);
    $data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;
    
    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/order.html', $data);
}

function fetch_all_agent($db, $company_id) {
    if (!$db || !is_numeric($company_id)) {
        return null;
    }

    $company_id = intval($company_id);
    $result = $db->select('agent', ['uid', 'name'], ['company' => $company_id, 'ORDER' => ['uid ASC']]);

    if ($result) {
        return $result;
    }

    return null;
}
