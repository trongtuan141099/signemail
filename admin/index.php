<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_admin();
$db = db();
$stats = [
    ['Mẫu chữ ký', (int) $db->query('SELECT COUNT(*) FROM templates')->fetchColumn(), 'templates.php'],
    ['Bài đã xuất bản', (int) $db->query("SELECT COUNT(*) FROM posts WHERE status='published'")->fetchColumn(), 'posts.php'],
    ['Lượt click affiliate', (int) $db->query('SELECT COALESCE(SUM(clicks),0) FROM ads')->fetchColumn(), 'ads.php'],
];
admin_header('Tổng quan', 'index.php', $user);
?>
<div class="admin-heading"><div><h1>Tổng quan</h1><p>Đăng nhập với tài khoản <?= h($user['username']) ?>.</p></div><a class="button button-secondary" href="<?= h(linkto()) ?>">Xem website</a></div>
<div class="admin-cards">
    <?php foreach ($stats as [$label,$count,$file]): ?>
    <a class="admin-card stat-card" href="<?= h(linkto('admin/' . $file)) ?>"><strong><?= number_format($count, 0, ',', '.') ?></strong><span><?= h($label) ?></span></a>
    <?php endforeach; ?>
</div>
<?php admin_footer(); ?>
