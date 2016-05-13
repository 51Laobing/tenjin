<?php

// url redirect
function redirect($page) {
    $host = $_SERVER['HTTP_HOST'];
    $port = $_SERVER['SERVER_PORT'];
    header("Location: http://$host/$page");
    exit();
}
