<?php
require '../loader.php';
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

if (isset($_GET['action']) && isset($_GET['id'])) {
    if ($_GET['action'] === 'remove') {
        $product_id = intval($_GET['id']);
        if ($product_id > 0) {
            if (is_company_product($db, $user['company'], $product_id)) {
                product_remove($db, $redis, $product_id);
            }
        }
    }

    redirect('product.php');
} else {
    $data['user'] = $user;
    $data['products'] = get_all_product($db, $user['company']);

    $radius = redis(RADIUS_HOST, RADIUS_PORT, RADIUS_PASSWORD, RADIUS_DB);
    $data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;
    
    $latte = new Latte\Engine;
    $latte->setTempDirectory('/dev/shm');
    $latte->render($app.'/view/product.html', $data);
}

