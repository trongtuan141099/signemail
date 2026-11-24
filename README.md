# SignEmail Studio

Trình tạo chữ ký email bằng PHP thuần, SQLite, Vanilla JavaScript và Tailwind CDN. Kho mặc định có 70 mẫu và 11 bài viết tiếng Việt.

## Chạy trên XAMPP

1. Bật Apache và mở [SignEmail](http://127.0.0.1/signemail/). Dùng PHP 8.0 trở lên, SQLite 3.24 trở lên và các extension PDO SQLite, DOM, fileinfo, mbstring, iconv. Bật GD nếu muốn tải logo hoặc avatar ngay trong trình chỉnh sửa.
2. Mở [Quản trị](http://127.0.0.1/signemail/admin/). Nếu database chưa có tài khoản, website chuyển đến màn hình tạo tài khoản đầu tiên. Chọn tên đăng nhập và mật khẩu dài 12–72 byte.
3. Đăng nhập. Để đổi mật khẩu, mở **Tài khoản** và nhập mật khẩu hiện tại. Sau khi đổi, đăng nhập lại bằng mật khẩu mới.

PHP tự nâng cấp cơ sở dữ liệu và bổ sung dữ liệu Đợt 2 cho bản đang chạy. Mẫu, bài viết, tài khoản và cấu hình quảng cáo đã lưu tiếp tục được sử dụng. Cần quyền ghi cho storage, uploads và uploads/brand.

Có thể chạy PHP tích hợp ngay tại thư mục dự án để phát triển cục bộ.

~~~powershell
& D:\xampp\php\php.exe -S 127.0.0.1:8000 router.php
~~~

## Shared hosting

- Bật HTTPS, giữ các tệp .htaccess và bật mod_rewrite. Nếu máy chủ không hỗ trợ rewrite, đặt SIGNEMAIL_PRETTY_URLS=0 để dùng post.php?slug=.
- Đặt SIGNEMAIL_ORIGIN thành origin HTTPS công khai, ví dụ https://chuky.example.com, để tạo canonical tuyệt đối và liên kết ảnh của chữ ký. Ảnh trên localhost chỉ mở được từ máy cài dự án.
- Tạo quản trị viên lần đầu trên hosting qua /admin/setup.php. Đặt SIGNEMAIL_SETUP_TOKEN thành chuỗi ngẫu nhiên ít nhất 32 ký tự trong cấu hình máy chủ, mở trang bằng HTTPS, nhập mã vào biểu mẫu rồi gỡ biến sau khi tạo tài khoản. Nếu máy chủ dùng reverse proxy, có thể đặt SIGNEMAIL_LOCAL_SETUP=0 và SIGNEMAIL_FORCE_SECURE=1 sau khi đã cấu hình HTTPS đúng.
- Có thể đặt SIGNEMAIL_DB thành đường dẫn SQLite tuyệt đối nằm ngoài document root. Nếu dùng thư mục mặc định, truy cập trực tiếp storage phải bị từ chối. Với hosting không dùng Apache, cấu hình tương đương cho storage, data, bin, database.sql và chỉ phục vụ ảnh hợp lệ từ uploads.
- PHP GD chuẩn hóa ảnh tải từ studio thành PNG có cạnh tối đa 512 px để dùng lâu dài trong email. Dùng URL công khai nếu cần giữ nguyên ảnh GIF động. Sao lưu uploads và SQLite cùng nhau.
- Giới hạn tải ảnh là 2 MB. Nếu nhập JSON lớn, tăng upload_max_filesize đến 12M và post_max_size cao hơn mức đó.
- Trong **Quảng cáo**, chọn vị trí, loại và trạng thái. Với affiliate, tải ảnh, thêm URL đích, tiêu đề, mô tả và nhãn nút tùy chọn. Bộ đếm ghi lượt mở liên kết khi vị trí đang bật. Với AdSense, dán mã đơn vị hiển thị có ins.adsbygoogle, publisher, slot và lệnh adsbygoogle.push. Xác minh tên miền và hoàn thành phê duyệt trong AdSense trước khi phân phối quảng cáo.

## Cấu trúc

~~~text
index.php, app.js           Trình chỉnh sửa theo bước, xem trước và sao chép
brand-upload.php            Chuẩn hóa và tải logo hoặc avatar
blog.php, post.php          Cẩm nang, tìm kiếm, chủ đề và mục lục bài viết
affiliate.php               Đếm click và chuyển hướng
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

Cả hai dùng trực tiếp trong **Quản trị → Mẫu chữ ký → Nhập JSON**. Mỗi tệp có tối đa 1.000 mẫu. Admin xuất JSON cùng định dạng và bỏ qua mẫu trùng hoàn toàn khi nhập.

Các trường chính gồm name, style, role, html, animated và visible. Trường accent đặt màu gợi ý, motion xác định GIF gợi ý. Mã HTML dùng thẻ table gốc và CSS inline. Các biến logo mới gồm {{logo_url}} và {{logo_width}}. Có thể dùng data-if="logo_url" để ẩn ô khi người dùng chưa thêm logo. {{motion_url}} dành cho GIF tích hợp và {{social_icons}} tạo bảng icon. Mẫu được tạo trước khi có biến logo vẫn hiển thị logo qua một hàng bổ sung.

Ứng dụng email có thể chặn ảnh từ URL hoặc chỉ hiển thị khung đầu của một số GIF. Nội dung liên hệ trong các mẫu là văn bản và có thể đọc độc lập với ảnh.
