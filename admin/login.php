<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (admin_user()) {
    redirect(linkto('admin/'));
}
if (!admin_exists()) redirect(linkto('admin/setup.php'));

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    $ip = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $now = time();
    $db = db();
    $attemptState = immediate_write($db, static function () use ($db, $ip, $now): array {
        $db->prepare('DELETE FROM login_attempts WHERE expires <= ?')->execute([$now]);
        $db->prepare('INSERT INTO login_attempts(bucket,attempts,expires) VALUES (?,1,?)
            ON CONFLICT(bucket) DO UPDATE SET attempts=MIN(attempts+1,13)')->execute([$ip, $now + 900]);
        $lookup = $db->prepare('SELECT attempts,expires FROM login_attempts WHERE bucket=?');
        $lookup->execute([$ip]);
        return $lookup->fetch();
    });

    if ((int) $attemptState['attempts'] > 12) {
        http_response_code(429);
        header('Retry-After: ' . max(1, (int) $attemptState['expires'] - $now));
        $error = 'Bạn đã thử quá nhiều lần. Vui lòng thử lại sau.';
    } else {
        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $query = $db->prepare('SELECT id,password_hash FROM admins WHERE username=?');
        $query->execute([substr($username, 0, 64)]);
        $user = $query->fetch();
        $hash = $user['password_hash'] ?? '$2y$10$6pgZGuYZi1YN6eBJLeZQEOewvuYdMm0k5VIMvt4kWrl1gBUnB45/K';
        $validPassword = strlen($password) <= 72 && !str_contains($password, "\0") && password_verify($password, $hash);
        if ($user && $validPassword && strlen($username) <= 64 && $password !== '') {
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $update = $db->prepare('UPDATE admins SET password_hash=? WHERE id=? AND password_hash=?');
                $update->execute([$newHash, $user['id'], $hash]);
                if ($update->rowCount()) {
                    $hash = $newHash;
                }
            }
            $db->prepare('DELETE FROM login_attempts WHERE bucket=?')->execute([$ip]);
            session_regenerate_id(true);
            $_SESSION = ['admin' => $user['id'], 'authenticated_at' => $now, 'last_seen' => $now, 'credential' => hash('sha256', $hash)];
            redirect(linkto('admin/'));
        }
        $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
    }
}

admin_header('Đăng nhập');
?>
<section class="admin-card login-card">
    <h1>Đăng nhập quản trị</h1>
    <p>Quản lý mẫu chữ ký, nội dung blog và quảng cáo.</p>
    <?php if ($error): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?>
    <form method="post" class="admin-stack">
        <?php csrf_input(); ?>
        <label class="field"><span>Tên đăng nhập</span><input name="username" maxlength="64" autocomplete="username" required></label>
        <label class="field"><span>Mật khẩu</span><input name="password" type="password" autocomplete="current-password" required></label>
        <button class="button">Đăng nhập</button>
    </form>
</section>
<?php admin_footer(); ?>
