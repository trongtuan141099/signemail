<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app-server.php';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('signemail_admin');
session_set_cookie_params([
    'path' => linkto('admin/'), 'httponly' => true, 'samesite' => 'Strict',
    'secure' => admin_https(),
]);
session_start();

function admin_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || getenv('SIGNEMAIL_FORCE_SECURE') === '1';
}

function admin_exists(): bool
{
    return (bool) db()->query('SELECT 1 FROM admins LIMIT 1')->fetchColumn();
}

function local_admin_setup(): bool
{
    return getenv('SIGNEMAIL_LOCAL_SETUP') !== '0'
        && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
        && preg_match('~^(?:localhost|127\.0\.0\.1|\[::1\])(?::\d{1,5})?$~iD', $_SERVER['HTTP_HOST'] ?? '')
        && !isset($_SERVER['HTTP_FORWARDED'])
        && !isset($_SERVER['HTTP_X_FORWARDED_FOR'])
        && !isset($_SERVER['HTTP_X_REAL_IP']);
}

function admin_password_fields(): void
{
    ?>
    <label class="field"><span>Mật khẩu mới</span><input name="new_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required><small>Ít nhất 12 byte, tối đa 72 byte.</small></label>
    <label class="field"><span>Nhập lại mật khẩu mới</span><input name="confirm_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
    <?php
}

function new_admin_password_hash(array $input): string
{
    $password = $input['new_password'] ?? null;
    if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
        throw new InvalidArgumentException('Mật khẩu phải dài từ 12 đến 72 byte.');
    }
    if ($password !== ($input['confirm_password'] ?? null)) {
        throw new InvalidArgumentException('Hai lần nhập mật khẩu mới cần giống nhau.');
    }
    return password_hash($password, PASSWORD_DEFAULT);
}

function admin_user(): ?array
{
    if (!isset($_SESSION['admin'], $_SESSION['authenticated_at'], $_SESSION['last_seen'])) {
        return null;
    }
    if (time() - $_SESSION['last_seen'] > 1800 || time() - $_SESSION['authenticated_at'] > 28800) {
        $_SESSION = [];
        session_regenerate_id(true);
        return null;
    }
    $query = db()->prepare('SELECT id,username,password_hash FROM admins WHERE id=?');
    $query->execute([$_SESSION['admin']]);
    $user = $query->fetch();
    if (!$user || !hash_equals($_SESSION['credential'], hash('sha256', $user['password_hash']))) {
        $_SESSION = [];
        session_regenerate_id(true);
        return null;
    }
    $_SESSION['last_seen'] = time();
    unset($user['password_hash']);
    return $user;
}

function require_admin(): array
{
    return admin_user() ?? redirect(linkto('admin/login.php'));
}

function csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_input(): void
{
    echo '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
}

function verify_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        exit;
    }
    if (!isset($_POST['csrf']) || !is_string($_POST['csrf']) || !hash_equals(csrf(), $_POST['csrf'])) {
        http_response_code(403);
        exit('Phiên biểu mẫu đã hết hạn. Hãy tải lại trang.');
    }
}

function flash(string $message, bool $error = false): void
{
    $_SESSION['flash'] = ['message' => $message, 'error' => $error];
}

function show_flash(): void
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div role="' . ($flash['error'] ? 'alert' : 'status') . '" class="notice' . ($flash['error'] ? ' error' : '') . '">' . h($flash['message']) . '</div>';
    }
}

function admin_error(Throwable $error): string
{
    if ($error instanceof InvalidArgumentException || $error instanceof JsonException) {
        return $error->getMessage();
    }
    error_log((string) $error);
    return 'Thao tác chưa hoàn tất. Vui lòng thử lại.';
}

function admin_header(string $title, string $active = '', ?array $user = null, bool $quill = false): void
{
    ?><!doctype html>
<html lang="vi"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> · Quản trị SignEmail</title>
<link rel="stylesheet" href="<?= h(linkto('assets/site.css')) ?>">
<link rel="stylesheet" href="<?= h(linkto('assets/studio.css')) ?>">
<link rel="stylesheet" href="<?= h(linkto('admin/admin.css')) ?>">
<?php if ($quill): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
<script defer src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<?php endif; ?>
<script defer src="<?= h(linkto('admin/admin.js')) ?>"></script>
</head><body>
<header class="admin-header"><div class="admin-shell admin-nav">
    <a class="brand" href="<?= h(linkto('admin/')) ?>"><img class="brand-icon" src="<?= h(linkto('assets/sign-mark.png')) ?>" width="36" height="36" alt="">Sign<span>Email</span></a>
    <?php if ($user): ?>
    <nav aria-label="Quản trị">
        <?php foreach (['index.php'=>'Tổng quan','templates.php'=>'Mẫu chữ ký','posts.php'=>'Bài viết','ads.php'=>'Quảng cáo','account.php'=>'Tài khoản'] as $file => $label): ?>
        <a <?= $active === $file ? 'aria-current="page"' : '' ?> href="<?= h(linkto('admin/' . $file)) ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <form method="post" action="<?= h(linkto('admin/logout.php')) ?>"><?php csrf_input(); ?><button class="button button-small button-secondary">Đăng xuất</button></form>
    <?php else: ?><a class="text-link" href="<?= h(linkto()) ?>">Về trang chủ</a><?php endif; ?>
</div></header>
<main class="admin-shell admin-main<?= !$user ? ' login-main' : '' ?>">
    <?php show_flash();
}

function admin_footer(): void
{
    echo '</main></body></html>';
}

function admin_input(string $key, string $label, array $data, int $max, string $type = 'text', bool $required = false): void
{
    ?>
    <label class="field"><span><?= h($label) ?></span><input name="<?= h($key) ?>" type="<?= h($type) ?>" value="<?= h(is_string($data[$key] ?? null) ? $data[$key] : '') ?>" maxlength="<?= $max ?>" <?= $required ? 'required' : '' ?>></label>
    <?php
}

function get_id(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) {
        throw new InvalidArgumentException('Mã mục không hợp lệ.');
    }
    return $id;
}
