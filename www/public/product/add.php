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

if (isset($_POST['action'])) {
    if ($_POST['action'] === 'new') {
        $data = filter_product_create($_POST);

        if ($data != null) {
            $data['company'] = $user['company'];
            $data['create_time'] = date('Y-m-d H:i:s', time());
            product_create($db, $redis, $user['company'], $data);
        } else {
            redirect('product.php');
        }
    }
}

redirect('product.php');
