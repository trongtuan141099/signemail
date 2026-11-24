<?php
declare(strict_types=1);
require __DIR__ . '/layout.php';
page_header('Điều khoản sử dụng · TT-Hub.vn', 'Điều khoản dùng công cụ và mẫu chữ ký email miễn phí, quyền chuyển thể, nội dung tải lên và bản quyền tại TT-Hub.vn.', '', linkto('dieu-khoan-su-dung.php'));
?>
<main class="shell info-page">
    <article class="info-card" aria-labelledby="info-title">
        <h1 id="info-title">Điều khoản sử dụng</h1>
        <p class="info-lead">Các điều khoản dưới đây áp dụng khi bạn sử dụng công cụ SignEmail và nội dung trên TT-Hub.vn.</p>
        <h2>Quyền sử dụng mẫu chữ ký miễn phí</h2>
        <p>Bạn có thể tạo, chỉnh sửa và sao chép mẫu chữ ký do TT-Hub.vn cung cấp để sử dụng trong email cá nhân, công việc hoặc hoạt động của tổ chức. Bạn có thể chia sẻ chữ ký đã tùy chỉnh với đồng nghiệp trong tổ chức. Quyền sử dụng này không bao gồm việc bán lại kho mẫu, công cụ, mã nguồn hoặc tài nguyên minh họa của website như một sản phẩm độc lập.</p>
        <h2>Nội dung bạn cung cấp</h2>
        <p>Bạn chịu trách nhiệm về quyền sử dụng logo, ảnh, nhãn hiệu, liên kết và các thông tin bạn đưa vào chữ ký. Khi tải ảnh lên, bạn cho phép TT-Hub.vn xử lý và phục vụ ảnh với mục đích hiển thị nó trong chữ ký của bạn. Bạn giữ các quyền mà mình có đối với nội dung đó.</p>
        <h2>Bản quyền bài viết và hình ảnh</h2>
        <p>TT-Hub.vn giữ quyền đối với nội dung biên soạn và thiết kế do TT-Hub.vn tạo. Bạn có thể chia sẻ đường dẫn và trích dẫn ngắn với nguồn rõ ràng. Hãy xin phép trước khi sao chép hoặc phân phối lại toàn bộ nội dung do TT-Hub.vn tạo, trừ trường hợp pháp luật hoặc giấy phép liên quan cho phép. Tài liệu, ảnh giao diện và nhãn hiệu của bên thứ ba thuộc về chủ sở hữu tương ứng và tuân theo điều kiện sử dụng của họ.</p>
        <h2>Sử dụng dịch vụ có trách nhiệm</h2>
        <p>Không sử dụng công cụ hoặc biểu mẫu liên hệ để gửi thư rác, mạo danh, xâm phạm bản quyền hoặc làm gián đoạn dịch vụ. Chúng tôi có thể hạn chế yêu cầu gây ảnh hưởng đến dịch vụ hoặc gỡ nội dung theo yêu cầu hợp lệ.</p>
        <h2>Thay đổi và liên hệ</h2>
        <p>Các mẫu, tính năng và nội dung hướng dẫn có thể được cập nhật theo thời gian. Với câu hỏi về quyền sử dụng hoặc yêu cầu liên quan đến bản quyền, hãy gửi qua <a href="<?= h(linkto('lien-he.php')) ?>">trang Liên hệ</a>.</p>
    </article>
</main>
<?php page_footer(); ?>
