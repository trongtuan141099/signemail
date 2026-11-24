<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app-server.php';

$username = trim($argv[1] ?? '');
if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/D', $username)) {
    fwrite(STDERR, 'Cách dùng: php bin/create-admin.php ten_tai_khoan' . PHP_EOL);
    exit(1);
}

$password = getenv('SIGNEMAIL_ADMIN_PASSWORD');
if ($password === false) {
    fwrite(STDERR, 'Dùng biến môi trường SIGNEMAIL_ADMIN_PASSWORD hoặc nhập mật khẩu qua stdin: ');
    $password = rtrim((string) fgets(STDIN), "\r\n");
}
if (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
    fwrite(STDERR, 'Mật khẩu phải dài từ 12 đến 72 byte.' . PHP_EOL);
    exit(1);
}

$db = db();
$query = $db->prepare('INSERT INTO admins(username,password_hash) VALUES (?,?) ON CONFLICT(username) DO UPDATE SET password_hash=excluded.password_hash');
$query->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, 'Đã lưu tài khoản quản trị: ' . $username . PHP_EOL);
