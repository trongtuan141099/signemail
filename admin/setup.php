<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (admin_exists()) redirect(linkto('admin/login.php'));

$local = local_admin_setup();
$secret = getenv('SIGNEMAIL_SETUP_TOKEN') ?: '';
$available = $local || (strlen($secret) >= 32 && admin_https());
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    try {
        if (!$available || (!$local && (!is_string($_POST['setup_token'] ?? null) || !hash_equals($secret, $_POST['setup_token'])))) {
            throw new InvalidArgumentException('Mã thiết lập không hợp lệ hoặc thiết lập chưa được mở.');
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
    <?php if ($available): ?>
    <form method="post" class="admin-stack">
        <?php csrf_input(); ?>
        <label class="field"><span>Tên đăng nhập</span><input name="username" maxlength="64" autocomplete="username" required></label>
        <?php if (!$local): ?><label class="field"><span>Mã thiết lập từ máy chủ</span><input name="setup_token" type="password" autocomplete="off" required></label><?php endif; ?>
        <?php admin_password_fields(); ?>
        <button class="button">Tạo tài khoản</button>
    </form>
    <?php else: ?>
    <p>Trên XAMPP, mở trang này bằng localhost hoặc 127.0.0.1. Trên hosting, bật HTTPS và đặt SIGNEMAIL_SETUP_TOKEN trong cấu hình máy chủ.</p>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
