<?php
require '../../loader.php';
require $app.'/libs/latte.php';
require $app.'/libs/medoo.php';
require $app.'/module/product.php';

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

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $pid = $_GET['id'];
    if (is_company_product($db, $user['company'], $pid)) {
        $product = get_product($db, $pid);
        if ($product === null) {
            redirect('product.php');
        }

        $data['user'] = $user;
        $data['product'] = $product;

        $radius = redis(RADIUS_HOST, RADIUS_PORT, RADIUS_PASSWORD, RADIUS_DB);
        $data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;
        
        $latte = new Latte\Engine;
        $latte->setTempDirectory('/dev/shm');
        $latte->render($app.'/view/product.edit.html', $data);
    } else {
        redirect('product.php');
    }
} else if (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $product_id = $_POST['id'];
    if (is_company_product($db, $user['company'], $product_id)) {
        $data = filter_product_edit($_POST);
        if ($data != null) {
            if (product_edit($db, $redis, $product_id, $data)) {
                redirect('product.php');
            }
        }
        redirect('product.php?id='.$product_id);
    } else {
        redirect('product.php');
    }
} else {
    redirect('product.php');
}

