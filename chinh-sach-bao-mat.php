<?php
declare(strict_types=1);
require __DIR__ . '/layout.php';
page_header('Chính sách bảo mật · TT-Hub.vn', 'Cách TT-Hub.vn xử lý dữ liệu nhập khi tạo chữ ký, ảnh tải lên, tin nhắn hỗ trợ, cookie và quảng cáo Google AdSense.', '', linkto('chinh-sach-bao-mat.php'));
?>
<main class="shell info-page">
    <article class="info-card" aria-labelledby="info-title">
        <h1 id="info-title">Chính sách bảo mật</h1>
        <p class="info-lead">Chính sách này áp dụng cho SignEmail, Cẩm nang email và các trang thông tin trên TT-Hub.vn.</p>
        <h2>Thông tin bạn nhập để tạo chữ ký</h2>
        <p>Tên, chức vụ, công ty, số điện thoại, email, địa chỉ và các nội dung văn bản bạn nhập trong trình tạo chữ ký được xử lý trên trình duyệt của bạn. TT-Hub.vn không lưu trữ các trường này trên máy chủ, không bán dữ liệu cá nhân và không gửi nội dung chữ ký cho mạng quảng cáo để cá nhân hóa quảng cáo. Khi bạn sao chép hoặc dán chữ ký, dữ liệu đi qua bộ nhớ tạm và ứng dụng email do bạn chọn.</p>
        <h2>Ảnh tải lên và URL ảnh</h2>
        <p>Khi chủ động tải ảnh lên, bạn gửi tệp cho TT-Hub.vn để xử lý và lưu trữ. Ảnh được cấp URL để hiển thị trong chữ ký, nên người có URL có thể mở ảnh. Hãy tải ảnh bạn có quyền sử dụng và tránh đưa thông tin riêng tư vào ảnh. Nếu dùng URL từ bên ngoài, trình duyệt hoặc trình đọc email có thể truy cập máy chủ cung cấp ảnh theo chính sách của bên đó.</p>
        <h2>Thông tin hỗ trợ và nhật ký kỹ thuật</h2>
        <p>Để ước tính số khách duy nhất mỗi ngày trên công cụ và blog, bộ đếm tạo mã băm từ địa chỉ IP và thông tin trình duyệt với khóa riêng cho từng ngày. Khi có lượt xem đầu tiên của ngày mới, hệ thống xóa mã nhận diện và khóa của các ngày trước, chỉ giữ số liệu tổng hợp. Bộ đếm không đặt cookie và không lưu IP gốc trong bảng thống kê.</p>
        <p>Khi gửi biểu mẫu liên hệ, chúng tôi lưu tên, email, chủ đề và tin nhắn để xử lý và phản hồi. Chúng tôi dùng bộ đếm tổng hợp theo ngày cho lượt xem, lượt chọn mẫu và lượt nhấp affiliate. Máy chủ hoặc nhà cung cấp hạ tầng có thể ghi thông tin kỹ thuật như địa chỉ IP, thời gian truy cập và URL được yêu cầu để vận hành và bảo vệ dịch vụ.</p>
        <h2>Cookies cần thiết và cookie quảng cáo</h2>
        <p>Trang quản trị và biểu mẫu liên hệ có thể đặt cookie phiên để duy trì đăng nhập hoặc bảo vệ việc gửi biểu mẫu. Bạn có thể xóa hoặc chặn cookie trong trình duyệt. Nếu chặn cookie phiên, một số chức năng liên hệ hoặc quản trị có thể không hoạt động.</p>
        <p>Khi Google AdSense được bật, Google và các nhà cung cấp quảng cáo bên thứ ba có thể đặt hoặc đọc cookie, dùng tín hiệu như địa chỉ IP, mã định danh thiết bị và điểm ảnh để phân phối, đo lường hoặc chống gian lận quảng cáo. Cookie quảng cáo của Google cho phép Google và đối tác hiển thị quảng cáo dựa trên lịch sử truy cập TT-Hub.vn và các trang khác. Bạn có thể quản lý quảng cáo cá nhân hóa tại <a href="https://myadcenter.google.com/">Trung tâm quảng cáo của tôi</a> và đọc <a href="https://policies.google.com/technologies/partner-sites?hl=vi">cách Google sử dụng dữ liệu trên trang đối tác</a>.</p>
        <p>Liên kết quảng cáo và affiliate có thể dẫn tới trang của đối tác có chính sách riêng. Những lựa chọn về cookie và cá nhân hóa trên các trang đó được quản lý với từng nhà cung cấp.</p>
        <h2>Yêu cầu về dữ liệu</h2>
        <p>Bạn có thể đề nghị kiểm tra, sửa hoặc xóa thông tin đã gửi qua biểu mẫu, hoặc đề nghị gỡ ảnh đã tải. Liên hệ <a href="mailto:support@tt-hub.vn">support@tt-hub.vn</a> và cung cấp thông tin cần thiết để chúng tôi xác định yêu cầu. Nếu yêu cầu gỡ một ảnh đang dùng trong chữ ký, ảnh có thể mất khỏi các thư đã gửi khi người nhận tải lại ảnh.</p>
    </article>
</main>
<?php page_footer(); ?>
