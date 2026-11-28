<?php
declare(strict_types=1);
require __DIR__ . '/layout.php';
$path = linkto('chu-ky-viet-tay.php');
$name = 'Công cụ tạo chữ ký viết tay online miễn phí';
$description = 'Vẽ chữ ký bằng chuột hoặc ngón tay, hoặc tạo chữ ký theo tên với 16 mẫu. Chọn màu mực, tải PNG nền trong suốt để chèn vào Gmail, Outlook, Word hoặc PDF.';
page_header($name . ' | SignEmail', $description, 'handwriting', $path, '', '', [
    '@context' => 'https://schema.org',
    '@type' => 'WebApplication',
    'name' => $name,
    'url' => canonical_url($path),
    'description' => $description,
    'applicationCategory' => 'BusinessApplication',
    'operatingSystem' => 'Any',
    'inLanguage' => 'vi',
    'isAccessibleForFree' => true,
    'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'VND'],
]);
?>
<main class="shell ink-page" data-upload="<?= h(linkto('brand-upload.php')) ?>" data-editor="<?= h(linkto()) ?>">
    <header><h1><?= h($name) ?></h1><p>Vẽ bằng chuột hoặc ngón tay, hoặc nhập tên để chọn một trong 16 mẫu chữ ký. Tải ảnh PNG nền trong suốt để dùng trong email và tài liệu.</p></header>
    <div class="ink-workspace">
        <section class="ink-tool" aria-labelledby="ink-title">
            <h2 id="ink-title">Thiết kế chữ ký</h2>
            <div class="ink-tabs" role="tablist" aria-label="Cách ký">
                <button type="button" role="tab" id="ink-draw-tab" aria-controls="ink-draw-panel" aria-selected="true">✎ Vẽ trực tiếp</button>
                <button type="button" role="tab" id="ink-type-tab" aria-controls="ink-type-panel" aria-selected="false" tabindex="-1">Aa · Mẫu theo tên</button>
            </div>
            <div class="ink-controls">
                <fieldset class="ink-palette"><legend>Màu mực</legend><div class="ink-swatch-bar">
                    <?php foreach (['#173c85'=>'Xanh dương','#181818'=>'Đen','#17705b'=>'Xanh lục','#008299'=>'Xanh ngọc','#59398b'=>'Tím','#98234e'=>'Đỏ rượu','#9c492e'=>'Nâu đồng','#526578'=>'Xám xanh'] as $hex => $label): ?>
                        <button type="button" class="ink-swatch" style="--swatch:<?= h($hex) ?>" data-ink-color="<?= h($hex) ?>" aria-label="<?= h($label) ?>" title="<?= h($label) ?>" aria-pressed="<?= $hex === '#173c85' ? 'true' : 'false' ?>"></button>
                    <?php endforeach; ?>
                    <label class="ink-custom">Màu riêng <input type="color" id="ink-color" value="#173c85"></label>
                </div></fieldset>
                <label id="ink-weight-label">Độ dày nét bút <span class="ink-width-picker"><input id="ink-weight" type="range" min="1" max="14" value="6"><span id="ink-stroke-sample" aria-hidden="true"></span><output for="ink-weight" id="ink-weight-value">6 px</output></span></label>
            </div>
            <div id="ink-type-panel" role="tabpanel" aria-labelledby="ink-type-tab" hidden>
                <div class="ink-controls"><label>Tên để ký<input id="ink-name" maxlength="80" placeholder="Nguyễn Minh Anh"></label></div>
                <fieldset><legend>Mẫu chữ ký theo tên</legend><div class="ink-samples" id="ink-font"></div></fieldset>
            </div>
            <div id="ink-draw-panel" role="tabpanel" aria-labelledby="ink-draw-tab">
                <div class="ink-drawing-surface">
                    <canvas id="ink-pad" class="ink-drawing" width="1200" height="460" aria-label="Bảng ký trực tiếp"></canvas>
                    <div id="ink-name-strip"><input id="ink-caption" aria-label="Họ và tên dưới chữ ký" maxlength="80" placeholder="Nhập họ và tên" disabled></div>
                </div>
                <div class="ink-actions ink-tools">
                    <button id="ink-pen" type="button" aria-pressed="true">✎ Bút ký</button>
                    <button id="ink-eraser" type="button" aria-pressed="false">▱ Tẩy</button>
                    <button id="ink-undo" type="button" disabled>↶ Hoàn tác</button>
                    <button id="ink-clear" type="button" disabled>× Xóa hết</button>
                </div>
            </div>
            <div class="ink-caption">
                <label><input type="checkbox" id="ink-caption-on"> Thêm họ tên bên dưới chữ ký</label>
                <div class="ink-controls" id="ink-caption-fields" hidden>
                    <div id="ink-type-caption" hidden></div>
                    <fieldset><legend>Phông họ tên</legend><div class="ink-samples" id="ink-caption-font"></div></fieldset>
                </div>
            </div>
        </section>
        <section class="ink-tool ink-proof" aria-labelledby="proof-title">
            <h2 id="proof-title">Xem trước</h2>
            <div class="ink-paper"><p id="ink-empty">Vẽ hoặc nhập tên để xem chữ ký tại đây.</p>
                <canvas id="ink-preview" hidden aria-label="Ảnh chữ ký hoàn chỉnh"></canvas></div>
            <div class="ink-actions">
                <button id="ink-download" type="button" class="button" disabled>↓ Tải PNG trong suốt</button>
                <button id="ink-insert" type="button" class="button button-secondary" disabled>✉ Chèn vào chữ ký email</button>
            </div>
            <p id="ink-status" role="status"></p>
        </section>
    </div>
    <article class="ink-guide">
        <h2>Cách tạo chữ ký viết tay và chèn vào Gmail, Outlook, Word hoặc PDF</h2>
        <p>Một chữ ký dùng trong email cần dễ nhận ra ngay cả khi thu nhỏ. Trước khi ký, hãy hình dung vị trí bạn định đặt ảnh: dưới lời chào cuối thư, cạnh thông tin liên hệ hay trên dòng dành cho người ký trong tài liệu. Nền trong suốt giúp cùng một ảnh PNG sử dụng được ở cả ba vị trí.</p>
        <h3>Vẽ trực tiếp hoặc chọn mẫu theo tên</h3>
        <p>Ở tab <strong>Vẽ trực tiếp</strong>, chọn màu từ thanh màu mực, rồi kéo thanh độ dày để thử nét mảnh hoặc đậm. Bạn có thể đổi màu toàn bộ chữ ký sau khi vẽ. Ký liền mạch như trên giấy; thử di chuyển chậm ở chữ cái đầu, nhanh hơn ở nét kết. Nếu nét cuối chưa vừa ý, bấm <strong>Hoàn tác</strong>. Nếu hai nét giao nhau và bạn chỉ muốn bỏ một đoạn nhỏ, chọn <strong>Tẩy</strong>, rê qua đoạn đó rồi chuyển về bút. Bạn cũng có thể hoàn tác một lần tẩy.</p>
        <p>Ở tab <strong>Mẫu theo tên</strong>, nhập tên có dấu và so sánh các mẫu bằng chính tên mình. Thử một nét kết hướng lên hoặc một đường cong ôm dưới tên. Bật dòng họ tên nếu chữ ký cách điệu khó đọc. Trong khung xem trước, kiểm tra dấu tiếng Việt, khoảng cách giữa hai dòng và màu mực trước khi chọn <strong>Tải PNG trong suốt</strong>.</p>
        <h3>Đặt chữ ký vào Gmail và Outlook</h3>
        <p>Để ghép nét ký với chức danh, số điện thoại và logo, chọn <strong>Chèn vào chữ ký email</strong>. Kiểm tra bố cục trong SignEmail rồi sao chép chữ ký hoàn chỉnh. Trên Gmail máy tính, mở <strong>Cài đặt → Xem tất cả chế độ cài đặt → Chung → Chữ ký</strong>. Tạo chữ ký mới, dán vào ô soạn, chọn mặc định cho thư mới hoặc thư trả lời và lưu thay đổi ở cuối trang.</p>
        <p>Trong Outlook mới hoặc Outlook trên web, mở <strong>Cài đặt</strong> và tìm “Chữ ký”. Với Outlook cổ điển trên Windows, vào <strong>File → Options → Mail → Signatures</strong>. Chọn đúng tài khoản gửi, dán chữ ký và lưu. Gửi một thư thử tới điện thoại để kiểm tra ảnh có rõ và vừa chiều ngang hay không.</p>
        <h3>Chèn ảnh chữ ký vào Word và PDF</h3>
        <p>Trong Word, đặt con trỏ tại dòng ký, chọn <strong>Insert → Pictures</strong> rồi mở tệp PNG vừa tải. Kéo từ góc ảnh để giữ tỷ lệ. Nếu đang làm PDF từ Word, hãy đặt chữ ký trong bản Word trước khi xuất PDF. Với PDF có sẵn, mở trình đọc hỗ trợ thêm chữ ký bằng ảnh, chọn tệp PNG, đặt lên dòng ký rồi lưu một bản sao. Mở lại bản cuối để kiểm tra vị trí và kích thước.</p>
    </article>
</main>
<script defer src="https://cdn.jsdelivr.net/npm/signature_pad@5.1.2/dist/signature_pad.umd.min.js" crossorigin="anonymous" integrity="sha384-oazhhUxOpNZqf88N8kvQCzj5IzLdcLFAFbjlZDAuh4EZNyXiNoPlam/0EwVN3McI"></script>
<script defer src="<?= h(asset_url('assets/handwriting.js')) ?>" data-pad-fallback="<?= h(asset_url('assets/vendor/signature-pad/signature_pad.umd.min.js')) ?>"></script>
<?php page_footer(); ?>
