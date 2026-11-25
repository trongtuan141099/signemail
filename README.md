# SignEmail Studio

Trình tạo chữ ký email bằng PHP thuần, SQLite, Vanilla JavaScript và Tailwind CDN. Kho mặc định có 80 mẫu chữ ký.

## Chạy trên XAMPP

1. Bật Apache và mở [SignEmail](http://127.0.0.1/signemail/). Dùng PHP 8.0 trở lên, SQLite 3.24 trở lên và các extension PDO SQLite, DOM, fileinfo, mbstring, iconv. Bật GD nếu muốn tải logo hoặc avatar ngay trong trình chỉnh sửa.
2. Mở [Quản trị](http://127.0.0.1/signemail/admin/). Nếu database chưa có tài khoản, website chuyển đến màn hình tạo tài khoản đầu tiên. Chọn tên đăng nhập và mật khẩu dài 12–72 byte.
3. Đăng nhập. Để đổi mật khẩu, mở **Tài khoản** và nhập mật khẩu hiện tại. Sau khi đổi, đăng nhập lại bằng mật khẩu mới.

PHP tự nâng cấp cơ sở dữ liệu cho bản đang chạy, bổ sung kho mẫu, vị trí quảng cáo và bảng thống kê cần thiết. Mẫu, bài viết, tài khoản và cấu hình quảng cáo đã lưu tiếp tục được sử dụng. Cần quyền ghi cho storage, uploads và uploads/brand.

Có thể chạy PHP tích hợp ngay tại thư mục dự án để phát triển cục bộ.

~~~powershell
& D:\xampp\php\php.exe -S 127.0.0.1:8000 router.php
~~~

## Shared hosting

- Bật HTTPS, giữ các tệp .htaccess và bật mod_rewrite. Nếu máy chủ không hỗ trợ rewrite, đặt SIGNEMAIL_PRETTY_URLS=0 để dùng post.php?slug=.
- Đặt SIGNEMAIL_ORIGIN thành origin HTTPS công khai, ví dụ https://chuky.example.com, để tạo canonical tuyệt đối và liên kết ảnh của chữ ký. Ảnh trên localhost chỉ mở được từ máy cài dự án.
- Lần đầu mở **Quản trị** bằng HTTPS, website tự chuyển đến trang thiết lập và tạo tệp `storage/admin-setup-code.php`. Mở tệp bằng File Manager của hosting, sao chép mã một lần, rồi nhập tên đăng nhập và mật khẩu ngay trên trang thiết lập. Website xóa tệp mã khi tạo xong tài khoản. Không cần cấu hình `SIGNEMAIL_SETUP_TOKEN`. Nếu máy chủ dùng reverse proxy, có thể đặt `SIGNEMAIL_LOCAL_SETUP=0` và `SIGNEMAIL_FORCE_SECURE=1` sau khi đã cấu hình HTTPS đúng.
- Biểu mẫu **Liên hệ** lưu tin nhắn vào **Quản trị → Tin nhắn**. Đặt `TTHUB_CONTACT_MAIL=1` và cấu hình PHP `mail()` để gửi bản sao tới `support@tt-hub.vn`. Trên hosting, xác minh thư đến hộp thư thật bằng một lần gửi thử. Tin nhắn vẫn được lưu trong trang quản trị nếu thư thông báo gửi thất bại.
- Có thể đặt SIGNEMAIL_DB thành đường dẫn SQLite tuyệt đối nằm ngoài document root. Nếu dùng thư mục mặc định, truy cập trực tiếp storage phải bị từ chối. Với hosting không dùng Apache, cấu hình tương đương cho storage, data, bin, database.sql và chỉ phục vụ ảnh hợp lệ từ uploads.
- PHP GD chuẩn hóa ảnh tải từ studio thành PNG, cạnh tối đa 640 px cho logo và avatar, 1.200 px cho banner. Có thể tải trực tiếp GIF để giữ chuyển động, tối đa 3 MB và mỗi chiều tối đa 1.600 px. Sao lưu uploads và SQLite cùng nhau.
- Studio nhận ảnh tĩnh đến 10 MB nếu cấu hình PHP cho phép. Trình duyệt thử thu nhỏ ảnh tĩnh khi tệp vượt ngưỡng của máy chủ. Ảnh quảng cáo trong Admin tối đa 2 MB mỗi tệp. Tăng post_max_size khi tải nhiều ảnh hoặc nhập JSON lớn.
- Trong **Quảng cáo**, chọn một trong sáu vị trí, loại và trạng thái. Với affiliate, tải tối đa sáu ảnh và chọn hiệu ứng chuyển ảnh, rồi thêm URL đích, tiêu đề, mô tả và nhãn nút tùy chọn. Hai vị trí lề hiển thị cạnh trình chỉnh sửa từ 1.280 px và chuyển xuống dưới ở màn hình hẹp. Bộ đếm ghi lượt mở liên kết khi vị trí đang bật. Với AdSense, dán mã đơn vị hiển thị có ins.adsbygoogle, publisher, slot và lệnh adsbygoogle.push. Xác minh tên miền và hoàn thành phê duyệt trong AdSense trước khi phân phối quảng cáo.

## Sử dụng bản minh họa và thống kê

- Chọn **Dùng mẫu này** để điền thông tin ngay trên bản xem trước. Các mục chưa điền có thể tiếp tục hiển thị dữ liệu minh họa. Bỏ chọn **Hiện dữ liệu mẫu ở mục chưa điền** để xem đúng nội dung sẽ sao chép. Trong **Thiết kế**, bật/tắt từng thành phần mà vẫn giữ dữ liệu đã nhập, thêm lời chào cuối thư, tải banner hoặc chọn GIF. Trong **Liên kết**, chọn icon theo màu chữ ký hoặc theo màu mạng xã hội.
- Mở **Cách cài chữ ký** để xem các thao tác được ghi trên SignEmail, với thuyết minh tiếng Việt và phụ đề. Phần cài đặt trong Outlook mới hoặc Outlook trên web dùng ảnh giao diện từ Microsoft Support.
- **Quản trị → Tổng quan** hiển thị lượt tải trang và lượt chọn mẫu trong 30 ngày. Mỗi mẫu được tính tối đa một lần trên một lần tải trang, khi người dùng chủ động chọn. Thống kê được tổng hợp theo ngày và không dùng để đo khách duy nhất. Mẫu đang hiển thị có lượt chọn cao được ưu tiên trong thư viện.

## Cấu trúc

~~~text
index.php, app.js           Trình chỉnh sửa theo bước, xem trước và sao chép
brand-upload.php            Chuẩn hóa và tải logo hoặc avatar
blog.php, post.php          Cẩm nang, tìm kiếm, chủ đề và mục lục bài viết
footer.php                  Chân trang dùng chung và các liên kết pháp lý
lien-he.php, admin/contact.php  Biểu mẫu hỗ trợ và hộp thư quản trị
affiliate.php, activity.php Đếm click, lượt xem và lượt chọn mẫu
app-server.php, layout.php  SQLite, nâng cấp dữ liệu, bộ lọc HTML và giao diện chung
database.sql                Schema SQLite
admin/                      Thiết lập tài khoản, đăng nhập, mẫu, blog và quảng cáo
assets/                     CSS, GIF, icon PNG và ảnh bìa
data/                       Hai đợt mẫu chữ ký và nội dung khởi tạo blog
storage/, uploads/          SQLite và ảnh tải lên
router.php                  Router cho máy chủ tích hợp của PHP
~~~

## Các đợt mẫu JSON

- data/templates-batch-01.json gồm 20 mẫu Tối giản và Doanh nghiệp.
- data/templates-batch-02.json gồm 50 mẫu bổ sung thuộc 10 nhóm phong cách.
- data/templates-batch-03.json gồm 10 mẫu lấy cảm hứng từ thiết kế biên tập, du lịch, kiến trúc và các ngành sáng tạo. Năm mẫu có GIF mặc định.

Cả hai dùng trực tiếp trong **Quản trị → Mẫu chữ ký → Nhập JSON**. Mỗi tệp có tối đa 1.000 mẫu. Admin xuất JSON cùng định dạng và bỏ qua mẫu trùng hoàn toàn khi nhập.

Các trường chính gồm name, style, role, html, animated và visible. Trường accent đặt màu gợi ý, motion xác định GIF gợi ý. Mã HTML dùng thẻ table gốc và CSS inline. Các biến logo mới gồm {{logo_url}} và {{logo_width}}. Có thể dùng data-if="logo_url" để ẩn ô khi người dùng chưa thêm logo. {{motion_url}} dành cho GIF tích hợp và {{social_icons}} tạo bảng icon. Mẫu được tạo trước khi có biến logo vẫn hiển thị logo qua một hàng bổ sung.

Ứng dụng email có thể chặn ảnh từ URL hoặc chỉ hiển thị khung đầu của một số GIF. Nội dung liên hệ trong các mẫu là văn bản và có thể đọc độc lập với ảnh.
