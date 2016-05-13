<?php
require '../loader.php';
require $app.'/libs/latte.php';

class_detect($user['type'], '1');

$data['user'] = $user;
$data['domain'] = DOMAIN;

$radius = redis(RADIUS_HOST, RADIUS_PORT, RADIUS_PASSWORD, RADIUS_DB);
$data['alert'] = is_money_enough($redis, $radius, $user['company']) ? false : true;

$latte = new Latte\Engine;
$latte->setTempDirectory('/dev/shm');
$latte->render($app.'/view/help.html', $data);
