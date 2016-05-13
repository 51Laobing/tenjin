<?php
require '../../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/module/agent.php';
require $app.'/module/order.php';

class_detect($user['type'], '2');

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

$pbx = new medoo([
    'database_type' => 'pgsql',
    'database_name' => PBX_DB,
    'server' => PBX_HOST,
    'port' => PBX_PORT,
    'username' => PBX_USER,
    'password' => PBX_PASSWORD,
    'charset' => 'utf8'
]);

if (isset($_POST['sub']) && $_POST['sub'] === 'ok') {
    $data = filter_order_create($db, $user['company'], $_POST);
    
    if ($data != null) {
        $success = the_order_create($orderdb, $user['company'], $user['uid'], $data);
        if ($success) {
            $html = "<!DOCTYPE html>\n";
            $html .= "<html lang=\"zh-cn\">\n";
            $html .= "<head>\n";
            $html .= "<title>订单添加成功</title>\n";
            $html .= "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">\n";
            $html .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"/css/bootstrap.min.css\">\n";
            $html .= "</head>\n";
            $html .= "<body style=\"background-color:#eeeeee\">\n";
            $html .= "<div class=\"jumbotron\" style=\"padding-top:90px;width:665px;height:290px;text-align:center\">\n";
            $html .= "<h3><span class=\"glyphicon glyphicon-ok-sign\" aria-hidden=\"true\"></span> 订单添加成功!</h3>\n";
            $html .= "<p>您的订单添加成功，请关闭这个窗口然后按 F5 刷新数据</p>\n";
            $html .= "</div>\n";
            $html .= "</body>\n";
            $html .= "</html>\n";
            echo $html;
        } else {
            $html = "<!DOCTYPE html>\n";
            $html .= "<html lang=\"zh-cn\">\n";
            $html .= "<head>\n";
            $html .= "<title>订单添加成功</title>\n";
            $html .= "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">\n";
            $html .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"/css/bootstrap.min.css\">\n";
            $html .= "</head>\n";
            $html .= "<body style=\"background-color:#eeeeee\">\n";
            $html .= "<div class=\"jumbotron\" style=\"padding-top:90px;width:665px;height:290px;text-align:center\">\n";
            $html .= "<h3><span class=\"glyphicon glyphicon-remove-sign\" aria-hidden=\"true\"></span> 订单添加失败!</h3>\n";
            $html .= "<p>系统在添加订单的时候遇到故障,请联系技术人员.</p>\n";
            $html .= "</div>\n";
            $html .= "</body>\n";
            $html .= "</html>\n";
            echo $html;
        }
    } else {
        $html = "<!DOCTYPE html>\n";
        $html .= "<html lang=\"zh-cn\">\n";
        $html .= "<head>\n";
        $html .= "<title>订单添加成功</title>\n";
        $html .= "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">\n";
        $html .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"/css/bootstrap.min.css\">\n";
        $html .= "</head>\n";
        $html .= "<body style=\"background-color:#eeeeee\">\n";
        $html .= "<div class=\"jumbotron\" style=\"padding-top:90px;width:665px;height:290px;text-align:center\">\n";
        $html .= "<h3><span class=\"glyphicon glyphicon-remove-sign\" aria-hidden=\"true\"></span> 订单添加失败!</h3>\n";
        $html .= "<p>订单信息不符合要求,请重新填写订单. <a href=\"javascript:history.go(-1);\">返回修改</a></p>\n";
        $html .= "</div>\n";
        $html .= "</body>\n";
        $html .= "</html>\n";
        echo $html;
    }
} else {
    $called = get_curr_called($pbx, $user['uid']);
    if ($called[0] === '0') {
        $called = substr($called, 1, strlen($called));
    }
    
    $address = $called ? get_numbers_attribution($called) : '';

    $data['called'] = $called ? $called : '';
    $data['address'] = $address;

    $data['products'] = fetch_all_product($db, $user['company']);

    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/order.add.html', $data);
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