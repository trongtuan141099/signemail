<?php
declare(strict_types=1);
require __DIR__ . '/layout.php';

$templates = array_map('template_record', db()->query('SELECT id,name,style,role,html,animated,accent,motion FROM templates WHERE visible=1 ORDER BY id')->fetchAll());
$posts = published_posts(3);
$popular = array_map('intval', array_column(popular_templates(30, 6), 'id'));
page_header(
    'Tạo chữ ký email miễn phí cho Gmail và Outlook | SignEmail Studio',
    'Tạo chữ ký email miễn phí cho Gmail và Outlook. Chọn mẫu, thêm logo, số điện thoại và mạng xã hội, xem trước rồi sao chép chữ ký ngay trên trình duyệt.',
    'tool',
    linkto(),
    '',
    'editor',
    ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'SignEmail Studio - TT-Hub', 'url' => 'https://tt-hub.io.vn/']
);

function editor_field(string $key, string $label, string $type = 'text', string $placeholder = '', string $value = '', string $class = ''): void
{
    ?>
    <label class="field <?= h($class) ?>">
        <span><?= h($label) ?></span>
        <input data-field="<?= h($key) ?>" type="<?= h($type) ?>" value="<?= h($value) ?>" placeholder="<?= h($placeholder) ?>" maxlength="<?= in_array($type, ['url','email'], true) ? '500' : '180' ?>">
    </label>
    <?php
}

function brand_field(string $key, string $label, string $hint): void
{
    ?>
    <section class="image-field wide" data-upload-box="<?= h($key) ?>">
        <div class="asset-preview"><span aria-hidden="true">◇</span><img alt="" hidden></div>
        <div class="asset-copy">
            <h4><?= h($label) ?></h4>
            <p><?= h($hint) ?></p>
            <label class="sr-only" for="<?= h($key) ?>-file">Tải <?= h(mb_strtolower($label)) ?></label>
            <input id="<?= h($key) ?>-file" type="file" data-upload="<?= h($key) ?>" accept="image/*,.bmp,.avif,.heic,.heif">
        </div>
        <div class="asset-source">
            <?php editor_field($key, 'Hoặc dùng URL ảnh', 'url', 'https://…'); ?>
            <button class="text-link" type="button" data-clear-image="<?= h($key) ?>" hidden>Gỡ ảnh</button>
        </div>
        <p class="upload-status" aria-live="polite"></p>
    </section>
    <?php
}

$railAds = [];
foreach (['left','right'] as $side) {
    ob_start();
    ad($side);
    $railAds[$side] = ob_get_clean();
}
$railClass = ($railAds['left'] ? ' has-left' : '') . ($railAds['right'] ? ' has-right' : '');
$uploadLimit = brand_upload_limit();
$steps = ['templates'=>['▤','Mẫu chữ ký'], 'personal'=>['◉','Hồ sơ'], 'images'=>['◇','Hình ảnh'], 'social'=>['↗','Liên kết'], 'design'=>['✦','Thiết kế']];
?>
<main>
    <section class="studio-hero shell">
        <div class="studio-intro">
            <p class="eyebrow"><span aria-hidden="true"></span> SignEmail Studio</p>
            <h1>Tạo chữ ký email miễn phí <em>cho Gmail và Outlook</em></h1>
            <p>Chọn mẫu chữ ký, thêm logo công ty, thông tin liên hệ và biểu tượng mạng xã hội. Xem trước rồi sao chép chữ ký vào email của bạn.</p>
            <a class="button studio-start" href="#editor">Bắt đầu thiết kế</a>
        </div>
        <div class="studio-illustration" aria-hidden="true" data-nosnippet>
            <div class="hero-orbit orbit-outer"></div><div class="hero-orbit orbit-inner"></div>
            <div class="hero-note hero-note-behind"><span></span><span></span><span></span></div>
            <div class="hero-note hero-note-main">
                <div class="hero-note-head"><div class="hero-monogram">MA</div><div><strong>Minh Anh Nguyễn</strong><span>Brand Lead · Studio North</span></div></div>
                <div class="hero-note-rule"></div>
                <div class="hero-note-contact">minhanh@example.com</div>
                <div class="hero-note-contact">+84 901 234 567</div>
                <div class="hero-note-icons"><span>in</span><span>◎</span><span>f</span></div>
            </div>
            <div class="hero-stamp">✦</div>
        </div>
    </section>
    <div class="studio-layout<?= h($railClass) ?><?= !$railClass ? ' shell' : '' ?>" data-nosnippet>
    <section class="studio-work" id="editor" aria-label="Trình tạo chữ ký">
        <header class="studio-heading">
            <h2>Thiết kế chữ ký</h2>
            <p>Chọn mẫu, thêm thông tin và kiểm tra chữ ký trước khi sao chép.</p>
        </header>
        <div class="workspace">
            <div class="editor panel">
                <div class="editor-navigation">
                    <div class="wizard-status"><span id="step-status">Bước 1 / 5</span><span id="step-name">Mẫu chữ ký</span></div>
                    <progress id="step-progress" value="1" max="5" aria-label="Tiến độ thiết kế"></progress>
                    <div class="tabs" role="tablist" aria-label="Các bước thiết kế">
                        <?php foreach ($steps as $key => [$icon,$label]): ?>
                        <button id="tab-<?= h($key) ?>" type="button" role="tab" aria-selected="<?= $key === 'templates' ? 'true' : 'false' ?>" aria-controls="<?= h($key) ?>" <?= $key === 'templates' ? '' : 'tabindex="-1"' ?>>
                            <span aria-hidden="true"><?= h($icon) ?></span><span><?= h($label) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div id="templates" class="tab-pane" role="tabpanel" aria-labelledby="tab-templates">
                    <label class="field"><span>Tìm mẫu</span><input type="search" id="template-search" placeholder="Tên mẫu, ngành nghề hoặc phong cách"></label>
                    <div class="filter-row">
                        <label class="field"><span>Phong cách</span><select id="style-filter"><option value="">Tất cả phong cách</option></select></label>
                        <label class="field"><span>Vị trí công việc</span><select id="role-filter"><option value="">Tất cả vị trí</option></select></label>
                    </div>
                    <div class="template-heading">
                        <div class="segmented motion-filter" aria-label="Loại mẫu">
                            <button type="button" data-template-motion="" aria-pressed="true">Tất cả</button>
                            <button type="button" data-template-motion="animated" aria-pressed="false">Có GIF</button>
                            <button type="button" data-template-motion="static" aria-pressed="false">Tĩnh</button>
                        </div>
                        <span id="template-count" aria-live="polite"></span>
                    </div>
                    <?php if ($popular): ?><p class="gallery-signal">Ưu tiên các mẫu được chọn nhiều trong 30 ngày qua.</p><?php endif; ?>
                    <div class="template-grid" id="template-grid"></div>
                    <noscript><p class="empty">Bật JavaScript để sử dụng trình tạo chữ ký.</p></noscript>
                </div>
                <div id="personal" class="tab-pane field-grid" role="tabpanel" aria-labelledby="tab-personal" hidden>
                    <?php editor_field('full_name','Họ và tên','text','Nguyễn Minh Anh','','wide'); ?>
                    <?php editor_field('job_title','Chức vụ','text','Quản lý thương hiệu'); ?>
                    <?php editor_field('company','Công ty','text','Studio North'); ?>
                    <?php editor_field('phone','Số điện thoại','tel','+84 901 234 567'); ?>
                    <?php editor_field('email','Email','email','minhanh@example.com'); ?>
                    <?php editor_field('website','Website','url','https://example.com'); ?>
                    <?php editor_field('address','Địa chỉ','text','Hà Nội, Việt Nam'); ?>
                </div>
                <div id="images" class="tab-pane field-grid" role="tabpanel" aria-labelledby="tab-images" hidden>
                    <?php brand_field('logo_url','Logo công ty','Tải logo hoặc kéo ảnh vào đây. PNG giữ nền trong suốt.'); ?>
                    <?php brand_field('avatar_url','Ảnh đại diện','Tải ảnh hoặc nhập URL. Bạn có thể điều chỉnh góc ảnh ở bước Thiết kế.'); ?>
                    <label class="field"><span>Chiều rộng logo</span><select data-field="logo_width"><option value="64">64 px</option><option value="88" selected>88 px</option><option value="112">112 px</option><option value="136">136 px</option></select></label>
                    <p class="editor-hint wide">Tệp tối đa <?= h((string) (floor($uploadLimit / 1048576 * 10) / 10)) ?> MB theo cấu hình máy chủ. GIF tối đa 3 MB.</p>
                </div>
                <div id="social" class="tab-pane field-grid" role="tabpanel" aria-labelledby="tab-social" hidden>
                    <?php foreach (['linkedin'=>'LinkedIn','facebook'=>'Facebook','zalo'=>'Zalo','site'=>'Website','tiktok'=>'TikTok','instagram'=>'Instagram','youtube'=>'YouTube'] as $key => $label) {
                        editor_field('social_' . $key, $label, 'url', 'https://…');
                    } ?>
                    <label class="field"><span>Icon mạng xã hội</span><select data-field="social_motion"><option value="static">Tĩnh</option><option value="animated">GIF động</option></select></label>
                    <label class="field"><span>Màu icon</span><select data-field="icon_tone"><option value="accent">Theo màu chủ đạo</option><option value="network">Màu từng mạng</option></select></label>
                    <div class="field-divider wide"><h3>Nút liên hệ</h3></div>
                    <?php editor_field('cta_label','Nhãn nút CTA','text','Đặt lịch hẹn','Đặt lịch hẹn'); ?>
                    <?php editor_field('cta_url','URL nút CTA','url','https://example.com/booking'); ?>
                </div>
                <div id="design" class="tab-pane field-grid" role="tabpanel" aria-labelledby="tab-design" hidden>
                    <div class="color-field"><label class="field"><span>Màu chủ đạo</span><input data-field="primary_color" type="color" value="#18634f"></label>
                        <button class="text-link" id="template-color" type="button">Dùng màu của mẫu</button>
                    </div>
                    <label class="field"><span>Phông chữ</span><select data-field="font_family">
                        <option value="Arial, sans-serif">Arial</option><option value="Verdana, sans-serif">Verdana</option>
                        <option value="Tahoma, sans-serif">Tahoma</option><option value="Georgia, serif">Georgia</option>
                        <option value="Times New Roman, serif">Times New Roman</option><option value="Courier New, monospace">Courier New</option>
                    </select></label>
                    <label class="field"><span>Cỡ chữ</span><input data-field="font_size" type="number" min="10" max="22" value="13"></label>
                    <label class="field"><span>Bo góc avatar</span><select data-field="avatar_radius"><option value="50%">Tròn</option><option value="8px">Bo nhẹ</option><option value="0px">Vuông</option></select></label>
                    <label class="field wide"><span>Biểu tượng chuyển động</span><select data-field="motion_choice">
                        <option value="auto">Theo mẫu</option><option value="off">Tắt</option>
                        <?php foreach (motion_assets() as $key => $label): ?><option value="<?= h($key) ?>"><?= h($label) ?></option><?php endforeach; ?>
                    </select></label>
                    <div class="motion-picker wide" aria-label="GIF chuyển động">
                        <?php foreach (wide_motion_assets() as $key => $label): ?>
                        <button type="button" data-motion-choice="<?= h($key) ?>" aria-pressed="false">
                            <picture>
                                <source media="(prefers-reduced-motion: reduce)" srcset="<?= h(linkto('assets/motion/' . $key . '.png')) ?>">
                                <img loading="lazy" src="<?= h(linkto('assets/motion/' . $key . '.gif')) ?>" width="108" height="28" alt="">
                            </picture>
                            <span><?= h($label) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <fieldset class="component-settings wide">
                        <legend>Thành phần hiển thị</legend>
                        <div class="component-options">
                            <?php foreach (['name'=>'Họ tên','role'=>'Chức vụ','company'=>'Công ty','phone'=>'Điện thoại','email'=>'Email','website'=>'Website','address'=>'Địa chỉ','logo'=>'Logo','avatar'=>'Ảnh đại diện','social'=>'Mạng xã hội','cta'=>'Nút CTA','banner'=>'Banner','motion'=>'GIF trang trí'] as $component => $label): ?>
                            <label><input type="checkbox" data-component="<?= h($component) ?>" checked> <?= h($label) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <label class="greeting-switch wide"><input type="checkbox" data-component="greeting"> Thêm lời chào cuối thư</label>
                    <?php editor_field('greeting','Nội dung lời chào','text','Nhập lời chào','Thanks you and Best Regard','wide'); ?>
                    <div class="field-divider wide"><h3>Banner trong chữ ký</h3></div>
                    <?php brand_field('banner_url','Ảnh banner','Tải ảnh hoặc GIF để hiển thị bên dưới chữ ký.'); ?>
                    <?php editor_field('banner_link','URL khi bấm vào banner','url','https://example.com/uu-dai','', 'wide'); ?>
                </div>
                <div class="wizard-actions">
                    <button class="button button-secondary" type="button" id="step-back" hidden>Quay lại</button>
                    <a class="text-link mobile-jump" href="#preview">Xem trước</a>
                    <button class="button" type="button" id="step-next">Tiếp tục</button>
                </div>
            </div>
            <div class="preview-column">
                <section class="panel preview-panel" id="preview" aria-labelledby="preview-title">
                    <div class="panel-heading preview-heading">
                        <div><h2 id="preview-title">Xem trước</h2><p id="selected-preview" aria-live="polite"></p></div>
                        <div class="segmented" aria-label="Giao diện xem trước">
                            <button type="button" data-theme="light" aria-pressed="true">Sáng</button>
                            <button type="button" data-theme="dark" aria-pressed="false">Tối</button>
                        </div>
                    </div>
                    <div class="preview-body">
                        <div class="example-control">
                            <span id="preview-mode">Bản minh họa đầy đủ</span>
                            <button class="text-link" type="button" id="example-toggle">Dùng mẫu này</button>
                            <label class="example-fill" id="example-fill-label" hidden><input type="checkbox" id="example-fill" checked> Hiện dữ liệu mẫu ở mục chưa điền</label>
                        </div>
                        <div id="mail-preview" class="mail">
                            <div class="mail-title"><span class="mail-icon" aria-hidden="true">✉</span><strong id="mail-label">Thư mới</strong><div class="spacer"></div>
                                <label class="mail-client"><span class="sr-only">Ứng dụng email</span><select id="mail-client"><option>Gmail</option><option>Outlook</option></select></label>
                            </div>
                            <div class="mail-meta"><span>Đến</span><span>nguoinhan@example.com</span></div>
                            <div class="mail-meta"><span>Chủ đề</span><span>Lời chào</span></div>
                            <div class="mail-content">
                                <p>Xin chào,</p><p id="mail-signoff">Trân trọng,</p>
                                <div class="signature-scroll"><div id="signature" aria-label="Chữ ký mẫu"></div></div>
                            </div>
                        </div>
                        <div class="preview-actions">
                            <button id="copy-signature" class="button" type="button" disabled><span aria-hidden="true">▣</span> Sao chép chữ ký</button>
                            <button id="copy-help" class="text-link" type="button">Cách cài chữ ký</button>
                            <a class="text-link mobile-jump" href="#editor">Chỉnh sửa</a>
                        </div>
                    </div>
                </section>
                <?php if (!ad('preview')) editorial_banner('guide'); ?>
            </div>
        </div>
    </section>
    <?php foreach ($railAds as $side => $content): if ($content): ?>
    <div class="studio-rail rail-<?= h($side) ?>"><?= $content ?></div>
    <?php endif; endforeach; ?>
    </div>
    <section class="shell featured">
        <div class="section-heading"><div><p class="eyebrow">Cẩm nang SignEmail</p><h2>Làm việc hiệu quả hơn với email</h2></div><a class="button button-secondary" href="<?= h(linkto('blog.php')) ?>">Xem cẩm nang</a></div>
        <?php post_cards($posts); ?>
    </section>
</main>
<dialog id="copy-dialog">
    <div data-nosnippet>
    <form method="dialog">
        <button class="dialog-close" value="close" aria-label="Đóng">×</button>
        <h2 id="copy-title">Cài chữ ký email</h2>
        <figure class="install-video">
            <video id="install-video" controls playsinline preload="none" poster="<?= h(asset_url('assets/guides/outlook-live-poster.webp')) ?>" aria-label="Video hướng dẫn tạo và cài chữ ký trong Outlook mới hoặc trên web">
                <source src="<?= h(asset_url('assets/guides/install-outlook.mp4')) ?>" type="video/mp4">
                <track kind="captions" src="<?= h(asset_url('assets/guides/install-outlook.vi.vtt')) ?>" srclang="vi" label="Tiếng Việt">
            </video>
            <figcaption>Thao tác được ghi trên SignEmail, có thuyết minh và phụ đề tiếng Việt. Phần Outlook dùng ảnh từ <a href="https://support.microsoft.com/en-us/outlook/training/sign-in-and-create-a-signature-for-outlook-on-the-web">Microsoft Support</a>. <span lang="en">Used with permission from Microsoft.</span></figcaption>
        </figure>
        <p><strong>Gmail</strong><br>Mở Cài đặt → Xem tất cả chế độ cài đặt → Chung → Chữ ký. Tạo chữ ký, dán rồi lưu thay đổi.</p>
        <p><strong>Outlook</strong><br>Mở Cài đặt → Tài khoản → Chữ ký. Với Outlook cổ điển, mở Tệp → Tùy chọn → Thư → Chữ ký. Dán rồi lưu. <a class="text-link" href="<?= h(post_url('cai-chu-ky-trong-outlook')) ?>">Đọc hướng dẫn chi tiết</a></p>
        <button class="button" value="close">Đóng</button>
    </form>
    </div>
</dialog>
<script type="application/json" id="template-data"><?= json_encode($templates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script type="application/json" id="editor-config"><?= json_encode([
    'iconRenderer' => canonical_url(asset_url('social-icon.php')),
    'motion' => canonical_url(linkto('assets/motion.gif')),
    'motionDirectory' => canonical_url(linkto('assets/motion/')),
    'motionKeys' => array_keys(motion_assets()),
    'wideMotions' => array_keys(wide_motion_assets()),
    'demoLogo' => canonical_url(linkto('assets/sign-mark.png')),
    'demoAvatar' => canonical_url(linkto('assets/demo-portrait.jpg')),
    'demoBanner' => canonical_url(linkto('assets/demo-studio-banner.jpg')),
    'popular' => $popular,
    'uploadLimit' => $uploadLimit,
    'upload' => linkto('brand-upload.php'),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR) ?></script>
<script defer src="<?= h(asset_url('app.js')) ?>"></script>
<?php page_footer(); ?>
