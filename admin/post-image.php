<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
if (!admin_user()) {
    http_response_code(401);
    echo json_encode(['error' => 'Phiên đăng nhập đã hết hạn.']);
    exit;
}
verify_post();
try {
    $name = upload_image('image');
    if ($name === '') throw new InvalidArgumentException('Hãy chọn ảnh cần tải.');
    echo json_encode(['location' => 'uploads/' . $name]);
} catch (Throwable $error) {
    http_response_code($error instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode(['error' => admin_error($error)]);
}
