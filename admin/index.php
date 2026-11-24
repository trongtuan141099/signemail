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
$activity = $db->prepare("SELECT entity,SUM(total) AS total FROM activity_counts WHERE event='view' AND day>=? GROUP BY entity ORDER BY total DESC,entity");
$activity->execute([activity_start(30)]);
$pages = $activity->fetchAll();
$popular = popular_templates(30, 10);
$postTitles = $db->query('SELECT slug,title FROM posts')->fetchAll(PDO::FETCH_KEY_PAIR);
$pageNames = ['editor'=>'Trình tạo chữ ký', 'blog'=>'Cẩm nang'];
admin_header('Tổng quan', 'index.php', $user);
?>
<div class="admin-heading"><div><h1>Tổng quan</h1><p>Đăng nhập với tài khoản <?= h($user['username']) ?>.</p></div><a class="button button-secondary" href="<?= h(linkto()) ?>">Xem website</a></div>
<div class="admin-cards">
    <?php foreach ($stats as [$label,$count,$file]): ?>
    <a class="admin-card stat-card" href="<?= h(linkto('admin/' . $file)) ?>"><strong><?= number_format($count, 0, ',', '.') ?></strong><span><?= h($label) ?></span></a>
    <?php endforeach; ?>
</div>

<section id="activity" class="admin-card admin-stack activity-report">
    <div class="admin-card-heading"><h2>Hoạt động trong 30 ngày</h2><span class="pill"><?= number_format(array_sum(array_column($pages,'total')),0,',','.') ?> lượt xem trang</span></div>
    <div class="admin-fields">
        <div class="admin-overflow">
            <table class="admin-table"><thead><tr><th scope="col">Trang</th><th scope="col">Lượt xem</th></tr></thead><tbody>
            <?php foreach ($pages as $page): ?>
            <tr><td><?= h($pageNames[$page['entity']] ?? $postTitles[substr($page['entity'],5)] ?? $page['entity']) ?></td><td><?= number_format((int) $page['total'],0,',','.') ?></td></tr>
            <?php endforeach; if (!$pages): ?><tr><td colspan="2">Chưa có lượt xem.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
        <div class="admin-overflow">
            <table class="admin-table"><thead><tr><th scope="col">Mẫu thường được chọn</th><th scope="col">Lượt chọn</th></tr></thead><tbody>
            <?php foreach ($popular as $model): ?>
            <tr><td><?= h($model['name']) ?></td><td><?= number_format((int) $model['total'],0,',','.') ?></td></tr>
            <?php endforeach; if (!$popular): ?><tr><td colspan="2">Chưa có lượt chọn.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </div>
</section>
<?php admin_footer(); ?>
