<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_admin();
$db = db();
$select = static function (int $id) use ($db): array {
    $query = $db->prepare('SELECT * FROM templates WHERE id=?');
    $query->execute([$id]);
    $template = $query->fetch();
    if (!$template) throw new InvalidArgumentException('Mẫu chữ ký không tồn tại.');
    return $template;
};

if (($_GET['action'] ?? '') === 'export') {
    $templates = $db->query('SELECT name,style,role,html,animated,visible,accent,motion FROM templates ORDER BY id')->fetchAll();
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="signemail-templates.json"');
    echo json_encode(array_map('template_record', $templates), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'import') {
            $file = $_FILES['templates'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 12 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) {
                throw new InvalidArgumentException('Chọn một tệp JSON không quá 12 MB và kiểm tra giới hạn tải lên của máy chủ.');
            }
            $json = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($file['tmp_name']));
            $items = json_decode($json, false, 64, JSON_THROW_ON_ERROR);
            if (!is_array($items) || count($items) < 1 || count($items) > 1000) {
                throw new InvalidArgumentException('Tệp phải chứa một mảng JSON có từ 1 đến 1.000 mẫu.');
            }
            $clean = [];
            foreach ($items as $index => $item) {
                try {
                    if (!$item instanceof stdClass) throw new InvalidArgumentException('Mỗi mẫu phải là một đối tượng JSON.');
                    $clean[] = template_data((array) $item);
                } catch (InvalidArgumentException $error) {
                    throw new InvalidArgumentException('Mẫu ' . ($index + 1) . ' · ' . $error->getMessage());
                }
            }
            $db->exec('BEGIN IMMEDIATE');
            try {
                $existing = $db->query('SELECT name,style,role,html,animated,visible,accent,motion FROM templates')->fetchAll();
                $key = static fn(array $data): string => hash('sha256', serialize(array_values(template_record($data))));
                $seen = array_fill_keys(array_map($key, $existing), true);
                $imported = 0;
                foreach ($clean as $data) {
                    if (isset($seen[$key($data)])) continue;
                    save_template($db, $data);
                    $seen[$key($data)] = true;
                    $imported++;
                }
                $db->exec('COMMIT');
            } catch (Throwable $error) {
                $db->exec('ROLLBACK');
                throw $error;
            }
            flash('Đã nhập ' . $imported . ' mẫu. Bỏ qua ' . (count($clean) - $imported) . ' mẫu trùng.');
        } elseif ($action === 'save') {
            $id = ($_POST['id'] ?? '') !== '' ? get_id($_POST['id']) : 0;
            if ($id) $select($id);
            save_template($db, template_data($_POST), $id);
            flash('Đã lưu mẫu chữ ký.');
        } else {
            $id = get_id($_POST['id'] ?? null);
            $template = $select($id);
            switch ($action) {
                case 'duplicate':
                    $template['name'] = mb_substr($template['name'], 0, 108) . ' (bản sao)';
                    $template['visible'] = 0;
                    unset($template['id']);
                    save_template($db, $template);
                    flash('Đã nhân bản mẫu ở trạng thái ẩn.');
                    break;
                case 'toggle':
                    $db->prepare('UPDATE templates SET visible=1-visible WHERE id=?')->execute([$id]);
                    flash('Đã cập nhật trạng thái hiển thị.');
                    break;
                case 'delete':
                    $db->prepare('DELETE FROM templates WHERE id=?')->execute([$id]);
                    flash('Đã xóa mẫu.');
                    break;
                default:
                    throw new InvalidArgumentException('Thao tác không hợp lệ.');
            }
        }
        redirect(linkto('admin/templates.php'));
    } catch (Throwable $failure) {
        $error = admin_error($failure);
        if ($action !== 'save') {
            flash($error, true);
            redirect(linkto('admin/templates.php'));
        }
    }
}

$edit = null;
$sample = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;font-family:{{font_family}};font-size:{{font_size}}px"><tr><td style="color:{{primary_color}}"><strong>{{full_name}}</strong></td></tr><tr data-if="job_title"><td>{{job_title}}</td></tr></table>';
if (isset($_GET['edit'])) {
    try {
        $edit = $_GET['edit'] === 'new'
            ? ['id'=>'','name'=>'','style'=>'','role'=>'','html'=>$sample,'animated'=>0,'visible'=>1]
            : $select(get_id($_GET['edit']));
    } catch (Throwable $failure) {
        flash(admin_error($failure), true);
        redirect(linkto('admin/templates.php'));
    }
}
if ($error !== '') $edit = $_POST;

$search = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 120) : '';
$like = '%' . $search . '%';
$count = $db->prepare('SELECT count(*) FROM templates WHERE name LIKE ? OR style LIKE ? OR role LIKE ?');
$count->execute([$like, $like, $like]);
$pages = max(1, (int) ceil($count->fetchColumn() / 25));
$page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$list = $db->prepare('SELECT id,name,style,role,animated,visible FROM templates WHERE name LIKE ? OR style LIKE ? OR role LIKE ? ORDER BY id DESC LIMIT 25 OFFSET ?');
foreach ([1=>$like,2=>$like,3=>$like] as $index => $value) $list->bindValue($index, $value);
$list->bindValue(4, ($page - 1) * 25, PDO::PARAM_INT);
$list->execute();
$rows = $list->fetchAll();
admin_header('Kho mẫu chữ ký', 'templates.php', $user);
?>
<div class="admin-heading">
    <div><h1>Kho mẫu chữ ký</h1><p>Quản lý nội dung và trạng thái hiển thị của từng mẫu.</p></div>
    <a class="button" href="?edit=new#template-editor">Thêm mẫu</a>
</div>
<?php if ($edit): ?>
<section class="admin-card admin-stack" id="template-editor">
    <div class="admin-card-heading"><h2><?= empty($edit['id']) ? 'Thêm mẫu chữ ký' : 'Sửa mẫu chữ ký' ?></h2><a class="text-link" href="<?= h(linkto('admin/templates.php')) ?>">Đóng biểu mẫu</a></div>
    <?php if ($error): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?>
    <form method="post" class="admin-stack">
        <?php csrf_input(); ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= h(is_scalar($edit['id'] ?? null) ? (string) $edit['id'] : '') ?>">
        <div class="admin-fields">
            <?php admin_input('name','Tên mẫu',$edit,120,'text',true); ?>
            <?php admin_input('style','Phong cách',$edit,70,'text',true); ?>
            <?php admin_input('role','Ngành nghề hoặc vị trí',$edit,90,'text',true); ?>
            <label class="field"><span>Hiệu ứng</span><select name="animated"><option value="0">Tĩnh</option><option value="1" <?= !empty($edit['animated']) ? 'selected' : '' ?>>Có hiệu ứng động</option></select></label>
            <label class="field"><span>Màu gợi ý</span><input type="color" name="accent" value="<?= h(is_string($edit['accent'] ?? null) ? $edit['accent'] : '#18634f') ?>"></label>
            <label class="field"><span>GIF của mẫu động</span><select name="motion">
                <?php foreach (motion_assets() as $key => $label): ?><option value="<?= h($key) ?>" <?= ($edit['motion'] ?? 'dots') === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
            </select></label>
            <label class="field"><span>Hiển thị trên website</span><select name="visible"><option value="1">Hiện</option><option value="0" <?= empty($edit['visible']) ? 'selected' : '' ?>>Ẩn</option></select></label>
        </div>
        <label class="field"><span>HTML khung chữ ký</span><textarea class="mono" name="html" rows="9" maxlength="25000" required><?= h(is_string($edit['html'] ?? null) ? $edit['html'] : '') ?></textarea></label>
        <details><summary>Biến thay thế</summary>
            <p><code>{{full_name}} {{job_title}} {{company}} {{phone}} {{email}} {{website}} {{address}} {{avatar_url}} {{primary_color}} {{social_icons}} {{banner_url}}</code></p>
            <p><code>{{font_family}} {{font_size}} {{avatar_radius}} {{phone_href}} {{cta_label}} {{cta_url}} {{banner_link}} {{motion_url}} {{logo_url}} {{logo_width}} {{contrast_color}} {{name_size}}</code></p>
            <p>Đặt <code>data-if="avatar_url"</code> trên ô chứa avatar để ô tự ẩn khi thiếu ảnh. Đặt <code>{{social_icons}}</code> trong một ô bảng.</p>
        </details>
        <div><button class="button">Lưu mẫu</button></div>
    </form>
</section>
<?php endif; ?>
<section class="admin-card admin-stack">
    <div class="admin-card-heading"><h2>Nhập và xuất JSON</h2><a class="button button-secondary button-small" href="?action=export">Xuất JSON</a></div>
    <form method="post" enctype="multipart/form-data" class="inline-form">
        <?php csrf_input(); ?><input type="hidden" name="action" value="import">
        <label class="field"><span>Tệp JSON (tối đa 1.000 mẫu)</span><input type="file" name="templates" accept=".json,application/json" required></label>
        <button class="button button-small">Nhập JSON</button>
    </form>
</section>
<section class="admin-card admin-stack">
    <form class="inline-form">
        <label class="field"><span>Tìm mẫu</span><input type="search" name="q" maxlength="120" value="<?= h($search) ?>" placeholder="Tên mẫu, phong cách hoặc vị trí"></label>
        <button class="button button-secondary button-small">Tìm</button>
    </form>
    <div class="admin-overflow"><table class="admin-table">
        <thead><tr><th scope="col">Tên mẫu</th><th scope="col">Phong cách</th><th scope="col">Vị trí</th><th scope="col">Trạng thái</th><th scope="col">Thao tác</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <th scope="row"><?= h($row['name']) ?><?= $row['animated'] ? ' · GIF' : '' ?></th><td><?= h($row['style']) ?></td><td><?= h($row['role']) ?></td>
            <td><span class="pill <?= $row['visible'] ? 'live' : '' ?>"><?= $row['visible'] ? 'Đang hiện' : 'Đang ẩn' ?></span></td>
            <td><form method="post" class="row-actions"><?php csrf_input(); ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <a class="text-link" href="?edit=<?= (int) $row['id'] ?>#template-editor">Sửa</a>
                <button class="text-link" name="action" value="duplicate">Nhân bản</button>
                <button class="text-link" name="action" value="toggle"><?= $row['visible'] ? 'Ẩn' : 'Hiện' ?></button>
                <button class="text-link danger" name="action" value="delete" data-confirm="Xóa mẫu chữ ký này?">Xóa</button>
            </form></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5">Không có mẫu phù hợp.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php if ($pages > 1): ?>
        <nav class="row-actions" aria-label="Phân trang kho mẫu">
            <?php if ($page > 1): ?><a class="text-link" href="?<?= h(http_build_query(['q'=>$search,'page'=>$page-1])) ?>">Trang trước</a><?php endif; ?>
            <span>Trang <?= $page ?> / <?= $pages ?></span>
            <?php if ($page < $pages): ?><a class="text-link" href="?<?= h(http_build_query(['q'=>$search,'page'=>$page+1])) ?>">Trang sau</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
