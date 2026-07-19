<?php
define('IN_SYS', true);
define('ROOT', dirname(__DIR__) . '/');
include ROOT . 'rd/bootstrap.php';
if (isLogin()) {
    $user = getUser();
    if ($user) clearRememberToken($user['id']);
}
session_destroy();
$_SESSION = [];
$base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
redirect($base . '/index.php');
