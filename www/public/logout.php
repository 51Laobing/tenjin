<?php
require_once '../loader.php';

logout($redis, $user['uid']);
redirect('login.php');
