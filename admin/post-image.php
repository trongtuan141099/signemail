<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
if (!admin_user()) {
    http_response_code(401);
    echo json_encode(['error' => 'Phiên đăng nhập đã hết hạn.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    http_response_code(413);
    echo json_encode(['error' => 'Hosting không nhận được nội dung tải lên. Hãy kiểm tra post_max_size và chọn ảnh nhỏ hơn.']);
    exit;
}
verify_post();
try {
    $name = upload_image('image');
    if ($name === '') throw new InvalidArgumentException('Hãy chọn ảnh cần tải.');
    echo json_encode(['location' => 'uploads/' . $name]);
} catch (ImageStorageException $error) {
    http_response_code(503);
    echo json_encode(['error' => $error->getMessage()]);
} catch (Throwable $error) {
    http_response_code($error instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode(['error' => admin_error($error)]);
}
