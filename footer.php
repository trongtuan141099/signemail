<?php if (!function_exists('linkto')) { http_response_code(404); return; } ?>
<footer class="site-footer tthub-footer">
    <div class="shell grid gap-8 py-9 md:grid-cols-3 tthub-footer-grid">
        <div>
            <a class="brand tthub-footer-brand" href="<?= h(linkto()) ?>">TT-Hub.vn</a>
            <p class="mt-2 max-w-sm">Tạo chữ ký email miễn phí và tìm hướng dẫn thực hành cho Outlook, Gmail.</p>
        </div>
        <nav aria-label="Khám phá TT-Hub.vn">
            <h2 class="font-semibold">Khám phá</h2>
            <ul class="list-none space-y-2 mt-3">
                <li><a href="<?= h(linkto('#editor')) ?>">Trình tạo chữ ký email</a></li>
                <li><a href="<?= h(linkto('blog.php')) ?>">Cẩm nang Outlook và Gmail</a></li>
                <li><a href="<?= h(post_url('cai-chu-ky-trong-outlook')) ?>">Cách cài chữ ký Outlook</a></li>
            </ul>
        </nav>
        <nav aria-label="Thông tin pháp lý">
            <h2 class="font-semibold">Pháp lý</h2>
            <ul class="list-none space-y-2 mt-3">
                <li><a href="<?= h(linkto('gioi-thieu.php')) ?>">Giới thiệu</a></li>
                <li><a href="<?= h(linkto('lien-he.php')) ?>">Liên hệ</a></li>
                <li><a href="<?= h(linkto('chinh-sach-bao-mat.php')) ?>">Chính sách bảo mật</a></li>
                <li><a href="<?= h(linkto('dieu-khoan-su-dung.php')) ?>">Điều khoản sử dụng</a></li>
                <li><a href="<?= h(linkto('mien-tru-trach-nhiem.php')) ?>">Miễn trừ trách nhiệm</a></li>
            </ul>
        </nav>
    </div>
    <div class="shell border-t py-4 tthub-footer-bottom">
        <small>© 2026 TT-Hub.vn</small>
        <a href="<?= h(linkto('admin/')) ?>">Quản trị</a>
    </div>
</footer>
