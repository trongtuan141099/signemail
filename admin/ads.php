<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_admin();
$db = db();
$placements = placements();
$error = '';
$edited = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    $upload = '';
    try {
        $placement = field($_POST, 'placement', 15, true);
        if (!isset($placements[$placement])) throw new InvalidArgumentException('Vị trí quảng cáo không hợp lệ.');
        $edited = $placement;
        $mode = field($_POST, 'mode', 15, true);
        if (!in_array($mode, ['banner','adsense'], true)) throw new InvalidArgumentException('Chọn loại quảng cáo hợp lệ.');
        $enabled = (int) isset($_POST['enabled']);
        $target = field($_POST, 'target', 1000);
        $code = field($_POST, 'code', 40000);
        $alt = field($_POST, 'alt', 180);
        $headline = field($_POST, 'headline', 100);
        $description = field($_POST, 'description', 200);
        $buttonText = field($_POST, 'button_text', 30);
        $tone = field($_POST, 'tone', 12) ?: 'forest';
        if (!in_array($tone, ['forest','sun','ink'], true)) throw new InvalidArgumentException('Chọn tông màu banner hợp lệ.');
        if ($target !== '' && !web_url($target)) throw new InvalidArgumentException('URL affiliate phải là một liên kết HTTP hoặc HTTPS.');
        if ($mode === 'adsense' && $code !== '') display_ad_parts($code);
        $upload = upload_image('image');
        $db->exec('BEGIN IMMEDIATE');
        try {
            $current = $db->prepare('SELECT image FROM ads WHERE placement=?');
            $current->execute([$placement]);
            $old = (string) $current->fetchColumn();
            $image = $upload ?: (isset($_POST['remove_image']) ? '' : $old);
            if ($enabled && (($mode === 'adsense' && $code === '') || ($mode === 'banner' && ($image === '' || $target === '')))) {
                throw new InvalidArgumentException('Thêm mã AdSense hoặc chọn ảnh và URL affiliate trước khi bật quảng cáo.');
            }
            $db->prepare('UPDATE ads SET mode=?,enabled=?,code=?,image=?,target=?,alt=?,headline=?,description=?,button_text=?,tone=? WHERE placement=?')
                ->execute([$mode,$enabled,$code,$image,$target,$alt,$headline,$description,$buttonText,$tone,$placement]);
            $db->exec('COMMIT');
        } catch (Throwable $failure) {
            $db->exec('ROLLBACK');
            throw $failure;
        }
        if ($old && $old !== $image) remove_upload($old);
        flash('Đã lưu ' . mb_strtolower($placements[$placement]) . '.');
        redirect(linkto('admin/ads.php#' . $placement));
    } catch (Throwable $failure) {
        if ($upload) remove_upload($upload);
        $error = admin_error($failure);
    }
}

$items = $db->query('SELECT * FROM ads')->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
admin_header('Quảng cáo', 'ads.php', $user);
?>
<div class="admin-heading"><div><h1>Quảng cáo và affiliate</h1><p>Quản lý bốn vị trí quảng cáo và theo dõi lượt click theo vị trí.</p></div></div>
<?php if ($error && !$edited): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?>
<div class="ads-grid">
<?php foreach ($placements as $slot => $label):
    $stored = $items[$slot];
    $item = $slot === $edited ? array_replace($stored, ['enabled' => isset($_POST['enabled'])], $_POST) : $stored;
?>
<section class="admin-card admin-stack" id="<?= h($slot) ?>">
    <div class="admin-card-heading"><h2><?= h($label) ?></h2><span class="pill"><?= number_format((int) $stored['clicks'], 0, ',', '.') ?> lượt click</span></div>
    <?php if ($slot === $edited): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="admin-stack" data-ad-form>
        <?php csrf_input(); ?><input type="hidden" name="placement" value="<?= h($slot) ?>">
        <label><input type="checkbox" name="enabled" <?= !empty($item['enabled']) ? 'checked' : '' ?>> Bật quảng cáo</label>
        <label class="field"><span>Loại quảng cáo</span><select name="mode"><option value="banner">Banner affiliate</option><option value="adsense" <?= $item['mode'] === 'adsense' ? 'selected' : '' ?>>Google AdSense</option></select></label>
        <div class="admin-stack" data-ad-mode="banner">
            <label class="field"><span>Ảnh banner (tối đa 2 MB)</span><input type="file" name="image" accept="image/png,image/jpeg,image/gif,image/webp"></label>
            <?php if ($stored['image']): ?><div class="cover-preview"><img src="<?= h(linkto('uploads/' . $stored['image'])) ?>" alt="Banner hiện tại"><label><input type="checkbox" name="remove_image"> Gỡ ảnh hiện tại</label></div><?php endif; ?>
            <?php admin_input('target','URL affiliate',$item,1000,'url'); admin_input('alt','Văn bản thay thế ảnh',$item,180);
            admin_input('headline','Tiêu đề banner',$item,100);
            admin_input('description','Mô tả ngắn',$item,200);
            admin_input('button_text','Nhãn nút CTA',$item,30); ?>
            <label class="field"><span>Tông màu banner</span><select name="tone">
                <?php foreach (['forest'=>'Xanh rừng','sun'=>'Vàng ấm','ink'=>'Xanh đêm'] as $key => $label): ?><option value="<?= h($key) ?>" <?= $item['tone'] === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
            </select></label>
        </div>
        <label class="field" data-ad-mode="adsense"><span>Mã Google AdSense</span><textarea class="mono" name="code" rows="6" maxlength="40000"><?= h(is_string($item['code'] ?? null) ? $item['code'] : '') ?></textarea></label>
        <div><button class="button button-small">Lưu vị trí</button></div>
    </form>
</section>
<?php endforeach; ?>
</div>
<?php admin_footer(); ?>
