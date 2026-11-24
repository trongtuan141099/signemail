<?php
declare(strict_types=1);
require __DIR__ . '/layout.php';

const SUPPORT_EMAIL = 'support@tt-hub.vn';

session_name('tthub_contact');
session_start([
    'use_only_cookies'=>1, 'use_strict_mode'=>1, 'cookie_httponly'=>true,
    'cookie_samesite'=>'Lax', 'cookie_path'=>linkto(),
    'cookie_secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || getenv('SIGNEMAIL_FORCE_SECURE') === '1',
]);
header('Cache-Control: no-store');
$receipt = $_SESSION['contact_receipt'] ?? null;
unset($_SESSION['contact_receipt']);
$_SESSION['contact_token'] ??= bin2hex(random_bytes(32));
$token = $_SESSION['contact_token'];
$values = ['name'=>'', 'email'=>'', 'subject'=>'', 'message'=>''];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($token, $_POST['csrf'])) {
            http_response_code(403);
            throw new InvalidArgumentException('Biểu mẫu đã hết hạn. Hãy tải lại trang và gửi lại.');
        }
        if (($_POST['homepage'] ?? '') !== '') {
            http_response_code(422);
            throw new InvalidArgumentException('Biểu mẫu chưa hợp lệ. Hãy thử gửi lại.');
        }
        $limit = ['name'=>120, 'email'=>254, 'subject'=>160, 'message'=>4000];
        foreach ($limit as $key => $max) {
            $raw = $_POST[$key] ?? '';
            if (is_string($raw)) $values[$key] = mb_substr(trim($raw), 0, $max);
        }
        $names = ['name'=>'họ và tên','email'=>'email','subject'=>'chủ đề','message'=>'tin nhắn'];
        foreach ($limit as $key => $max) {
            try {
                $values[$key] = field($_POST, $key, $max, true);
            } catch (InvalidArgumentException $failure) {
                throw new InvalidArgumentException('Vui lòng kiểm tra mục ' . $names[$key] . ' (tối đa ' . $max . ' ký tự).');
            }
        }
        if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Vui lòng nhập địa chỉ email hợp lệ.');
        }

        $connection = db();
        $now = time();
        $bucket = hash('sha256', 'contact:' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $reference = 'TH-' . strtoupper(bin2hex(random_bytes(5)));
        immediate_write($connection, static function () use ($connection, $values, $bucket, $now, $reference): void {
            $connection->prepare('DELETE FROM contact_limits WHERE expires<=?')->execute([$now]);
            $query = $connection->prepare('SELECT attempts FROM contact_limits WHERE bucket=?');
            $query->execute([$bucket]);
            if ((int) $query->fetchColumn() >= 5) {
                http_response_code(429);
                throw new InvalidArgumentException('Bạn đã gửi nhiều tin nhắn trong giờ qua. Vui lòng thử lại sau.');
            }
            $connection->prepare('INSERT INTO contact_limits(bucket,attempts,expires) VALUES (?,1,?)
                ON CONFLICT(bucket) DO UPDATE SET attempts=attempts+1')->execute([$bucket, $now + 3600]);
            $connection->prepare('INSERT INTO contact_messages(reference,name,email,subject,message) VALUES (:reference,:name,:email,:subject,:message)')
                ->execute(['reference'=>$reference] + $values);
        });

        $_SESSION['contact_token'] = bin2hex(random_bytes(32));
        $_SESSION['contact_receipt'] = $reference;
        session_write_close();
        if (getenv('TTHUB_CONTACT_MAIL') === '1') {
            $body = "Mã: {$reference}\nTên: {$values['name']}\nEmail: {$values['email']}\nChủ đề: {$values['subject']}\n\n{$values['message']}";
            try {
                $sent = @mail(SUPPORT_EMAIL, 'TT-Hub.vn contact ' . $reference, $body, [
                    'From'=>SUPPORT_EMAIL, 'Reply-To'=>$values['email'], 'Content-Type'=>'text/plain; charset=UTF-8',
                ]);
                if (!$sent) error_log('Unable to send contact email notification for ' . $reference);
            } catch (Throwable $failure) {
                error_log('Unable to send contact email notification for ' . $reference);
            }
        }
        redirect(linkto('lien-he.php'));
    } catch (InvalidArgumentException $failure) {
        if (!in_array(http_response_code(), [403,422,429], true)) http_response_code(422);
        $error = $failure->getMessage();
    } catch (Throwable $failure) {
        error_log('Contact form: ' . $failure->getMessage());
        http_response_code(500);
        $error = 'Hệ thống chưa nhận được tin nhắn. Vui lòng thử lại hoặc liên hệ qua email.';
    }
}

page_header('Liên hệ · TT-Hub.vn', 'Gửi góp ý, báo lỗi hoặc yêu cầu hỗ trợ SignEmail và Cẩm nang Outlook, Gmail tới TT-Hub.vn.', '', linkto('lien-he.php'));
?>
<main class="shell info-page">
    <article class="info-card" aria-labelledby="info-title">
        <h1 id="info-title">Liên hệ TT-Hub.vn</h1>
        <p class="info-lead">Gửi câu hỏi về SignEmail, góp ý cho bài viết hoặc báo lỗi khi thiết lập chữ ký.</p>
        <p>Bạn cũng có thể liên hệ qua <a href="mailto:<?= h(SUPPORT_EMAIL) ?>"><?= h(SUPPORT_EMAIL) ?></a>.</p>
        <?php if ($receipt): ?>
        <p class="info-notice" role="status">Chúng tôi đã nhận được tin nhắn của bạn. Mã theo dõi <strong><?= h($receipt) ?></strong>.</p>
        <?php endif; ?>
        <?php if ($error): ?><p class="info-notice error" role="alert"><?= h($error) ?></p><?php endif; ?>
        <form method="post" action="<?= h(linkto('lien-he.php')) ?>" class="contact-form">
            <input type="hidden" name="csrf" value="<?= h($token) ?>">
            <div hidden aria-hidden="true"><label>Trang cá nhân<input name="homepage" tabindex="-1" autocomplete="off"></label></div>
            <div class="contact-fields">
                <label class="field"><span>Họ và tên</span><input name="name" value="<?= h($values['name']) ?>" autocomplete="name" maxlength="120" required></label>
                <label class="field"><span>Địa chỉ email để phản hồi</span><input type="email" name="email" value="<?= h($values['email']) ?>" autocomplete="email" maxlength="254" required></label>
            </div>
            <label class="field"><span>Chủ đề</span><input name="subject" value="<?= h($values['subject']) ?>" maxlength="160" required></label>
            <label class="field"><span>Tin nhắn</span><textarea name="message" rows="7" maxlength="4000" required><?= h($values['message']) ?></textarea></label>
            <p class="contact-hint">Hãy tránh gửi mật khẩu hoặc nội dung email riêng tư. Thông tin trên được dùng để xử lý phản hồi theo <a href="<?= h(linkto('chinh-sach-bao-mat.php')) ?>">Chính sách bảo mật</a>.</p>
            <div><button class="button" type="submit">Gửi tin nhắn</button></div>
        </form>
    </article>
</main>
<?php page_footer(); ?>
