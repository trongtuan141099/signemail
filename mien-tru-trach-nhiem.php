<?php
declare(strict_types=1);
require __DIR__ . '/layout.php';
page_header('Miễn trừ trách nhiệm · TT-Hub.vn', 'Tính độc lập của TT-Hub.vn, giới hạn hướng dẫn và công bố về quảng cáo Google AdSense và liên kết affiliate.', '', linkto('mien-tru-trach-nhiem.php'));
?>
<main class="shell info-page">
    <article class="info-card" aria-labelledby="info-title">
        <h1 id="info-title">Miễn trừ trách nhiệm</h1>
        <p class="info-lead">TT-Hub.vn là website độc lập cung cấp trình tạo chữ ký và hướng dẫn sử dụng email.</p>
        <h2>Độc lập với Microsoft và Google</h2>
        <p>TT-Hub.vn không trực thuộc, được tài trợ hoặc được Microsoft hay Google bảo chứng. Tên Microsoft, Outlook, Google và Gmail được dùng để xác định sản phẩm đề cập trong hướng dẫn. Nhãn hiệu và ảnh giao diện thuộc về chủ sở hữu tương ứng. Khi có thể, bài viết dẫn tới tài liệu chính thức để bạn đối chiếu.</p>
        <h2>Hướng dẫn và khả năng hiển thị</h2>
        <p>Đường dẫn trong menu và cách hiển thị chữ ký có thể thay đổi tùy phiên bản ứng dụng, thiết bị và thiết lập của tổ chức hoặc người nhận. Hãy thử gửi thư trước khi áp dụng chữ ký cho công việc. Nội dung của cẩm nang nhằm hỗ trợ thực hành và không thay thế tài liệu hoặc kênh hỗ trợ của nhà cung cấp dịch vụ.</p>
        <h2>Quảng cáo và liên kết affiliate</h2>
        <p>Website có thể hiển thị quảng cáo, bao gồm quảng cáo do Google AdSense hoặc đối tác khác phân phối. Các khu vực quảng cáo được gắn nhãn để bạn nhận biết. Một số liên kết có thể là liên kết affiliate. Nếu bạn mua hàng hoặc thực hiện hành động đủ điều kiện qua liên kết đó, TT-Hub.vn có thể nhận hoa hồng.</p>
        <p>Giá, bảo hành, điều kiện giao dịch và chính sách hoàn tiền do bên bán hoặc nhà cung cấp áp dụng. Hãy kiểm tra những thông tin đó trên trang đích trước khi quyết định. Chi tiết về cookie quảng cáo có trong <a href="<?= h(linkto('chinh-sach-bao-mat.php')) ?>">Chính sách bảo mật</a>.</p>
    </article>
</main>
<?php page_footer(); ?>
