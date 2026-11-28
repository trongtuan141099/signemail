<?php
declare(strict_types=1);
require __DIR__ . '/app-server.php';

function upload_response(int $status, array $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function image_problem(int $status, string $code, string $message): void
{
    upload_response($status, ['code' => $code, 'error' => $message]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    image_problem(405, 'method', 'Tải ảnh bằng biểu mẫu SignEmail.');
}
if (($_SERVER['HTTP_X_SIGNEMAIL_UPLOAD'] ?? '') !== 'brand') {
    image_problem(403, 'origin', 'Hãy tải lại công cụ và thử lại.');
}
if (!extension_loaded('gd')) {
    image_problem(503, 'gd', 'Máy chủ cần bật PHP GD. Bạn vẫn có thể dùng URL ảnh.');
}

$kind = $_POST['kind'] ?? 'logo_url';
if (!in_array($kind, ['logo_url','avatar_url','banner_url','handwriting_url'], true)) {
    image_problem(400, 'kind', 'Vị trí ảnh không hợp lệ.');
}

try {
    $file = $_FILES['image'] ?? null;
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if (in_array($error, [UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE], true)
        || (!$file && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > brand_upload_limit())) {
        image_problem(413, 'size', 'Tệp vượt giới hạn tải của máy chủ. Hãy chọn ảnh nhẹ hơn.');
    }
    if (in_array($error, [UPLOAD_ERR_NO_TMP_DIR,UPLOAD_ERR_CANT_WRITE,UPLOAD_ERR_EXTENSION], true)) {
        image_problem(503, 'server', 'Máy chủ chưa lưu được tệp tải lên. Hãy thử lại sau hoặc dùng URL ảnh.');
    }
    if (!$file || $error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        image_problem(400, 'file', 'Chưa nhận đủ tệp ảnh. Hãy chọn lại ảnh.');
    }
    if ($file['size'] > brand_upload_limit()) {
        image_problem(413, 'size', 'Ảnh vượt giới hạn tải lên. Hãy chọn tệp nhẹ hơn.');
    }

    $info = @getimagesize($file['tmp_name']);
    $formats = [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_BMP];
    if (!$info || !in_array($info[2], $formats, true)) {
        image_problem(422, 'format', 'Không nhận ra ảnh. Dùng PNG, JPG, GIF, WebP hoặc BMP.');
    }
    $gif = $info[2] === IMAGETYPE_GIF;
    $longest = max($info[0], $info[1]);
    if ($gif && ($file['size'] > 3 * 1024 * 1024 || $longest > 1600 || $info[0] * $info[1] > 1600000)) {
        image_problem(422, 'gif_size', 'GIF giữ chuyển động cần tối đa 3 MB và mỗi chiều tối đa 1.600 px. Bạn có thể dùng URL GIF công khai.');
    }
    if ($longest > 7000 || $info[0] * $info[1] > 24000000) {
        image_problem(422, 'dimensions', 'Ảnh rất lớn. Hãy thử ảnh có kích thước nhỏ hơn.');
    }

    $db = db();
    $ip = hash('sha256', 'brand:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $now = time();
    $count = immediate_write($db, static function () use ($db, $ip, $now): int {
        $db->prepare('DELETE FROM brand_upload_limits WHERE expires <= ?')->execute([$now]);
        $db->prepare('INSERT INTO brand_upload_limits(bucket,attempts,expires) VALUES (?,1,?)
            ON CONFLICT(bucket) DO UPDATE SET attempts=MIN(attempts+1,41)')->execute([$ip, $now + 3600]);
        $query = $db->prepare('SELECT attempts FROM brand_upload_limits WHERE bucket=?');
        $query->execute([$ip]);
        return (int) $query->fetchColumn();
    });
    if ($count > 40) image_problem(429, 'rate', 'Đã đạt giới hạn tải ảnh trong giờ. Bạn có thể dùng URL ảnh.');

    $original = file_get_contents($file['tmp_name']);
    $input = $original !== false ? @imagecreatefromstring($original) : false;
    if (!$input) image_problem(422, 'decode', 'Không đọc được dữ liệu ảnh. Hãy thử lại với PNG hoặc JPG.');

    $width = $info[0];
    $height = $info[1];
    $extension = $gif ? 'gif' : 'png';
    if ($gif) {
        $bytes = $original;
    } else {
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($file['tmp_name'], 'IFD0')['Orientation'] ?? 1);
            if (in_array($orientation, [2,4,5,7], true)) imageflip($input, IMG_FLIP_HORIZONTAL);
            $angle = [3=>180,4=>180,5=>90,6=>-90,7=>-90,8=>90][$orientation] ?? 0;
            if ($angle) $input = imagerotate($input, $angle, 0) ?: $input;
        }
        $scale = min(1, ($kind === 'banner_url' ? 1200 : 640) / max(imagesx($input), imagesy($input)));
        $width = max(1, (int) round(imagesx($input) * $scale));
        $height = max(1, (int) round(imagesy($input) * $scale));
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagecopyresampled($image, $input, 0, 0, 0, 0, $width, $height, imagesx($input), imagesy($input));
        ob_start();
        $success = imagepng($image, null, 7);
        $bytes = ob_get_clean();
        if (!$success || !$bytes) throw new RuntimeException('Could not encode image');
    }

    $digest = hash('sha256', $bytes);
    $directory = __DIR__ . '/uploads/brand/';
    $created = null;
    try {
        $name = immediate_write($db, static function () use ($db, $digest, $bytes, $extension, $directory, &$created): ?string {
            $query = $db->prepare('SELECT filename FROM brand_uploads WHERE digest=?');
            $query->execute([$digest]);
            $existing = $query->fetchColumn();
            if ($existing && is_file($directory . $existing)) return $existing;
            $size = strlen($bytes);
            if (!$existing && (int) $db->query('SELECT COALESCE(SUM(bytes),0) FROM brand_uploads')->fetchColumn() + $size > 128 * 1024 * 1024) {
                return null;
            }
            $name = $existing ?: bin2hex(random_bytes(16)) . '.' . $extension;
            $temp = tempnam($directory, 'brand-');
            if ($temp === false) throw new RuntimeException('Could not stage image');
            try {
                if (file_put_contents($temp, $bytes) !== $size) throw new RuntimeException('Could not write image');
                if (DIRECTORY_SEPARATOR === '/' && !chmod($temp, 0644)) throw new RuntimeException('Could not publish image');
                if (!rename($temp, $directory . $name)) throw new RuntimeException('Could not save image');
                $created = $directory . $name;
                $db->prepare('INSERT OR IGNORE INTO brand_uploads(digest,filename,bytes) VALUES (?,?,?)')->execute([$digest,$name,$size]);
            } finally {
                if (is_file($temp)) @unlink($temp);
            }
            return $name;
        });
    } catch (Throwable $failure) {
        if ($created) @unlink($created);
        throw $failure;
    }
    if (!$name) image_problem(507, 'quota', 'Kho ảnh đã đầy. Hãy dùng URL ảnh công khai.');
    upload_response(201, ['url' => canonical_url(linkto('uploads/brand/' . $name)), 'width' => $width, 'height' => $height]);
} catch (Throwable $failure) {
    error_log((string) $failure);
    image_problem(500, 'server', 'Không thể lưu ảnh. Hãy thử lại hoặc dùng URL ảnh công khai.');
}
