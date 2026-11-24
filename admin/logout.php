<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

verify_post();
$_SESSION = [];
$cookie = session_get_cookie_params();
unset($cookie['lifetime']);
setcookie(session_name(), '', ['expires' => time() - 3600] + $cookie);
session_destroy();
redirect(linkto('admin/login.php'));
