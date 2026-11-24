<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (admin_exists()) redirect(linkto('admin/login.php'));

function read_or_create_setup_code(string $path): string
{
    $prefix = "<?php\nhttp_response_code(404);\nexit;\n// Mã thiết lập: ";
    if (!is_file($path)) {
        $contents = $prefix . bin2hex(random_bytes(16)) . "\n";
        if (@file_put_contents($path, $contents, LOCK_EX) !== strlen($contents)) {
            if (is_file($path)) @unlink($path);
            throw new RuntimeException('Could not write admin setup code');
        }
        @chmod($path, 0600);
    }

    $contents = @file_get_contents($path, false, null, 0, 256);
    $code = is_string($contents) && str_starts_with($contents, $prefix)
        ? trim(substr($contents, strlen($prefix))) : '';
    if (!preg_match('/^[a-f0-9]{32}$/D', $code)) {
        throw new RuntimeException('Could not read admin setup code');
    }
    return $code;
}

$local = local_admin_setup();
$available = $local || admin_https();
$codePath = dirname(__DIR__) . '/storage/admin-setup-code.php';
$code = null;
$error = '';
$submitting = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($submitting) verify_post();

if ($available && !$local) {
    try {
        $code = immediate_write(db(), static fn(): ?string => admin_exists() ? null : read_or_create_setup_code($codePath));
        if ($code === null) redirect(linkto('admin/login.php'));
    } catch (Throwable $failure) {
        error_log($failure->getMessage());
        $error = 'Không thể chuẩn bị mã thiết lập. Hãy kiểm tra quyền đọc và ghi của PHP trong thư mục storage.';
    }
}

if ($submitting && ($local || $code !== null)) {
    try {
        if (!$local && (!is_string($_POST['setup_code'] ?? null) || !hash_equals($code, trim($_POST['setup_code'])))) {
            throw new InvalidArgumentException('Mã thiết lập chưa đúng. Hãy kiểm tra mã trong tệp trên hosting.');
        }
        $username = field($_POST, 'username', 64, true);
        if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/D', $username)) {
            throw new InvalidArgumentException('Tên đăng nhập cần ít nhất 3 ký tự. Có thể dùng chữ, số, dấu chấm, gạch ngang và gạch dưới.');
        }
        $hash = new_admin_password_hash($_POST);
        $connection = db();
        immediate_write($connection, static function () use ($connection, $username, $hash): void {
            if (admin_exists()) throw new InvalidArgumentException('Tài khoản quản trị đã được thiết lập.');
            $connection->prepare('INSERT INTO admins(username,password_hash) VALUES (?,?)')->execute([$username,$hash]);
        });
        if (is_file($codePath) && !@unlink($codePath)) {
            error_log('Could not remove used admin setup code');
        }
        session_regenerate_id(true);
        $_SESSION = [];
        flash('Đã tạo tài khoản. Hãy đăng nhập bằng mật khẩu vừa thiết lập.');
        redirect(linkto('admin/login.php'));
    } catch (Throwable $failure) {
        $error = admin_error($failure);
    }
}

admin_header('Thiết lập quản trị');
?>
<section class="admin-card login-card">
    <h1>Thiết lập tài khoản quản trị</h1>
    <p>Tạo tên đăng nhập và mật khẩu cho tài khoản đầu tiên.</p>
    <?php if ($error): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?>
    <?php if ($local || $code !== null): ?>
    <?php if (!$local): ?>
    <p>Trong File Manager của hosting, mở tệp <code>storage/admin-setup-code.php</code> bằng trình xem hoặc trình sửa tệp. Sao chép chuỗi ở dòng <strong>Mã thiết lập</strong> vào biểu mẫu bên dưới.</p>
    <?php endif; ?>
    <form method="post" class="admin-stack">
        <?php csrf_input(); ?>
        <label class="field"><span>Tên đăng nhập</span><input name="username" maxlength="64" autocomplete="username" required></label>
        <?php if (!$local): ?><label class="field"><span>Mã thiết lập một lần</span><input name="setup_code" type="password" autocomplete="off" spellcheck="false" minlength="32" maxlength="32" required></label><?php endif; ?>
        <?php admin_password_fields(); ?>
        <button class="button">Tạo tài khoản</button>
    </form>
    <?php elseif (!$available): ?>
    <p>Mở trang quản trị bằng HTTPS để thiết lập tài khoản trên hosting. Trên XAMPP, bạn có thể mở trang bằng localhost hoặc 127.0.0.1.</p>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
