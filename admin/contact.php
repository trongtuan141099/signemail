<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_admin();
$connection = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    $id = get_id($_POST['id'] ?? null);
    $action = $_POST['action'] ?? '';
    if ($action === 'read') {
        $connection->prepare('UPDATE contact_messages SET read_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$id]);
        flash('Đã đánh dấu tin nhắn là đã đọc.');
    } elseif ($action === 'delete') {
        $connection->prepare('DELETE FROM contact_messages WHERE id=?')->execute([$id]);
        flash('Đã xóa tin nhắn liên hệ.');
    }
    redirect(linkto('admin/contact.php'));
}

$page = max(1, (int) filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT));
$pages = max(1, (int) ceil((int) $connection->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn() / 20));
$page = min($page, $pages);
$query = $connection->prepare('SELECT * FROM contact_messages ORDER BY id DESC LIMIT 20 OFFSET ?');
$query->bindValue(1, ($page - 1) * 20, PDO::PARAM_INT);
$query->execute();
$messages = $query->fetchAll();

admin_header('Tin nhắn liên hệ', 'contact.php', $user);
?>
<header class="admin-heading"><div><h1>Tin nhắn liên hệ</h1><p>Hộp thư tiếp nhận từ biểu mẫu TT-Hub.vn.</p></div></header>
<div class="contact-inbox">
    <?php foreach ($messages as $item): ?>
    <article class="admin-card admin-stack">
        <?php $date = published_date($item['created_at']); ?>
        <header>
            <h2><?= h($item['subject']) ?> <small class="pill"><?= $item['read_at'] ? 'Đã đọc' : 'Mới' ?></small></h2>
            <p><?= h($item['name']) ?> · <a class="text-link" href="mailto:<?= h($item['email']) ?>"><?= h($item['email']) ?></a></p>
            <small><?= h($item['reference']) ?> · <time datetime="<?= h($date->format(DATE_ATOM)) ?>"><?= h($date->format('d/m/Y H:i')) ?></time></small>
        </header>
        <p class="contact-message"><?= nl2br(h($item['message'])) ?></p>
        <div class="row-actions">
            <?php if (!$item['read_at']): ?>
            <form method="post"><?php csrf_input(); ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="button button-secondary button-small" name="action" value="read">Đánh dấu đã đọc</button></form>
            <?php endif; ?>
            <form method="post"><?php csrf_input(); ?><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button class="text-link" name="action" value="delete" data-confirm="Xóa tin nhắn này?">Xóa tin nhắn</button></form>
        </div>
    </article>
    <?php endforeach; ?>
    <?php if (!$messages): ?><p class="empty">Chưa có tin nhắn liên hệ.</p><?php endif; ?>
    <?php if ($pages > 1): ?><nav class="row-actions" aria-label="Trang tin nhắn">
        <?php if ($page > 1): ?><a class="text-link" href="?page=<?= $page - 1 ?>">Trang trước</a><?php endif; ?>
        <span>Trang <?= $page ?> / <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="text-link" href="?page=<?= $page + 1 ?>">Trang sau</a><?php endif; ?>
    </nav><?php endif; ?>
</div>
<?php admin_footer(); ?>
