<?php
require_once '../config.php';

$ch = curl_init();
$url = 'http://apis.baidu.com/apistore/mobilenumber/mobilenumber?phone='.$_GET['phone'];
$header = ['apikey: '.APIKEY];

// 添加apikey到header
curl_setopt($ch, CURLOPT_HTTPHEADER  , $header);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

// 执行HTTP请求
curl_setopt($ch , CURLOPT_URL , $url);
$res = curl_exec($ch);
$data = json_decode($res);
echo $data->retData->province,$data->retData->city;
