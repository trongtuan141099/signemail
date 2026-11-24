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
$days = ($_GET['days'] ?? '') === '14' ? 14 : 30;
$start = activity_start($days);
$summary = $db->prepare("SELECT SUM(CASE WHEN day=? THEN total END), SUM(CASE WHEN day=? THEN total END),
    SUM(CASE WHEN day>=? THEN total END), SUM(total) FROM activity_counts WHERE event='view'");
$summary->execute([activity_start(1),activity_start(2),activity_start(7)]);
$totals = $summary->fetch(PDO::FETCH_NUM);
$views = $db->prepare("SELECT day,SUM(total) FROM activity_counts WHERE event='view' AND day>=? GROUP BY day");
$views->execute([$start]);
$dailyViews = $views->fetchAll(PDO::FETCH_KEY_PAIR);
$visitors = $db->prepare('SELECT day,visitors FROM traffic_days WHERE day>=?');
$visitors->execute([$start]);
$dailyVisitors = $visitors->fetchAll(PDO::FETCH_KEY_PAIR);
$firstTracked = $db->query('SELECT MIN(day) FROM traffic_days')->fetchColumn();
$series = [];
for ($offset = $days; $offset >= 1; $offset--) {
    $day = activity_start($offset);
    $series[] = [$day, (int) ($dailyViews[$day] ?? 0), $firstTracked && $day >= $firstTracked ? (int) ($dailyVisitors[$day] ?? 0) : null];
}
$activity = $db->prepare("SELECT a.entity,p.path,SUM(a.total) AS total FROM activity_counts a LEFT JOIN traffic_paths p ON p.entity=a.entity WHERE event='view' AND day>=? GROUP BY a.entity,p.path ORDER BY total DESC,a.entity LIMIT 10");
$activity->execute([$start]);
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
    <div class="admin-card-heading"><h2>Thống kê lượt truy cập (Traffic)</h2></div>
    <div class="traffic-cards">
    <?php foreach (['Hôm nay','Hôm qua','7 ngày gần nhất','Toàn thời gian'] as $i=>$label): ?>
        <div class="admin-card stat-card"><span><?= h($label) ?></span><strong><?= number_format((int)$totals[$i],0,',','.') ?></strong><small>Lượt xem trang</small></div>
    <?php endforeach; ?>
    </div>
    <form method="get" action="#activity"><label>Khoảng thống kê <select name="days">
        <?php foreach ([14,30] as $range): ?><option value="<?= $range ?>" <?= $days === $range ? 'selected' : '' ?>><?= $range ?> ngày gần nhất</option><?php endforeach; ?>
    </select></label> <button class="button button-secondary">Áp dụng</button></form>
    <div class="traffic-chart"><canvas id="traffic-chart" aria-label="Lượt xem và khách duy nhất mỗi ngày" role="img"></canvas></div>
    <script type="application/json" id="traffic-data"><?= json_encode($series,JSON_THROW_ON_ERROR) ?></script>
    <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js"></script>
    <script defer src="<?= h(asset_url('admin/traffic.js')) ?>"></script>
    <details><summary>Số liệu theo ngày (giờ Việt Nam)</summary><table class="admin-table"><thead><tr><th>Ngày</th><th>Lượt xem</th><th>Khách duy nhất</th></tr></thead><tbody>
    <?php foreach (array_reverse($series) as [$day,$pv,$uv]): ?><tr><td><?= h($day) ?></td><td><?= $pv ?></td><td><?= $uv ?? 'Chưa đo' ?></td></tr><?php endforeach; ?></tbody></table></details>
    <div class="admin-fields">
        <div class="admin-overflow">
            <h3>Top trang/bài viết được xem nhiều nhất · <?= $days ?> ngày</h3>
            <table class="admin-table"><thead><tr><th scope="col">Trang</th><th scope="col">Lượt xem</th></tr></thead><tbody>
            <?php foreach ($pages as $page): ?>
            <tr><td><?= h($pageNames[$page['entity']] ?? $postTitles[substr($page['entity'],5)] ?? $page['entity']) ?><br><small><?= h($page['path'] ?? ($page['entity']==='editor' ? linkto() : ($page['entity']==='blog' ? linkto('blog.php') : post_url(substr($page['entity'],5))))) ?></small></td><td><?= number_format((int) $page['total'],0,',','.') ?></td></tr>
            <?php endforeach; if (!$pages): ?><tr><td colspan="2">Chưa có lượt xem.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
        <div class="admin-overflow">
            <h3>Mẫu chữ ký được chọn trong 30 ngày</h3>
            <table class="admin-table"><thead><tr><th scope="col">Mẫu thường được chọn</th><th scope="col">Lượt chọn</th></tr></thead><tbody>
            <?php foreach ($popular as $model): ?>
            <tr><td><?= h($model['name']) ?></td><td><?= number_format((int) $model['total'],0,',','.') ?></td></tr>
            <?php endforeach; if (!$popular): ?><tr><td colspan="2">Chưa có lượt chọn.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </div>
</section>
<?php admin_footer(); ?>
