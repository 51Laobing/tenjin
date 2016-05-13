<?php
require '../../loader.php';
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

$cdr = new medoo([
    'database_type' => 'pgsql',
    'database_name' => CDR_DB,
    'server' => CDR_HOST,
    'port' => CDR_PORT,
    'username' => CDR_USER,
    'password' => CDR_PASSWORD,
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

if (isset($_POST['sub']) && $_POST['sub'] === 'ok') {
    $order_id = intval($_POST['id']);
    if (is_company_order($orderdb, $user['company'], $order_id)) {
        $data = filter_order_edit($db, $user['company'], $_POST);
        if ($data != null) {
            $data['quality'] = $user['uid'];

            // update quality time
            $status_list = [2, 3];
            if (in_array($data['status'], $status_list, true)) {
                $data['quality_time'] = date('Y-m-d H:i:s');
            }
            
            the_edit_order($orderdb, $order_id, $data);
            $html = "<!DOCTYPE html>\n";
            $html .= "<html lang=\"zh-cn\">\n";
            $html .= "<head>\n";
            $html .= "<title>订单修改成功</title>\n";
            $html .= "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">\n";
            $html .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"/css/bootstrap.min.css\">\n";
            $html .= "</head>\n";
            $html .= "<body>\n";
            $html .= "<div class=\"jumbotron\" style=\"padding-top:120px;width:785px;height:445px;text-align:center\">\n";
            $html .= "<h3><span class=\"glyphicon glyphicon-ok-sign\" aria-hidden=\"true\"></span> 订单修改成功!</h3>\n";
            $html .= "<p>您的订单修改成功，请关闭这个窗口然后按 F5 刷新数据</p>\n";
            $html .= "</div>\n";
            $html .= "</body>\n";
            $html .= "</html>\n";
            echo $html;
        } else {
            $html = "<!DOCTYPE html>\n";
            $html .= "<html lang=\"zh-cn\">\n";
            $html .= "<head>\n";
            $html .= "<title>订单修改成功</title>\n";
            $html .= "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">\n";
            $html .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"/css/bootstrap.min.css\">\n";
            $html .= "</head>\n";
            $html .= "<body>\n";
            $html .= "<div class=\"jumbotron\" style=\"padding-top:120px;width:785px;height:445px;text-align:center\">\n";
            $html .= "<h1><span class=\"glyphicon glyphicon-ok-sign\" aria-hidden=\"true\"></span> 订单修改失败!</h1>\n";
            $html .= "<p>您的订单修改失败，请关闭这个窗口重新编辑订单.</p>\n";
            $html .= "</div>\n";
            $html .= "</body>\n";
            $html .= "</html>\n";
            echo $html;
        }
    } else {
        echo '<b>Error: Illegal operation!</b>';
    }
} else if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $order_id = intval($_GET['id']);
    if (is_company_order($orderdb, $user['company'], $order_id)) {
        $order = get_order($orderdb, $order_id);
        if ($order != null) {
            $data['order'] = $order;

            $creator = fetch_agent($redis, $order['creator']);
            if ($creator === null) {
                $creator = ['uid' => 'Unknown', 'name' => 'Unknown'];
            }

            $data['creator'] = $creator;
            $data['products'] = fetch_all_product($db, $user['company']);

	    $record = get_order_record($cdr, $user['company'], $order['phone']);
	    if ($record === null) {
	        $record = 'null.wav';
	    }
	    $data['record'] = $record;

            $latte = new Latte\Engine;
            $latte->setTempDirectory('/dev/shm');
            $latte->render($app.'/view/order.edit.html', $data);
        }
    }
}

function fetch_agent($redis, $uid) {
    if (!$redis || !is_numeric($uid)) {
        return null;
    }

    $reply = $redis->hMGet('agent.'.$uid, ['name', 'password', 'type', 'callerid', 'company', 'icon', 'status', 'last_login', 'last_ipaddr']);

    // check redis return value
    if (in_array(false, $reply, true)) {
        return null;
    }

    // format return value
    $reply['uid'] = $uid;
    $reply['type'] = intval($reply['type']);
    $reply['company'] = intval($reply['company']);
    $reply['status'] = intval($reply['status']);
    $reply['img'] = str_replace('jpg', 'png', $reply['icon']);
    $reply['last_login'] = date('Y-m-d H:i:s', intval($reply['last_login']));
    
    return $reply;
}

function fetch_all_product($db, $company_id) {
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

