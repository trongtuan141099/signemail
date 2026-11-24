<?php
declare(strict_types=1);
require __DIR__ . '/layout.php';
page_header('Giới thiệu · TT-Hub.vn', 'TT-Hub.vn cung cấp SignEmail, công cụ tạo chữ ký email miễn phí và cẩm nang thực hành Outlook, Gmail.', '', linkto('gioi-thieu.php'));
?>
<main class="shell info-page">
    <article class="info-card" aria-labelledby="info-title">
        <h1 id="info-title">Giới thiệu TT-Hub.vn</h1>
        <p class="info-lead">TT-Hub.vn giúp bạn tạo chữ ký email và thiết lập hộp thư qua các hướng dẫn bằng tiếng Việt.</p>
        <h2>Tạo chữ ký email miễn phí với SignEmail</h2>
        <p>Chọn mẫu, điền thông tin và xem trước chữ ký khi chỉnh sửa. Bạn có thể thêm logo, ảnh đại diện, liên kết mạng xã hội, nút liên hệ và lời chào cuối thư. Sau khi hoàn tất, sao chép chữ ký và dán vào phần cài đặt chữ ký của ứng dụng email bạn sử dụng.</p>
        <p>Công cụ chỉnh sửa chạy trong trình duyệt và không yêu cầu đăng nhập. Bạn tự chọn thành phần sẽ xuất hiện trong chữ ký, cũng như nội dung chia sẻ với người nhận email.</p>
        <h2>Cẩm nang Outlook và Gmail</h2>
        <p>Cẩm nang hướng dẫn các công việc thường gặp như thiết lập chữ ký, quản lý thư đến, tìm lại email và gửi thư theo lịch. Mỗi bài giải thích các bước, nêu điểm khác nhau giữa các phiên bản khi cần và dẫn tới tài liệu chính thức phù hợp.</p>
        <h2>Góp ý và hỗ trợ</h2>
        <p>Nếu bạn thấy một hướng dẫn chưa khớp với phiên bản của mình hoặc gặp lỗi khi tạo chữ ký, hãy cho chúng tôi biết ứng dụng, phiên bản và bước xảy ra vấn đề. Bạn có thể dùng <a href="<?= h(linkto('lien-he.php')) ?>">biểu mẫu liên hệ</a> để gửi phản hồi.</p>
        <div class="info-actions"><a class="button" href="<?= h(linkto('#editor')) ?>">Tạo chữ ký email</a><a class="text-link" href="<?= h(linkto('blog.php')) ?>">Đọc cẩm nang</a></div>
    </article>
</main>
<?php page_footer(); ?>
