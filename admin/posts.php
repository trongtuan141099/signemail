<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_admin();
$db = db();
$statuses = ['draft' => 'Bản nháp', 'published' => 'Đã xuất bản', 'hidden' => 'Đã ẩn'];

function find_post(PDO $db, int $id): array
{
    $query = $db->prepare('SELECT * FROM posts WHERE id=?');
    $query->execute([$id]);
    $row = $query->fetch();
    if (!$row) throw new InvalidArgumentException('Bài viết không tồn tại.');
    return $row;
}

function unique_slug(PDO $db, string $base, int $id): string
{
    $query = $db->prepare('SELECT 1 FROM posts WHERE slug=? AND id<>?');
    $slug = $base;
    for ($suffix = 2; ; $suffix++) {
        $query->execute([$slug, $id]);
        if (!$query->fetchColumn()) return $slug;
        $slug = $base . '-' . $suffix;
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    $action = $_POST['action'] ?? '';
    $upload = '';
    try {
        $id = ($_POST['id'] ?? '') !== '' ? get_id($_POST['id']) : 0;
        if ($action === 'save') {
            $title = field($_POST, 'title', 180, true);
            $requestedSlug = field($_POST, 'slug', 135);
            $status = field($_POST, 'status', 20, true);
            if (!isset($statuses[$status])) throw new InvalidArgumentException('Chọn trạng thái hợp lệ.');
            $content = clean_fragment(field($_POST, 'content', 160000, true), false);
            if (trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '' && !str_contains($content, '<img ')) {
                throw new InvalidArgumentException('Nhập nội dung bài viết.');
            }
            $category = field($_POST, 'category', 40);
            if ($category !== '' && !in_array($category, blog_categories(), true)) throw new InvalidArgumentException('Chọn chủ đề bài viết hợp lệ.');
            $data = [
                'category' => $category,
                'title' => $title,
                'excerpt' => field($_POST, 'excerpt', 280),
                'content' => $content,
                'meta_title' => field($_POST, 'meta_title', 120),
                'meta_description' => field($_POST, 'meta_description', 250),
                'status' => $status,
            ];
            $upload = upload_image('cover');
            $db->exec('BEGIN IMMEDIATE');
            try {
                $old = $id ? find_post($db, $id) : [];
                $data['slug'] = unique_slug($db, slugify($requestedSlug ?: $title), $id);
                $data['cover'] = $upload ?: (isset($_POST['remove_cover']) ? '' : ($old['cover'] ?? ''));
                $data['published_at'] = $old['published_at'] ?? ($status === 'published' ? gmdate('Y-m-d H:i:s') : null);
                if ($id) {
                    $db->prepare('UPDATE posts SET title=:title,slug=:slug,excerpt=:excerpt,content=:content,cover=:cover,
                        meta_title=:meta_title,meta_description=:meta_description,category=:category,status=:status,published_at=:published_at,updated_at=CURRENT_TIMESTAMP WHERE id=:id')
                        ->execute($data + ['id' => $id]);
                } else {
                    $db->prepare('INSERT INTO posts(title,slug,excerpt,content,cover,meta_title,meta_description,category,status,published_at)
                        VALUES(:title,:slug,:excerpt,:content,:cover,:meta_title,:meta_description,:category,:status,:published_at)')->execute($data);
                }
                $db->exec('COMMIT');
            } catch (Throwable $failure) {
                $db->exec('ROLLBACK');
                throw $failure;
            }
            if (!empty($old['cover']) && $old['cover'] !== $data['cover']) remove_upload($old['cover']);
            flash('Đã lưu bài viết.');
        } elseif ($id && $action === 'hide') {
            $db->prepare("UPDATE posts SET status='hidden',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
            flash('Đã ẩn bài viết.');
        } elseif ($id && $action === 'delete') {
            $cover = immediate_write($db, static function () use ($db, $id): string {
                $post = find_post($db, $id);
                $db->prepare('DELETE FROM posts WHERE id=?')->execute([$id]);
                return $post['cover'];
            });
            if (is_string($cover)) remove_upload($cover);
            flash('Đã xóa bài viết.');
        } else {
            throw new InvalidArgumentException('Thao tác không hợp lệ.');
        }
        redirect(linkto('admin/posts.php'));
    } catch (Throwable $failure) {
        if ($upload) remove_upload($upload);
        $error = admin_error($failure);
        if ($action !== 'save') {
            flash($error, true);
            redirect(linkto('admin/posts.php'));
        }
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    try {
        $edit = $_GET['edit'] === 'new'
            ? ['id'=>'','title'=>'','slug'=>'','excerpt'=>'','content'=>'','cover'=>'','meta_title'=>'','meta_description'=>'','status'=>'draft']
            : find_post($db, get_id($_GET['edit']));
    } catch (Throwable $failure) {
        flash(admin_error($failure), true);
        redirect(linkto('admin/posts.php'));
    }
}
if ($error !== '') $edit = array_replace($edit ?? [], $_POST);
$rows = $db->query('SELECT id,title,slug,status,updated_at FROM posts ORDER BY updated_at DESC,id DESC')->fetchAll();
admin_header('Bài viết blog', 'posts.php', $user, (bool) $edit);
?>
<div class="admin-heading"><div><h1>Bài viết blog</h1><p>Soạn thảo và xuất bản hướng dẫn Gmail, Outlook.</p></div><a class="button" href="?edit=new#post-editor">Viết bài mới</a></div>
<?php if ($edit): ?>
<section class="admin-card admin-stack" id="post-editor">
    <div class="admin-card-heading"><h2><?= empty($edit['id']) ? 'Viết bài mới' : 'Sửa bài viết' ?></h2><a class="text-link" href="<?= h(linkto('admin/posts.php')) ?>">Đóng biểu mẫu</a></div>
    <?php if ($error): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="admin-stack" id="post-form">
        <?php csrf_input(); ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= h(is_scalar($edit['id'] ?? null) ? (string) $edit['id'] : '') ?>">
        <div class="admin-fields">
            <?php admin_input('title','Tiêu đề bài viết',$edit,180,'text',true); ?>
            <?php admin_input('slug','Slug (tự tạo nếu để trống)',$edit,135); ?>
            <label class="field"><span>Chủ đề</span><select name="category"><option value="">Hướng dẫn chung</option>
                <?php foreach (blog_categories() as $category): ?><option <?= ($edit['category'] ?? '') === $category ? 'selected' : '' ?>><?= h($category) ?></option><?php endforeach; ?>
            </select></label>
            <label class="field"><span>Trạng thái</span><select name="status">
                <?php foreach ($statuses as $key => $label): ?><option value="<?= h($key) ?>" <?= ($edit['status'] ?? 'draft') === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
            </select></label>
            <label class="field"><span>Ảnh đại diện (tối đa 2 MB)</span><input type="file" name="cover" accept="image/png,image/jpeg,image/gif,image/webp"></label>
        </div>
        <?php if (!empty($edit['cover']) && is_string($edit['cover'])): ?>
        <div class="cover-preview"><img src="<?= h(linkto('uploads/' . $edit['cover'])) ?>" alt="Ảnh đại diện hiện tại"><label><input type="checkbox" name="remove_cover"> Gỡ ảnh hiện tại</label></div>
        <?php endif; ?>
        <label class="field"><span>Tóm tắt bài viết</span><textarea name="excerpt" rows="2" maxlength="280"><?= h(is_string($edit['excerpt'] ?? null) ? $edit['excerpt'] : '') ?></textarea></label>
        <div class="field">
            <label for="post-content">Nội dung bài viết</label>
            <p>Kéo ảnh vào bài hoặc dán nội dung từ Google Docs bằng Ctrl+V (⌘V trên Mac). Ảnh PNG, JPEG, GIF và WebP tối đa 2 MB mỗi ảnh.</p>
            <textarea id="post-content" name="content" rows="14" maxlength="160000" data-base="<?= h(linkto()) ?>" data-upload="<?= h(linkto('admin/post-image.php')) ?>"><?= h(is_string($edit['content'] ?? null) ? $edit['content'] : '') ?></textarea>
            <p id="editor-status" role="status" aria-live="polite"></p>
        </div>
        <div class="admin-fields"><?php admin_input('meta_title','Meta Title',$edit,120); ?></div>
        <label class="field"><span>Meta Description</span><textarea name="meta_description" rows="2" maxlength="250"><?= h(is_string($edit['meta_description'] ?? null) ? $edit['meta_description'] : '') ?></textarea></label>
        <div><button class="button">Lưu bài viết</button></div>
    </form>
</section>
<?php endif; ?>
<section class="admin-card admin-overflow">
    <table class="admin-table"><thead><tr><th scope="col">Tiêu đề</th><th scope="col">Trạng thái</th><th scope="col">Cập nhật (UTC)</th><th scope="col">Thao tác</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?>
    <tr><th scope="row"><?= h($row['title']) ?><small><?= h($row['slug']) ?></small></th><td><span class="pill <?= $row['status'] === 'published' ? 'live' : '' ?>"><?= h($statuses[$row['status']]) ?></span></td><td><?= h(substr($row['updated_at'], 0, 10)) ?></td>
        <td><form method="post" class="row-actions"><?php csrf_input(); ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            <a class="text-link" href="?edit=<?= (int) $row['id'] ?>#post-editor">Sửa</a>
            <?php if ($row['status'] === 'published'): ?><a class="text-link" href="<?= h(post_url($row['slug'])) ?>">Xem</a><?php endif; ?>
            <?php if ($row['status'] !== 'hidden'): ?><button class="text-link" name="action" value="hide">Ẩn</button><?php endif; ?>
            <button class="text-link danger" name="action" value="delete" data-confirm="Xóa bài viết này?">Xóa</button>
        </form></td></tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="4">Chưa có bài viết.</td></tr><?php endif; ?>
    </tbody></table>
</section>
<?php admin_footer(); ?>
