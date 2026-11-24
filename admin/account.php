<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_admin();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    try {
        $current = $_POST['current_password'] ?? null;
        if (!is_string($current) || $current === '' || strlen($current) > 72 || str_contains($current, "\0")) {
            throw new InvalidArgumentException('Mật khẩu hiện tại không đúng.');
        }
        $connection = db();
        $query = $connection->prepare('SELECT password_hash FROM admins WHERE id=?');
        $query->execute([$user['id']]);
        $previous = $query->fetchColumn();
        if (!is_string($previous) || !password_verify($current, $previous)) {
            throw new InvalidArgumentException('Mật khẩu hiện tại không đúng.');
        }
        $hash = new_admin_password_hash($_POST);
        if (password_verify($_POST['new_password'], $previous)) {
            throw new InvalidArgumentException('Chọn mật khẩu mới khác với mật khẩu hiện tại.');
        }
        $change = $connection->prepare('UPDATE admins SET password_hash=? WHERE id=? AND password_hash=?');
        $change->execute([$hash, $user['id'], $previous]);
        if (!$change->rowCount()) {
            throw new InvalidArgumentException('Phiên tài khoản đã thay đổi. Hãy đăng nhập lại.');
        }
        session_regenerate_id(true);
        $_SESSION = [];
        flash('Đã đổi mật khẩu. Hãy đăng nhập lại bằng mật khẩu mới.');
        redirect(linkto('admin/login.php'));
    } catch (Throwable $failure) {
        $error = admin_error($failure);
    }
}
admin_header('Tài khoản', 'account.php', $user);
?>
<div class="admin-heading"><div><h1>Tài khoản</h1><p>Đổi mật khẩu cho <?= h($user['username']) ?>.</p></div></div>
<section class="admin-card account-card">
    <?php if ($error): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?>
    <form method="post" class="admin-stack">
        <?php csrf_input(); ?>
        <label class="field"><span>Mật khẩu hiện tại</span><input name="current_password" type="password" autocomplete="current-password" required></label>
        <?php admin_password_fields(); ?>
        <div><button class="button">Lưu mật khẩu mới</button></div>
    </form>
</section>
<?php admin_footer(); ?>
