<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_admin();
$db = db();
$placements = placements();
$error = '';
$edited = '';
$effects = ['fade'=>'Chuyển mờ','slide'=>'Trượt ngang','glow'=>'Nhấn sáng nhẹ','manual'=>'Chuyển thủ công'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    $uploads = [];
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
        $effect = field($_POST, 'transition', 12) ?: 'fade';
        if (!isset($effects[$effect])) throw new InvalidArgumentException('Chọn hiệu ứng chuyển ảnh hợp lệ.');
        $primary = upload_image('image');
        if ($primary) $uploads[] = $primary;
        $added = [];
        $galleryFile = $_FILES['gallery'] ?? [];
        $fileErrors = $galleryFile['error'] ?? [];
        if (!is_array($fileErrors) || count($fileErrors) > 6) throw new InvalidArgumentException('Mỗi banner có tối đa 6 ảnh.');
        foreach ($fileErrors as $index => $state) {
            $additional = store_uploaded_image([
                'error'=>$state, 'size'=>$galleryFile['size'][$index], 'tmp_name'=>$galleryFile['tmp_name'][$index],
            ]);
            if ($additional) {
                $added[] = $additional;
                $uploads[] = $additional;
            }
        }
        $removed = immediate_write($db, static function () use ($db,$placement,$primary,$added,$enabled,$mode,$target,$code,$alt,$headline,$description,$buttonText,$tone,$effect): array {
            $current = $db->prepare('SELECT image,gallery FROM ads WHERE placement=?');
            $current->execute([$placement]);
            $old = $current->fetch();
            $oldGallery = ad_gallery($old);
            $discard = (array) ($_POST['remove_gallery'] ?? []);
            $gallery = array_merge(array_filter($oldGallery, static fn(string $name): bool => !in_array($name, $discard, true)), $added);
            $image = $primary ?: (isset($_POST['remove_image']) ? '' : $old['image']);
            if (!$image && $gallery) $image = array_shift($gallery);
            if (count($gallery) + (int) ($image !== '') > 6) throw new InvalidArgumentException('Mỗi banner có tối đa 6 ảnh.');
            if ($enabled && (($mode === 'adsense' && $code === '') || ($mode === 'banner' && ($image === '' || $target === '')))) {
                throw new InvalidArgumentException('Thêm mã AdSense hoặc chọn ảnh và URL affiliate trước khi bật quảng cáo.');
            }
            $db->prepare('UPDATE ads SET mode=?,enabled=?,code=?,image=?,target=?,alt=?,headline=?,description=?,button_text=?,tone=?,gallery=?,transition=? WHERE placement=?')
                ->execute([$mode,$enabled,$code,$image,$target,$alt,$headline,$description,$buttonText,$tone,json_encode(array_values($gallery), JSON_THROW_ON_ERROR),$effect,$placement]);
            return array_diff(array_merge([$old['image']], $oldGallery), array_merge([$image], $gallery));
        });
        foreach ($removed as $name) remove_upload($name);
        flash('Đã lưu ' . mb_strtolower($placements[$placement]) . '.');
        redirect(linkto('admin/ads.php#' . $placement));
    } catch (Throwable $failure) {
        foreach ($uploads as $name) remove_upload($name);
        $error = admin_error($failure);
    }
}

$items = $db->query('SELECT * FROM ads')->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
admin_header('Quảng cáo', 'ads.php', $user);
?>
<div class="admin-heading"><div><h1>Quảng cáo và affiliate</h1><p>Quản lý sáu vị trí. Mỗi banner affiliate có thể xoay tối đa sáu ảnh và ghi click theo vị trí.</p></div></div>
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
            <label class="field"><span>Ảnh chính (tối đa 2 MB)</span><input type="file" name="image" accept="image/png,image/jpeg,image/gif,image/webp"></label>
            <?php if ($stored['image']): ?><div class="cover-preview"><img src="<?= h(linkto('uploads/' . $stored['image'])) ?>" alt="Banner hiện tại"><label><input type="checkbox" name="remove_image"> Gỡ ảnh hiện tại</label></div><?php endif; ?>
            <label class="field"><span>Thêm ảnh luân phiên (tối đa 2 MB mỗi ảnh)</span><input type="file" name="gallery[]" multiple accept="image/png,image/jpeg,image/gif,image/webp"></label>
            <?php foreach (ad_gallery($stored) as $index => $image): ?>
            <div class="cover-preview"><img src="<?= h(linkto('uploads/' . $image)) ?>" alt="Ảnh luân phiên <?= $index + 1 ?>"><label><input type="checkbox" name="remove_gallery[]" value="<?= h($image) ?>"> Gỡ ảnh <?= $index + 1 ?></label></div>
            <?php endforeach; ?>
            <label class="field"><span>Hiệu ứng giữa các ảnh</span><select name="transition">
                <?php foreach ($effects as $value => $meaning): ?><option value="<?= h($value) ?>" <?= $item['transition'] === $value ? 'selected' : '' ?>><?= h($meaning) ?></option><?php endforeach; ?>
            </select></label>
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
