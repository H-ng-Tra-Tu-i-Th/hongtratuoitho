<?php
if (!defined('HUIYI_DEVELOPER_DOCS')) { http_response_code(404); exit; }
return [
    'version'=>'19.9.1',
    'markdown'=>'<a id="tieng-viet"></a>

# Nhật ký phát triển hệ thống đặt đồ uống HUIYI

[Tiếng Việt](#tieng-viet) · [繁體中文](#traditional-chinese)

Phạm vi bản ghi: mã nguồn cục bộ 19.9.1 và các gói đã bàn giao. Nhánh GitHub `codex/promotions-member-rewards` hiện có mã nguồn 19.7.0; lần cập nhật này công bố nhật ký song ngữ. Các tệp chưa nằm trên nhánh được giữ dưới dạng đường dẫn có nhãn “tệp cục bộ”.

Ngày cập nhật: ngày 7 tháng 10 năm 2026, giờ Việt Nam UTC+7<br>
Phiên bản chương trình hiện tại: 19.9.1<br>
Phiên bản giao diện tiếp nhận đơn hàng trên điện thoại: 1.2<br>
Phiên bản lõi thông báo: 19.1<br>
Phiên bản hình mặt thẻ mới nhất: Bộ thẻ nhỏ HUIYI v3

Hệ thống hiện đã có chức năng đặt hàng thông thường, chia tiền cho đơn nhóm, thành viên nền tảng, lựa chọn một ưu đãi duy nhất, trò chơi lật thẻ bốc thăm trúng thưởng khi mua 10 ly, giao diện quản lý sản phẩm và cửa hàng, cùng thông báo tiếp nhận đơn hàng qua Email và Web Push. Phiên bản mới nhất 19.9.1 bổ sung cơ chế cập nhật lượt chơi và tự động chuyển vào trò chơi trên trang hoàn tất đơn hàng; khách phải chờ cửa hàng hoàn tất đơn đủ điều kiện và thực sự được cấp lượt chơi rồi mới được vào trò chơi.

Trang chính thức và trang thử nghiệm đã sử dụng cơ sở dữ liệu riêng. Các tệp giao diện công khai trên shop đã hoạt động bình thường sau khi cập nhật; toàn bộ quy trình trực tuyến từ đặt hàng đến nhận phiếu ưu đãi qua trò chơi được đưa vào đợt nghiệm thu tiếp theo. Sáu mẫu mặt thẻ HUIYI mới nhất đã hoàn thành hình ảnh và bản xem trước, đang chờ tải lên trang quản lý trò chơi.

## 1. Phiên bản hiện tại và trạng thái triển khai

| Hạng mục | Trạng thái hiện tại |
| --- | --- |
| Chương trình dự án | VERSION là 19.9.1, bao gồm các chức năng đặt hàng theo nhóm, thành viên, phân công giao hàng, trò chơi và thiết lập quản trị từ các phiên bản trước |
| Trang chính thức | order.huiyitea.vn đã vận hành chính thức; phiên bản đang chạy còn chờ đối chiếu, việc cập nhật trang chính thức phải được bố trí sau khi nghiệm thu trên shop |
| Trang thử nghiệm | shop.huiyitea.vn là nơi ưu tiên triển khai và thử nghiệm các thay đổi tiếp theo |
| Cơ sở dữ liệu | Người dùng đã xác nhận vào 2026-10-07 rằng order và shop đã tách cơ sở dữ liệu; hạn chế do dùng chung cơ sở dữ liệu trước đây đã được gỡ bỏ |
| Thư mục website shop | /home/huiyitea/shop.huiyitea.vn |
| Cấu hình riêng của shop | Kết nối cơ sở dữ liệu phải trỏ đến shop, APP_BASE_URL phải là https://shop.huiyitea.vn |
| Khắc phục kết nối shop | Đã cấp bổ sung ALL PRIVILEGES cho tài khoản huiyitea_shop trên cơ sở dữ liệu cùng tên; trang chủ và trang đăng nhập quản trị trả về 200 |
| Khắc phục giao diện shop | Đã xử lý lỗi 403 của các tệp CSS／JavaScript công khai sau khi cập nhật; 32 tệp tĩnh công khai đã có thể đọc lại, đã xác nhận giá L／XL hiển thị trên hai dòng và căn phải |
| Gói cập nhật chương trình mới nhất | `dist/drink-order-cpanel-v19.9.1-update.zip` (tệp cục bộ), 739,539 bytes, 163 tệp, quyền của các tệp thông thường là 0644 |
| Gói cài đặt cho cơ sở dữ liệu trống | `dist/drink-order-cpanel-v19.9.0-shop-install.zip` (tệp cục bộ) đã có; quyền của các tệp công khai trong gói vẫn cần sửa, xem các việc bảo trì cần thực hiện |
| Mặt thẻ mới nhất | Đã hoàn thành sáu ảnh PNG HUIYI nền trong suốt và bản xem trước ngoại tuyến; chưa tải lên trang quản trị hoặc thiết lập làm mặt thẻ trên website |

Phiên bản chương trình, giao diện điện thoại và hình ảnh được quản lý riêng. Mặt thẻ v3 là phiên bản hình ảnh thứ ba, có thể tải lên và sử dụng cùng các chủ đề hiện có trong trang quản trị.

## 2. Tổng quan chức năng toàn hệ thống

| Mô-đun | Chức năng đã hoàn thành | Điểm truy cập chính |
| --- | --- | --- |
| Xem sản phẩm | Chuyển đổi ba ngôn ngữ, danh mục, đề xuất, món mới, món bán chạy, hiển thị số lượng đã đặt, giá L／XL | Trang chủ index.php |
| Tùy chỉnh đồ uống | Cỡ ly, lượng đá, độ ngọt, topping đề xuất và topping khác, số lượng | product.php |
| Topping bán riêng | Sản phẩm topping độc lập và giá bán riêng | topping.php |
| Giỏ hàng | Điều chỉnh số lượng, xóa món, tạm tính, lựa chọn ưu đãi | cart.php |
| Thanh toán đơn thông thường | Điền sẵn thông tin thành viên, tiền mặt／chuyển khoản, QR nhận tiền, ghi chú, thông tin hóa đơn | checkout.php |
| Đặt hàng theo nhóm | Tạo nhóm, mời và QR, cùng chọn món, phân chia thanh toán, trưởng nhóm xác nhận, xóa thành viên chưa thanh toán, gửi đơn một lần | group/ |
| Thành viên | Đăng ký và đăng nhập bằng số điện thoại, thông tin giao hàng, đơn hàng của mình, lượt chơi và phiếu ưu đãi trúng thưởng, dữ liệu iPOS chỉ đọc | member/ |
| Ưu đãi | Chọn một trong bốn phương án: không dùng ưu đãi, Mua 4 tặng 1, Mua 10 ly – bốc thăm trúng thưởng, phiếu ưu đãi trúng thưởng cá nhân | Giỏ hàng thông thường và trang trưởng nhóm |
| Trò chơi | 12 thẻ, 6 cặp, ván chơi lưu trên máy chủ, chơi tiếp, phát phiếu ưu đãi ngẫu nhiên | minigame/ |
| Quản lý trò chơi | Bật／tắt chương trình, ba chủ đề, mặt sau thẻ, sáu bộ hình, phần thưởng và xác suất | admin/game.php, admin/settings.php |
| Quản lý sản phẩm | Sản phẩm, danh mục, topping, thiết lập đá và độ ngọt, giá, hình ảnh, thứ tự và kích hoạt | admin/ |
| Quản lý tiếp nhận đơn hàng | Bốn trạng thái đơn hàng, món, khách hàng, thanh toán, hóa đơn, phân công giao hàng và trạng thái thông báo | admin/, mobile-admin/ |
| Cửa hàng và phân công giao hàng | Thông tin cửa hàng, vị trí, đề xuất cửa hàng gần nhất, phân công thủ công và lịch sử thay đổi | admin/stores.php, trang chi tiết đơn hàng |
| Thông báo | Email nhiều người nhận, gửi lại, âm thanh khi mở giao diện ở phía trước, Web Push, hàng đợi gửi lại và chẩn đoán | Thiết lập hệ thống và hai giao diện tiếp nhận đơn hàng |
| PWA | Thêm giao diện khách hàng và giao diện tiếp nhận đơn hàng vào màn hình chính, lưu bộ nhớ đệm tài nguyên tĩnh | manifest của website và Service Worker |

## 3. Quy trình đặt hàng thông thường của khách

### Hiển thị sản phẩm và giá

Trang chủ cung cấp nội dung bằng tiếng Việt, tiếng Anh và tiếng Trung. Khách có thể xem đồ uống theo danh mục, xem món mới, món bán chạy và món đề xuất; topping được phép bán riêng có điểm truy cập mua hàng riêng. Việc hiển thị số lượng đã đặt được điều khiển bằng tùy chọn bật／tắt trong trang quản trị.

Sản phẩm có lựa chọn cỡ ly có giá riêng cho L và XL. Từ 19.8.1, thẻ sản phẩm đề xuất và thẻ sản phẩm theo danh mục trên trang chủ hiển thị hai dòng, L ở trên và XL ở dưới; cỡ ly và số tiền căn phải, mép phải của các số tiền thẳng hàng. Sản phẩm chỉ có một mức giá vẫn hiển thị trên một dòng.

### Từ chọn món đến trang hoàn tất

1. Khách chọn đồ uống và mở trang sản phẩm.
2. Chọn cỡ ly đang bán, lượng đá và độ ngọt; chọn topping đề xuất hoặc topping khác nếu cần.
3. Giá mỗi ly bằng giá đồ uống cơ bản cộng giá các topping đã chọn. Mỗi lần thêm được 1–20 phần.
4. Máy chủ kiểm tra lại trạng thái kích hoạt của sản phẩm, cỡ ly, lựa chọn đá, độ ngọt và topping, sau đó lưu món vào giỏ hàng của trình duyệt này.
5. Điều chỉnh số lượng hoặc xóa món trong giỏ hàng. Mỗi dòng tối đa 20 phần; dòng có số lượng bằng không sẽ bị xóa.
6. Chọn một ưu đãi, kiểm tra số tiền tạm tính, giảm giá và số tiền phải thanh toán.
7. Khi thanh toán, nhập họ tên, số điện thoại, địa chỉ giao hàng, phương thức thanh toán, ghi chú và thông tin hóa đơn nếu cần. Thành viên có thể dùng thông tin cá nhân đã lưu; khách chưa đăng ký cũng có thể đặt hàng.
8. Máy chủ kiểm tra các món hiện tại, ưu đãi và quyền sở hữu theo tài khoản đăng nhập, rồi lưu đơn hàng và các món trong cùng một giao dịch cơ sở dữ liệu.
9. Sau khi tạo đơn thành công, giỏ hàng thông thường được làm trống và trang hoàn tất được hiển thị; thông báo và định vị địa chỉ được xử lý sau khi đơn đã được tạo.
10. Cửa hàng xử lý đơn trong giao diện tiếp nhận đơn hàng. Khách chọn Mua 10 ly – bốc thăm trúng thưởng có thể chờ cấp lượt chơi trên trang hoàn tất.

Đơn hàng lưu tên sản phẩm, cỡ ly, đá, độ ngọt, topping, đơn giá, số lượng và mức giảm giá tại thời điểm đặt. Việc sửa thông tin sản phẩm về sau không trực tiếp ghi đè các đơn đã tạo. Nếu ưu đãi hoặc tài khoản thay đổi trước khi gửi đơn, báo giá cũ mất hiệu lực và phải xác nhận lại.

### Thanh toán và thông tin hóa đơn

Đơn thông thường hỗ trợ tiền mặt và chuyển khoản ngân hàng. Khu vực chuyển khoản hiển thị các tài khoản nhận tiền đã được kích hoạt trong trang quản trị cùng hình QR; nhân viên vẫn phải đối chiếu tiền thực tế đã vào tài khoản.

Khi chọn cần hóa đơn, tên công ty, mã số thuế, địa chỉ, số điện thoại và Email là thông tin bắt buộc; người đại diện công ty là thông tin không bắt buộc. Chức năng này lưu dữ liệu cần để xuất hóa đơn; việc phát hành hóa đơn điện tử vẫn do cửa hàng thực hiện sau đó.

## 4. Lựa chọn ưu đãi và quy tắc tính giá

Mỗi đơn thông thường hoặc đơn nhóm chỉ được chọn một ưu đãi. Có tổng cộng bốn phương án gồm ba loại ưu đãi và “Không dùng ưu đãi”.

| Lựa chọn | Xử lý đơn hàng hiện tại | Lượt chơi sau đó |
| --- | --- | --- |
| Không dùng ưu đãi | Thanh toán theo giá sản phẩm và topping | Đơn này không được cấp lượt chơi theo chương trình mua 10 ly |
| Mua 4 tặng 1 | Cứ đủ 5 ly thì được giảm phần giá đồ uống cơ bản, không giảm giá topping | Không đồng thời cấp lượt chơi mua 10 ly |
| Mua 10 ly – bốc thăm trúng thưởng | Không giảm số tiền của đơn hiện tại; cấp 1 lượt chơi sau khi đáp ứng điều kiện số ly và hoàn tất đơn | Mỗi đơn đủ điều kiện tối đa 1 lượt |
| Phiếu ưu đãi trúng thưởng cá nhân | Dùng một phiếu còn hiệu lực thuộc ví hiện tại và giảm giá theo điều kiện của phiếu | Không đồng thời cấp lượt chơi mua 10 ly |

Với đơn thông thường, Mua 4 tặng 1 chọn những ly có giá đồ uống cơ bản thấp nhất theo số ly được miễn. Với đơn nhóm, lấy giá đồ uống cơ bản thấp nhất của cả nhóm nhân với số ly miễn phí, sau đó giới hạn mức giảm trong số tiền trưởng nhóm phải trả cho phần tự mua và phần thanh toán thay; không giảm vào khoản của các thành viên tự thanh toán khác.

Chỉ trưởng nhóm được chọn ưu đãi cho đơn nhóm. Khi chốt nhận món, ưu đãi và báo giá được cố định; sau khi xóa thành viên chưa thanh toán, hệ thống tính lại theo các quy tắc đã đóng băng ban đầu. Nếu số ly giảm xuống dưới 10 ly, điều kiện Mua 10 ly – bốc thăm trúng thưởng trước đó cũng được xét lại.

Phiếu ưu đãi trúng thưởng chỉ được ghi nhận đã sử dụng khi đặt hàng thực sự thành công. Phiếu không đáp ứng điều kiện, đã hết hạn, đã sử dụng hoặc không thuộc người dùng hiện tại không thể đổi. Nếu đơn thông thường có phiếu hợp lệ nhưng không có khoản tiền được giảm, phiếu được giữ lại và người dùng phải chọn lại. Với đơn nhóm, nếu cả phần trưởng nhóm tự mua và phần thanh toán thay đều bằng không, hệ thống không áp dụng hoặc tiêu hao phiếu ưu đãi; sau khi tất cả những người có khoản phải thanh toán dương đã được xác nhận, vẫn có thể gửi đơn theo giá gốc và không đồng thời cấp lượt bốc thăm. Các phiếu giảm một số tiền cố định đã phát trước đây giữ nguyên quy tắc sử dụng ban đầu.

## 5. Tạo nhóm và cùng chọn món

### Tạo nhóm và mời tham gia

Trang đặt hàng theo nhóm sử dụng ba bước: tạo đơn nhóm, chia sẻ giỏ hàng, thanh toán chung／thanh toán riêng. Trưởng nhóm có thể chỉ mở nhóm mà không tự đặt đồ uống; cũng có thể sao chép giỏ hàng thông thường vào nhóm mới.

- Mỗi nhóm được giữ trong 4 giờ kể từ lúc tạo; mở lại hoặc thay lời mời không kéo dài thời hạn.
- Mỗi nhóm tối đa 50 người, 200 dòng món; mỗi dòng sản phẩm có 1–20 phần.
- Cùng một trình duyệt được tạo tối đa 20 nhóm trong 24 giờ.
- Gửi lại cùng yêu cầu tạo nhóm sẽ sử dụng lại nhóm đó và cùng thời điểm hết hạn.
- Danh tính trưởng nhóm và thành viên được xác định theo thiết bị trình duyệt ban đầu, được xử lý riêng với đăng nhập thành viên nền tảng.
- Thành viên tham gia qua lời mời bằng một biệt danh duy nhất trong nhóm, không cần đăng ký thành viên trước.
- Thiết bị đã tham gia khi mở lại lời mời sẽ trở về nhóm của mình.

### Liên kết và QR

Đối với nhóm đang mở và chưa hết hạn, chỉ trưởng nhóm có quyền truy cập giao diện liên kết chia sẻ, hình QR, tải xuống và sao chép. QR được tạo trực tiếp trong trình duyệt, không phụ thuộc dịch vụ QR bên ngoài.

Thiết bị hỗ trợ chia sẻ hình ảnh có thể chia sẻ QR và nội dung mời; các thiết bị khác chia sẻ bằng URL, và có thể sao chép liên kết nếu không có chức năng chia sẻ của hệ thống. Sau khi trưởng nhóm thay lời mời, liên kết và QR cũ mất hiệu lực ngay, các thành viên hiện có vẫn được giữ lại. Sau khi chốt nhận món, hủy, gửi đơn hoặc hết hạn, khu vực mời không còn được cung cấp.

Lời mời là điểm truy cập để tham gia, không cấp quyền quản lý của trưởng nhóm. Thành viên có thể xem biệt danh, món và thông tin thanh toán; số điện thoại của trưởng nhóm, địa chỉ giao hàng, thông tin hóa đơn và mã nhận diện thiết bị không nằm trong phản hồi trạng thái dành cho thành viên.

### Chọn món và đồng bộ

Mỗi thành viên có thể chỉnh sửa toàn bộ lựa chọn của món mình đặt. Trưởng nhóm có thể điều chỉnh số lượng hoặc xóa món của người khác. Có ít nhất một món thì hệ thống tự hiển thị đã chọn xong; xóa món cuối cùng sẽ trở lại trạng thái đang chọn. Không cần bấm riêng nút “Tôi đã chọn xong”, và cũng không bắt buộc mọi người tham gia đều phải mua hàng mới được chốt nhận món.

Trang đơn nhóm đang hiển thị cập nhật trạng thái khoảng mỗi 5 giây; việc đồng bộ tạm dừng khi trang ở nền. Các cập nhật thông thường giữ nguyên thẻ đang mở và nội dung đang chỉnh sửa; những thay đổi trạng thái như chốt nhận món hoặc hết hạn vẫn do máy chủ xác định.

## 6. Xác nhận thanh toán và gửi đơn nhóm chính thức

### Quy trình chính

```mermaid
flowchart TD
  A["Trưởng nhóm tạo nhóm và chia sẻ"] --> B["Thành viên tham gia và chọn món"]
  B --> C["Trưởng nhóm chọn ưu đãi và cách thanh toán"]
  C --> D["Chốt nhận món, cố định món và số tiền"]
  D --> E{"Cách thanh toán"}
  E -->|Thanh toán chung| F["Trưởng nhóm xác nhận khoản mình phải trả"]
  E -->|Thanh toán riêng| G["Liệt kê riêng nghĩa vụ thanh toán của từng người"]
  G -->|Tự chuyển khoản hoặc tiền mặt| H["Thành viên báo đã thanh toán"]
  H --> I["Trưởng nhóm đối chiếu và xác nhận đã thanh toán"]
  G -->|Trưởng nhóm tự mua và thanh toán thay| P["Khoản tiền dương được gộp vào nghĩa vụ của trưởng nhóm"]
  P --> F
  F --> J["Tất cả khoản phải thanh toán dương đã được xác nhận"]
  I --> J
  J --> K["Trưởng nhóm điền thông tin giao hàng và gửi đơn"]
  K --> L["Tạo một đơn hàng chính thức"]
  L --> M["Cửa hàng tiếp nhận, giao hàng và hoàn tất"]
```

Các trạng thái chính của đơn nhóm là open, locked, submitted; ngoài ra có thể hủy. Thời hạn được xác định theo thời điểm tạo. Thanh toán riêng vẫn tạo một đơn hàng chính thức cho cửa hàng, giữ tên từng thành viên trong các dòng món và lưu bản chụp dữ liệu chia tiền.

### Nghĩa vụ thanh toán

| Trường hợp | Nghĩa vụ thanh toán | Cách xác nhận |
| --- | --- | --- |
| Thanh toán chung | Trưởng nhóm chịu khoản thanh toán của cả nhóm | Trưởng nhóm xác nhận khoản mình phải trả |
| Thành viên tự chuyển khoản hoặc trả tiền mặt | Các món của từng thành viên | Sau khi thành viên báo đã thanh toán, trưởng nhóm đối chiếu và xác nhận |
| Trưởng nhóm thanh toán thay | Gộp vào khoản trưởng nhóm phải trả | Xác nhận cùng khoản thanh toán của trưởng nhóm, không tính trùng |
| Trưởng nhóm tự đặt món | Gộp vào khoản trưởng nhóm phải trả | Trưởng nhóm có thể xác nhận trực tiếp |
| Số tiền phải trả bằng không | Không tạo thêm nghĩa vụ thanh toán | Không yêu cầu xác nhận thanh toán |

Trạng thái tự thanh toán là pending → requested → paid. Thành viên bấm “Xác nhận thanh toán” chỉ chuyển đến chờ trưởng nhóm xác nhận, không được tự xác nhận khoản tiền là đã thanh toán. Trưởng nhóm đối chiếu số tiền hiện tại rồi xác nhận; hệ thống lưu người xác nhận, thời gian và số tiền.

Trưởng nhóm chỉ được kết đơn sau khi tất cả nghĩa vụ thanh toán có số tiền dương đã được xác nhận; trang thanh toán và thao tác gửi đơn cuối cùng đều kiểm tra lại. Trưởng nhóm điền một bộ thông tin khách hàng, giao hàng, ghi chú và hóa đơn; cả nhóm trở thành một đơn hàng chính thức. Gửi lại đơn nhóm đã gửi sẽ trả về đơn hiện có, không tạo đơn thứ hai.

### Xóa thành viên chưa thanh toán sau khi chốt nhận món

Trong thời hạn còn hiệu lực và trước khi gửi đơn chính thức, trưởng nhóm có thể xóa những thành viên khác chưa thanh toán hoặc đang chờ xác nhận. Không thể xóa chính mình hoặc người đã có xác nhận thanh toán còn hiệu lực. Các món của thành viên được xóa cùng lúc, thao tác được ghi lại; thiết bị ban đầu của người đó không thể tham gia lại hoặc thao tác trong nhóm.

Đơn nhóm đã chốt được tính lại theo giá và quy tắc ưu đãi đã đóng băng ban đầu:

1. Giữ nguyên xác nhận thanh toán nếu số tiền không thay đổi.
2. Nếu số tiền thay đổi, xác nhận cũ mất hiệu lực; thành viên phải báo lại theo số tiền mới và trưởng nhóm xác nhận lại.
3. Khi số tiền trưởng nhóm phải trả thay đổi, trưởng nhóm có thể trực tiếp xác nhận lại.
4. Báo giá trên trang thanh toán cũ mất hiệu lực, phải tải lại trang.
5. Khi đã có người báo hoặc được xác nhận thanh toán, không được mở lại hoặc hủy để tránh thay đổi nội dung đã thanh toán.

## 7. Tài khoản thành viên của nền tảng và luồng dữ liệu iPOS

### Tài khoản trên nền tảng

Thành viên đăng ký và đăng nhập bằng số điện thoại Việt Nam cùng mật khẩu của nền tảng. Số điện thoại được chuẩn hóa trước; không cho phép đăng ký trùng số điện thoại. Mật khẩu được lưu dưới dạng băm. Đăng ký thành công sẽ tự động đăng nhập và chuyển đến trang thành viên; nếu đăng ký gặp lỗi, người dùng trở lại trang đăng ký riêng.

Thành viên có thể lưu tên và địa chỉ giao hàng, điền sẵn thông tin khi thanh toán, xem 50 đơn hàng gần nhất được tạo trên nền tảng khi mình đã đăng nhập, đồng thời xem số lượt chơi còn dùng được và các phiếu thưởng. Đơn hàng thuộc về thành viên nào được xác định theo trạng thái đăng nhập; các đơn hàng cũ của khách vãng lai có cùng số điện thoại không tự động được gộp vào tài khoản.

Các giới hạn hiện tại gồm mật khẩu dài 8–72 byte, 6 lần đăng ký mỗi giờ cho mỗi IP, giới hạn đăng nhập trong 15 phút là 30 lần cho mỗi IP và trong 15 phút là 10 lần cho mỗi số điện thoại. Đăng nhập, đăng xuất và đổi tài khoản sẽ cập nhật trạng thái đăng nhập, đồng thời xóa lựa chọn phiếu ưu đãi riêng của người dùng trước.

### Chuyển phần thưởng của khách vãng lai vào tài khoản thành viên

Khách vãng lai vẫn có thể đặt hàng và nhận lượt chơi. Phần thưởng được lưu trong ví phía máy chủ gắn với trình duyệt ban đầu. Sau khi đăng nhập tài khoản thành viên bằng cùng trình duyệt, các lượt còn dùng được, lịch sử chơi và phiếu thưởng được chuyển một lần vào ví của thành viên đó. Việc chuyển được ghi nhận và số dư của ví ban đầu được đưa về không.

Thành viên đăng nhập cùng tài khoản trên thiết bị khác có thể sử dụng cùng ví và tiếp tục ván chơi của mình. Ván bài chưa nhận thưởng của khách vãng lai không được chuyển sang; sau khi đăng nhập, người dùng tiếp tục ván chơi hiện có của thành viên, hoặc bắt đầu ván mới nếu chưa có, mà không bị trừ thêm lượt. Các lượt phát sinh sau này từ đơn hàng chưa hoàn tất được xử lý theo tài khoản đã nhận chuyển ví của đơn hàng ban đầu.

### Nhập dữ liệu một chiều từ iPOS

1. Thành viên tạo tài khoản trên nền tảng trước.
2. Nhân viên gọi điện xác nhận số điện thoại thuộc về chính thành viên, sau đó cho phép nhập dữ liệu bên ngoài trong trang quản trị.
3. Trong trang quản trị, cấu hình quyền truy cập iPOS HUB đã được xác nhận sử dụng được, nguồn dữ liệu cửa hàng và múi giờ của nguồn.
4. Máy chủ chỉ đọc thông tin thành viên và lịch sử giao dịch của thành viên.
5. Tên, ngày sinh, hạng thành viên, điểm và giao dịch tại cửa hàng được hiển thị riêng; tên, địa chỉ, mật khẩu và đơn hàng trên nền tảng được giữ nguyên.
6. Lịch sử giao dịch phải đáp ứng quy tắc nguồn channel／source／sale_type và đúng chủ sở hữu là thành viên đó.
7. Chỉ lưu một lần sau khi đã lấy đầy đủ tất cả các trang dữ liệu; nếu truy vấn thất bại hoặc dữ liệu không đầy đủ, giữ lại bộ dữ liệu đầy đủ và thời điểm cập nhật thành công gần nhất.

Chức năng nhập iPOS mặc định tắt. Giao dịch trên nền tảng, thay đổi thông tin thành viên và phiếu thưởng của trò chơi không được ghi ngược vào iPOS. Hạng thành viên và điểm tại cửa hàng được hiển thị như dữ liệu từ nguồn; hoạt động rút thưởng trên nền tảng sử dụng ví riêng. Số tiền được ghi nhận tại cửa hàng không được coi là kết quả đối soát ngân hàng hoặc tiền mặt.

## 8. Điều kiện Mua 10 ly – bốc thăm trúng thưởng và lối vào từ trang hoàn tất đặt hàng

### Điều kiện cần để được cấp một lượt

Đơn hàng mới phải đồng thời đáp ứng các điều kiện sau; lượt chỉ được cấp sau khi cửa hàng đánh dấu đơn đã hoàn tất:

- Chọn ưu đãi Mua 10 ly – bốc thăm trúng thưởng.
- Có ít nhất 10 ly đồ uống trong cùng một đơn hàng chính thức.
- Không đồng thời sử dụng Mua 4 tặng 1, mã phiếu thưởng hoặc khoản giảm giá khác.
- Đơn hàng được đặt thành done trong trang quản trị.
- Đơn hàng đó chưa được xử lý cấp lượt.

Mỗi đơn được cấp tối đa 1 lượt: 9 ly không có lượt, 10 ly có 1 lượt và 20 ly vẫn chỉ có 1 lượt. Không cộng dồn giữa các đơn; topping mua riêng không được tính vào số ly đồ uống. Với đơn nhóm, trưởng nhóm nhận lượt của cả đơn, đồng thời hệ thống kiểm tra quyền sở hữu của tài khoản hiện tại hoặc thiết bị ban đầu.

Việc cửa hàng đánh dấu hoàn tất cùng một đơn nhiều lần không làm cấp lượt trùng. Người nhận được xác định theo thành viên hoặc ví khách vãng lai đã lưu trong đơn hàng, không theo tài khoản của nhân viên đang thao tác trang quản trị.

### Từ gửi đơn đến vào trò chơi

```mermaid
flowchart TD
  A["Chọn Mua 10 ly – bốc thăm trúng thưởng và gửi đơn đủ 10 ly"] --> B["Trang hoàn tất đặt hàng hiển thị chờ cửa hàng hoàn tất đơn"]
  B --> C["Cửa hàng đặt đơn thành done"]
  C --> D["Máy chủ kiểm tra điều kiện và cấp 1 lượt"]
  D --> E["Trang hoàn tất của khách ban đầu nhận trạng thái ready"]
  E --> F["Tự động vào minigame trên cùng website"]
  F --> G["Bắt đầu hoặc tiếp tục ván 12 thẻ"]
  G --> H["Hoàn thành toàn bộ 6 cặp"]
  H --> I["Trong cùng giao dịch, trừ 1 lượt và cấp 1 phiếu"]
  I --> J["Thành viên hoặc trình duyệt ban đầu xem phần thưởng"]
  J --> K["Sử dụng phiếu ưu đãi trong đơn tiếp theo đủ điều kiện"]
```

Khi trang hoàn tất đặt hàng vẫn mở và hiển thị ở phía trước, trang truy vấn trạng thái thực tế mỗi 5 giây. Chỉ tự động vào trò chơi sau khi thực sự đã nhận lượt, hoạt động cho phép chơi và giải thưởng đã sẵn sàng. Nếu đã có thể chơi ngay khi tải trang hoàn tất đặt hàng, máy chủ chuyển trực tiếp bằng 303 đến trang trò chơi trên cùng website.

Trang hoàn tất đặt hàng vẫn có nút “Cập nhật lượt chơi” và nút “Vào Mini Game” khi có thể sử dụng. Trang đơn nhóm đã gửi dùng cơ chế đồng bộ hiện có để cập nhật lối vào của trưởng nhóm. Khi trang ở nền, việc chuyển trang được hoãn; hệ thống truy vấn lại khi hiển thị lại ở phía trước hoặc có mạng trở lại.

Các trạng thái gồm chờ, có thể chơi, hoạt động tạm dừng, giải thưởng đang chuẩn bị, lượt đã sử dụng và hủy. Truy vấn chỉ đọc đơn hàng và số dư của người dùng hiện tại, không cấp lượt sớm, bắt đầu ván, trừ lượt hoặc cấp phiếu. Đổi tài khoản sẽ xóa trạng thái riêng của người dùng trước trên trang hoàn tất đặt hàng.

## 9. Trò chơi lật thẻ và nhận thưởng ngẫu nhiên

### Cách vận hành trò chơi

- Mỗi ván có 12 thẻ, 6 cặp; mỗi hình xuất hiện hai lần.
- Máy chủ xáo bài bằng bộ sinh số ngẫu nhiên an toàn và lưu ván chơi, chỉ trả về hình của các lá đã lật hoặc đã ghép đúng.
- Khách lần lượt lật thẻ; hai hình giống nhau được ghép thành cặp, hai hình khác nhau sẽ úp lại sau khoảng 950 mili giây.
- Không giới hạn thời gian chơi; số bước, tốc độ và việc ghép sai không thay đổi xác suất nhận giải.
- Tải lại trang hoặc mất kết nối vẫn có thể khôi phục cùng ván; thành viên có thể tiếp tục trên thiết bị khác.
- Mỗi ví chỉ có một ván có thể khôi phục; sau khi hoàn thành, người dùng phải chủ động sử dụng lượt tiếp theo để bắt đầu ván mới.
- Bắt đầu ván không trừ lượt; chỉ trừ 1 lượt khi đã hoàn thành đủ sáu cặp và cấp phiếu thành công.

Cặp cuối cùng được lưu trước cùng trạng thái trò chơi đã hoàn thành, sau đó giao diện gửi yêu cầu nhận thưởng. Dấu ghi nhận đã nhận thưởng, việc trừ lượt, rút thưởng, lịch sử chơi và cấp phiếu được xử lý trong cùng một giao dịch cơ sở dữ liệu. Nếu cấp phiếu thất bại, giao dịch nhận thưởng được hoàn tác và có thể khôi phục bằng cách thử lại. Yêu cầu nhận thưởng lặp lại trả về cùng kết quả, tránh trừ lượt nhiều lần hoặc cấp phiếu trùng.

Máy chủ xác minh chủ sở hữu ván chơi và số thứ tự thao tác; yêu cầu quá cũ chỉ đồng bộ trạng thái hiện tại. Giới hạn lật thẻ là 240 lần mỗi phút cho mỗi session trình duyệt; vượt giới hạn trả về 429.

### Giải thưởng mặc định

Dưới đây là cấu hình mặc định ban đầu của chương trình. Hoạt động trên website sử dụng cấu hình đã lưu trong trang quản trị và bản chụp cấu hình của ván hiện tại.

| Giải thưởng | Xác suất mặc định | Điều kiện dùng trong đơn tiếp theo | Thời hạn mặc định |
| --- | ---: | --- | --- |
| Miễn phí 1 phần topping chỉ định | 50% | Có ít nhất 1 ly đồ uống trả tiền và chọn thêm topping chỉ định trên đồ uống; chỉ miễn phí 1 phần | 7 ngày |
| Giảm 5,000đ | 35% | Có ít nhất 1 ly đồ uống trả tiền, tổng tiền hàng trước giảm giá đạt 50,000đ | 7 ngày |
| Miễn phí 1 ly đồ uống chỉ định | 15% | Mua 4 ly đồ uống trả tiền, thêm ly tặng đúng sản phẩm và cỡ ly chỉ định, tổng cộng ít nhất 5 ly | 7 ngày |

Đồ uống tặng chỉ được miễn giá đồ uống cơ bản; topping tính riêng. Ly tặng và đồ uống giá bằng không không được tính vào số ly trả tiền bắt buộc. Topping chỉ định phải được thêm vào đồ uống; mua topping riêng không đáp ứng điều kiện.

Thời hạn phiếu thưởng được tính từ lúc nhận. Mỗi phiếu dùng một lần trong đơn tiếp theo. Sản phẩm, cỡ ly và điều kiện sử dụng được lưu trong chính phiếu; thay đổi ở trang quản trị không sửa lại các phiếu đã cấp.

### Thuật toán xác suất

Xác suất có đơn vị nhỏ nhất là 0.01% và được đổi thành trọng số nguyên: 100% tương ứng 10,000. Tổng trọng số của các giải đang bật và có xác suất lớn hơn 0% phải bằng 10,000.

Khi cấp thưởng, máy chủ lấy một số nguyên ngẫu nhiên an toàn trong khoảng 1–10,000, cộng dồn trọng số theo thứ tự trong danh sách giải để chọn một giải. Giải có xác suất 0% hoặc đã tắt không được chọn. Hình ghép, thứ tự lật thẻ và thời gian chơi không ảnh hưởng đến việc rút thưởng.

Khi dùng phiếu thưởng ngẫu nhiên mới trong đơn nhóm, món tặng phải thuộc các món do trưởng nhóm tự mua hoặc thanh toán thay; khoản giảm chỉ trừ vào phần trưởng nhóm trả. Nếu trách nhiệm thanh toán của trưởng nhóm không đủ để áp dụng toàn bộ giá trị phiếu mới, hệ thống giữ lại phiếu, không sử dụng một phần.

## 10. Trang quản trị Mini Game và hình mặt thẻ

### Quy trình cấu hình hoạt động

1. Quản trị viên đăng nhập và bật Mini Game trong trang cài đặt chung.
2. Vào trang quản trị Mini Game để chọn chủ đề.
3. Tải lên hình mặt sau thẻ và tối đa sáu hình mặt trước dùng để ghép cặp, hoặc dùng hình mặc định.
4. Thêm, chỉnh sửa hoặc tắt giải thưởng; thiết lập sản phẩm, cỡ ly và điều kiện sử dụng.
5. Xác nhận tổng xác suất của tất cả giải đang bật bằng 100%, rồi lưu.
6. Dùng tài khoản thử nghiệm có lượt để bắt đầu ván mới, kiểm tra hình, xác suất và điều kiện.

Khi chưa có giải thưởng hợp lệ sẵn sàng, hoạt động hiển thị đang chuẩn bị và giữ lại lượt. Việc lưu cấu hình có kiểm tra phiên bản; trang cũ không thể ghi đè trực tiếp cấu hình mới của quản trị viên khác.

### Các trường có thể cấu hình

| Nhóm | Chức năng và giới hạn |
| --- | --- |
| Chủ đề có sẵn | Ký ức tuổi thơ, Vườn hương trà, Tiệc trà đêm sao |
| Hình ghép cặp | 1 hình mặt sau thẻ, 6 hình mặt trước; mỗi hình mặt trước xuất hiện hai lần trong 12 thẻ |
| Định dạng hình | PNG, JPEG, WebP; mỗi hình tối đa 2 MiB, mỗi chiều tối đa 4096 px |
| Lưu hình | uploads/minigame/, tên tệp ngẫu nhiên được kiểm soát; hình cũ được giữ để các ván cũ sử dụng |
| Số giải thưởng | Tối đa 12 giải, có thể thêm, xóa hoặc tắt |
| Loại giải thưởng | Topping chỉ định, đồ uống chỉ định, giảm một số tiền |
| Chọn sản phẩm | Sản phẩm hợp lệ, đang bật và có giá dương; chọn cỡ ly đồ uống có bán |
| Tên | Tiếng Việt, tiếng Anh và tiếng Trung, mỗi ngôn ngữ tối đa 160 ký tự; để trống thì tên được tạo theo sản phẩm hoặc số tiền |
| Xác suất | Hai chữ số sau dấu thập phân; tổng các giải đang bật bằng 100% |
| Số tiền giảm | Giải đang bật dùng số nguyên 1–100,000,000 |
| Tổng tiền hàng tối thiểu | 0–100,000,000 |
| Số ly đồ uống trả tiền tối thiểu | 1–100 |
| Số ngày hiệu lực | 1–365 |

Chủ đề, hình, danh sách giải và điều kiện mới được lưu khi bắt đầu ván mới. Ván đang chơi giữ nguyên bản chụp cấu hình ban đầu; phiếu đã cấp giữ nguyên điều kiện ban đầu. Tạm dừng hoạt động sẽ chặn lật thẻ và nhận thưởng nếu chưa được cấp, nhưng giữ lại tiến độ và lượt; kết quả đã cấp thưởng vẫn xem được.

Hình mặt trước tùy chỉnh hiển thị nhãn chung theo ngôn ngữ “圖案／Hình／Picture” kèm số thứ tự. Danh sách ví trong trang quản trị lấy 200 bản ghi gần nhất; số liệu thống kê là tổng trong phạm vi danh sách đó.

### Quá trình phát triển thiết kế mặt thẻ

| Phiên bản hình | Nội dung | Trạng thái |
| --- | --- | --- |
| Mùa đầu tiên v1 | Sáu cảnh gồm đi học, lớp học, giờ ra chơi, ngày hội thể thao, uống hồng trà sau giờ học và tốt nghiệp | Đã lưu sáu hình, bản xem trước và gói hình |
| Thẻ nhỏ v2 | Cặp sách, sách, máy bay giấy, cúp, ly hồng trà và mũ tốt nghiệp; chuyển sang một chủ thể lớn duy nhất | Đã lưu PNG trong suốt và bản xem trước ở kích thước nhỏ |
| Thẻ nhỏ HUIYI v3 | Chân dung bé trai, chân dung bé gái, cửa hàng phong cách chibi, đồ uống, máy bay giấy và Logo | Sáu chủ đề do người dùng chỉ định, đã tạo; đang chờ tải lên trang quản trị |

v3 tiếp tục sử dụng nhân vật HUIYI và phong cách thương hiệu trong tệp đính kèm, làm nổi bật chân dung, nơ đỏ, đường nét cửa hàng và các yếu tố thương hiệu đỏ trắng. Cả sáu hình đều là PNG RGBA trong suốt 1254 × 1254 px, mỗi hình khoảng 0.67–1.28 MiB, đáp ứng giới hạn của trang quản trị. Bản xem trước có đối chiếu ở 40, 50, 70 px và mô phỏng màn hình điện thoại 360 px với 4 cột, 12 thẻ.

- Gói sáu hình HUIYI mới nhất：`designs/game-cards/S01-huiyi-small-v3-6-cards.zip` (tệp cục bộ)
- Xem trước thẻ nhỏ HUIYI：`designs/game-cards/season-01-huiyi-small-v3/index.html` (tệp cục bộ)
- Thứ tự sử dụng hình：`designs/game-cards/season-01-huiyi-small-v3/README.md` (tệp cục bộ)
- Câu lệnh mô tả thiết kế：`designs/game-cards/season-01-huiyi-small-v3/prompts.json` (tệp cục bộ)

Mặt bài là hình ảnh phục vụ trò chơi ghi nhớ và ghép cặp, được cấu hình riêng với nội dung giải thưởng và xác suất. Sáu hình không đại diện cho sáu loại giải thưởng.

## 11. Sản phẩm và trang quản trị nhận đơn

### Sản phẩm và cài đặt chung

Đồ uống có thể quản lý tên bằng ba ngôn ngữ, danh mục, đơn giá hoặc giá L／XL, hình sản phẩm, cấu hình đá và độ ngọt, topping gợi ý, thứ tự hiển thị, trạng thái bật, nhãn mới và nhãn phổ biến. Hình sản phẩm chấp nhận JPEG, PNG, WebP, tối đa 5 MiB.

Topping có thể quản lý tên và mô tả bằng ba ngôn ngữ, giá thêm vào đồ uống, có bán riêng hay không, giá bán riêng, hình, thứ tự và trạng thái. Danh mục có thể thêm, chỉnh sửa, sắp xếp và tắt; chỉ danh mục không có sản phẩm mới được xóa. Nhãn và lựa chọn bằng ba ngôn ngữ của mức đá I1–I4 và độ ngọt S1–S5 có thể chỉnh sửa.

Cài đặt hệ thống gồm tên cửa hàng bằng ba ngôn ngữ, tiền tệ, chủ đề thường／đỏ sẫm, banner trang chủ và liên kết sản phẩm, hiển thị số lượng đã đặt, mã và công tắc Mua 4 tặng 1, công tắc Mini Game, Email và âm báo nhận đơn. Tài khoản nhận tiền có thể thêm, chỉnh sửa, sắp xếp, bật, tắt, xóa và thay hình QR.

### Xử lý đơn hàng

Trang quản trị có bốn trạng thái new, accepted, done, cancelled. Quy trình thông thường là đơn mới → nhận đơn → hoàn tất, hoặc hủy khi cần. Giao diện hiện cho phép chọn trực tiếp cả bốn trạng thái, không bắt buộc mỗi lần chỉ được chuyển sang bước kế tiếp.

Trang chi tiết đơn có thông tin khách hàng, món, topping, ghi chú, thanh toán, người đặt và phân chia thanh toán trong đơn nhóm, thông tin hóa đơn, điều phối giao hàng và trạng thái Email. Khi đặt đơn thành hoàn tất, hệ thống xử lý cấp lượt một lần theo điều kiện Mua 10.

Danh sách của bản quản trị đầy đủ lấy 200 đơn gần nhất mỗi lần. Bản điện thoại lấy 120 đơn gần nhất mỗi lần, có lọc trạng thái, thẻ đơn hàng, nhận đơn bằng một thao tác, trang chi tiết, gọi điện và điều phối cho cửa hàng. Hai trang quản trị dùng chung tài khoản quản trị, cơ sở dữ liệu và đơn hàng.

## 12. Quản lý cửa hàng và quy trình điều phối giao hàng

Trang quản trị cửa hàng quản lý tên, địa chỉ, điện thoại, liên kết Google Maps, vĩ độ, kinh độ và việc có nhận điều phối giao hàng hay không. Cửa hàng hợp lệ, đang bật và có vị trí đầy đủ có thể tham gia đề xuất theo khoảng cách.

1. Giao dịch lưu đơn thông thường hoặc đơn nhóm vào cơ sở dữ liệu được hoàn tất thành công trước.
2. Khi bật định vị địa chỉ, hệ thống truy vấn vị trí của địa chỉ giao hàng.
3. Tính khoảng cách đường thẳng giữa các cửa hàng hợp lệ đang bật để đề xuất cửa hàng gần nhất.
4. Nhân viên áp dụng đề xuất, chọn thủ công cửa hàng khác đang bật hoặc xóa phân công.
5. Lưu người thao tác, thời gian cùng bản chụp tên và địa chỉ cửa hàng tại thời điểm đó.
6. Nếu cửa hàng được đổi tên hoặc tắt sau này, lịch sử phân công vẫn giữ dữ liệu tại thời điểm phân công.

Cửa hàng gần nhất chỉ là tham khảo để điều phối, không tính khoảng cách theo đường đi, thời gian di chuyển hoặc cam kết thời điểm giao đến. Khi không có khóa API, địa chỉ không rõ, không có kết quả hoặc dịch vụ thất bại, đơn hàng vẫn được tạo và có thể phân công thủ công. Cửa hàng đang bật nhưng chưa có tọa độ cũng có thể được chọn thủ công.

Tọa độ lấy từ truy vấn Google được lưu tạm 30 ngày và phải lấy lại khi hết hạn. Vị trí nhập thủ công hoặc đăng ký bằng liên kết vị trí hợp lệ được giữ theo quy tắc của nguồn đó. Cập nhật đề xuất không ghi đè cửa hàng đã được phân công thủ công. Khi nhiều người cùng điều phối, hệ thống kiểm tra phiên bản để tránh ghi đè; đơn đã hoàn tất hoặc hủy không còn cho phép thay đổi điều phối.

## 13. Thông báo tiếp nhận đơn hàng và PWA

### Email

Có thể cấu hình tối đa 10 người nhận sau khi loại bỏ địa chỉ trùng lặp và gửi email thử nghiệm. Thông báo cho đơn hàng đã gửi chính thức bao gồm thông tin khách hàng, món, thanh toán và chi tiết chia tiền của đơn nhóm; hệ thống lưu trạng thái thành công, thành công một phần hoặc thất bại, số lần thử, thời gian và thông tin chẩn đoán. Trang đơn hàng của giao diện quản trị đầy đủ cho phép gửi lại email.

Thông báo được xử lý sau khi gửi đơn; việc gửi thất bại không hoàn tác đơn hàng đã tạo thành công. Những thay đổi trong giỏ hàng nhóm trước khi gửi đơn không tạo thông báo đơn mới cho từng lần thay đổi.

### Âm thanh khi trang ở phía trước

Khi trang quản trị đang hiển thị ở phía trước, có thể dùng âm thanh mặc định hoặc âm thanh tải lên, với thời lượng tính bằng giây, phút hoặc phát liên tục cho đến khi tiếp nhận đơn. Nhân viên phải nhấn để bật âm thanh trước; lời nhắc đơn mới dừng sau khi đơn được tiếp nhận.

### Thông báo đẩy hệ thống

Web Push hỗ trợ xác minh đăng ký thiết bị, bật/khôi phục, tắt, thử nghiệm từ máy chủ, thử nghiệm có độ trễ 15 giây và chẩn đoán. Giao diện quản trị đầy đủ và giao diện di động sử dụng phạm vi Service Worker riêng.

Đơn mới trước tiên tạo tác vụ gửi và thực hiện lần gửi đầu. Khi gặp lỗi tạm thời, tác vụ được giữ trong hàng đợi để bin/process-push-queue.php chạy theo lịch và thử lại, tối đa bốn lần thử; thông báo có hiệu lực một giờ. Mã 404/410 sẽ vô hiệu hóa đăng ký không còn hợp lệ. Khi đơn đã được tiếp nhận, hủy, hoàn tất hoặc thông báo hết hạn, hệ thống không gửi bù lời nhắc đơn mới.

“Dịch vụ đẩy đã chấp nhận”, “điện thoại đã xử lý thông báo” và “nhân viên đã tiếp nhận đơn” là các trạng thái khác nhau. Âm thanh và cách hiển thị trên màn hình khóa do hệ điều hành điện thoại kiểm soát; chức năng phát liên tục MP3 tùy chỉnh dùng cho trang tiếp nhận đơn đang ở phía trước. Việc thông báo đến thiết bị thật và lịch chạy trên máy chủ hoạt động đúng thuộc phạm vi nghiệm thu triển khai.

### Thêm vào màn hình chính và bộ nhớ đệm

Giao diện khách hàng, quản trị đầy đủ và quản trị di động cung cấp khả năng PWA để thêm vào màn hình chính. Giao diện khách hàng lưu đệm tài nguyên tĩnh; trang đăng nhập, trang đơn hàng và API riêng tư không lưu đơn hàng vào bộ nhớ đệm ngoại tuyến. Việc đặt đơn và cập nhật trạng thái thời gian thực vẫn cần kết nối mạng.

## 14. Cài đặt, cập nhật và bảo vệ dữ liệu

### Cài mới với cơ sở dữ liệu shop trống

Trình cài đặt dành cho cơ sở dữ liệu riêng của shop thực sự trống; trình cài đặt tạo cấu hình kết nối riêng tư và tài khoản quản trị, đồng thời đặt múi giờ Việt Nam Asia/Ho_Chi_Minh. Yêu cầu tối thiểu gồm PHP 8.1, PDO MySQL và session; các thư mục tải lên liên quan phải cho phép ghi.

Các biện pháp bảo vệ khi cài đặt gồm CSRF riêng, xác thực biểu mẫu, mật khẩu quản trị ít nhất 8 ký tự, khóa tệp của website, từ chối cơ sở dữ liệu không trống và khóa cài đặt khi config.local.php đã tồn tại. Sau khi hoàn tất, xóa điểm truy cập cài đặt cùng tệp SQL, rồi tạo danh mục, sản phẩm, topping, tài khoản nhận tiền, cửa hàng và phần thưởng trò chơi.

### Cập nhật website shop hiện có

Khi kiểm tra sự cố ngày 2026-10-07, cơ sở dữ liệu shop đã có 27 bảng cùng sản phẩm và đơn hàng. Website này áp dụng quy trình cập nhật website hiện có, giữ dữ liệu và cấu hình riêng tư.

Hợp nhất gói cập nhật theo đúng các đường dẫn ban đầu vào /home/huiyitea/shop.huiyitea.vn, để index.php, actions/, lib/, admin/, minigame/ cùng nằm trong một thư mục gốc. Không tạo thêm thư mục mang tên ZIP và không đặt toàn bộ gói cập nhật chỉ vào minigame/.

Giữ config.php, các cấu hình riêng tư .local.php, uploads/, biểu tượng thương hiệu và .htaccess ở thư mục gốc; cơ sở dữ liệu hiện có dùng quy trình nâng cấp tự động. Các thao tác thử nghiệm và cài đặt shop sử dụng thư mục và cơ sở dữ liệu shop; website chính thức order được xử lý theo kế hoạch riêng.

### Ghi nhận việc sửa quyền truy cập trong gói cập nhật

Gói cập nhật 19.9.1 đợt đầu lưu quyền tệp là 0600. Sau khi giải nén, CSS/JavaScript công khai trả về 403, khiến giao diện mất định dạng. Đã sửa 32 tệp tĩnh công khai trên shop và tạo lại gói cập nhật 19.9.1, trong đó cả 163 tệp thông thường đều được đặt rõ quyền 0644.

Tài nguyên công khai thường dùng quyền 0644, thư mục dùng 0755. Cấu hình riêng tư giữ quyền ban đầu; xử lý theo mục đích của từng tệp, không áp dụng đồng loạt quyền công khai cho toàn website.

### Tài khoản và dữ liệu

Đăng nhập quản trị và đăng nhập thành viên được tách riêng. Mật khẩu được xác minh bằng hàm băm; session được cập nhật khi đăng nhập. Biểu mẫu sửa dữ liệu kiểm tra CSRF. Đơn hàng thành viên, ví trò chơi và thao tác đặt nhóm được kiểm tra theo quyền sở hữu tương ứng.

Khóa cho thông báo đẩy, nhập dữ liệu thành viên và bản đồ được lưu trong cấu hình riêng tư; gói cập nhật không chứa thông tin xác thực thật. Hình tải lên được kiểm tra định dạng thực tế; thư mục ảnh trò chơi hạn chế thực thi mã. Trạng thái nhạy cảm sử dụng private/no-store.

## 15. Ghi nhận các phiên bản phát triển chức năng

Các phiên bản được sắp xếp theo tiến trình phát triển chức năng. Bảng dưới giữ nội dung đã hoàn thành của từng phiên bản; quy tắc vận hành hiện hành căn cứ vào các chương phía trên.

| Phiên bản | Nội dung hoàn thành |
| --- | --- |
| 19.1 | Sửa phần lõi thông báo tiếp nhận đơn; xác minh đăng ký thiết bị, phạm vi Worker chính xác, thông báo đẩy mã hóa, hàng đợi thử lại, chẩn đoán và chuông khi trang ở phía trước |
| Dòng 19.2 | Phần lõi đặt nhóm, giỏ hàng chung và trưởng nhóm gửi đơn |
| 19.2.1 | Thời gian giữ nhóm cố định là 4 giờ kể từ khi tạo; đồng bộ hướng dẫn ba ngôn ngữ và thời hạn |
| 19.2.2 | QR mời, chia sẻ và tải xuống, đồng bộ khi thay liên kết mời |
| 19.3.0 | Thanh toán chung/tách riêng, tiền mặt/chuyển khoản/trưởng nhóm thanh toán thay, bản chụp trạng thái thanh toán và trách nhiệm ưu đãi của trưởng nhóm |
| 19.3.1 | Nội dung hướng dẫn ba bước; tự động đánh dấu đã chọn xong khi giỏ hàng có món |
| 19.3.2 | Rút gọn hướng dẫn ba ngôn ngữ ở giao diện khách hàng, thống nhất thuật ngữ thanh toán |
| 19.4.0 | Thành viên riêng của nền tảng, xác nhận số điện thoại thủ công, nhập dữ liệu một chiều từ iPOS, đơn hàng nền tảng và điền sẵn thông tin thanh toán |
| 19.5.0 | Trưởng nhóm xác nhận sau khi thành viên báo thanh toán; chỉ gửi đơn khi tất cả khoản thanh toán được xác nhận; loại thành viên chưa thanh toán và tính lại giá |
| 19.5.1 | Trang đăng ký riêng, chuyển về khi có lỗi và tự động đăng nhập sau khi đăng ký thành công |
| 19.6.0 | Mỗi đơn chỉ chọn một ưu đãi, ví thưởng thành viên, sử dụng qua nhiều thiết bị và chuyển ví khách sang thành viên một lần |
| 19.7.0 | Quản lý cửa hàng, gợi ý cửa hàng gần nhất, phân công giao đơn thủ công, lịch sử thay đổi và cấu hình bản đồ riêng tư |
| 19.8.0 | Tích hợp trò chơi lật thẻ từ tệp đính kèm, điều kiện mua 10, 6 cặp hình, ba loại phiếu thưởng ngẫu nhiên và sử dụng ở đơn tiếp theo |
| 19.8.1 | Giá L/XL trên trang chủ được tách dòng, căn phải, căn thẳng số tiền và điều chỉnh phiên bản bộ nhớ đệm |
| 19.9.0 | Ba chủ đề trò chơi, tải lên mặt sau và sáu hình thẻ, cấu hình phần thưởng và xác suất, lưu bản chụp cấu hình khi bắt đầu lượt |
| 19.9.1 | Trang hoàn tất đọc trạng thái cấp lượt thực tế mỗi 5 giây, tự động chuyển vào trò chơi, lối vào dành cho trưởng nhóm; sửa quyền tệp trong gói cập nhật |
| Bộ hình v1 đến v3 | Cảnh trường học mùa đầu → thẻ nhỏ một vật thể → sáu mẫu thẻ HUIYI mới nhất theo nhân vật và thương hiệu |

### Ghi nhận môi trường và bàn giao ngày 2026-10-07

- Xác nhận order và shop đã tách cơ sở dữ liệu; các thử nghiệm tiếp theo ưu tiên shop.
- Cung cấp hướng dẫn cài shop trên cơ sở dữ liệu trống; khi kiểm tra website hiện có, xác nhận cơ sở dữ liệu đã chứa dữ liệu nên chuyển sang xử lý quyền kết nối và cập nhật.
- Bổ sung quyền cơ sở dữ liệu shop theo sự cho phép của người dùng; website và trang đăng nhập quản trị hoạt động trở lại.
- Hoàn thành mã tự động vào trò chơi 19.9.1 và xác minh trên môi trường cục bộ.
- Sửa quyền tệp định dạng công khai sau khi người dùng cập nhật; xác nhận thẻ sản phẩm hiển thị L/XL tách dòng, căn phải trở lại.
- Tạo ba phiên bản hình thẻ trò chơi; v3 mới nhất hoàn thành theo sáu chủ đề: bé trai, bé gái, cửa hàng chibi, đồ uống, máy bay giấy và Logo.
- Tổng hợp chức năng hiện có, quy trình thao tác, phiên bản, kiểm chứng và công việc bảo trì để lập nhật ký phát triển này.

## 16. Ghi nhận kiểm chứng

Dưới đây là kết quả kiểm thử cuối cùng được giữ lại theo từng phiên bản, không cộng gộp giữa các phiên bản; số lần chạy lại cũng không được tính thành phạm vi kiểm thử mới. Trong lần biên soạn nhật ký này, không tạo lại đơn hàng thử nghiệm.

| Phạm vi và phiên bản | Kiểm chứng đã ghi nhận | Báo cáo |
| --- | --- | --- |
| Chọn món nhóm 19.3.1 | MariaDB/giao diện 154, HTTP 53, trình duyệt 24 | [Chọn món nhóm](GROUP-READINESS-TEST-REPORT.md) |
| Thành viên nền tảng 19.4.0 | Tích hợp 441, hàm thuần thành viên 83; dữ liệu bên ngoài dùng phản hồi mô phỏng | [Thành viên](MEMBER-TEST-REPORT.md) |
| Trưởng nhóm xác nhận thanh toán 19.5.0 | Tích hợp mới 260; gồm tích hợp hiện có là 592, ngoài ra có kiểm tra giá và bố cục | [Xác nhận thanh toán](GROUP-APPROVAL-TEST-REPORT.md) |
| Lối vào đăng ký 19.5.1 | Biểu mẫu/chuyển trang 76, giao diện 28, tổng 104 | [Cập nhật đăng ký](UPDATE-19.5.1.md) |
| Ưu đãi và ví thành viên 19.6.0 | Tích hợp mới 405, giao diện 126; kiểm thử hồi quy tương thích về sau ghi riêng 406 | [Ưu đãi](PROMOTION-TEST-REPORT.md), Hồi quy trò chơi：`MINIGAME-TEST-REPORT.md` (tệp cục bộ) |
| Phân công đơn cho cửa hàng 19.7.0 | Kiểm thử mã chức năng mới: 410; giao diện ba ngôn ngữ: 399; tổng: 809 | [Phân công đơn](DISPATCH-TEST-REPORT.md) |
| Lật thẻ 19.8.0 | Trò chơi 144, hàm thuần phần thưởng 45, giao diện khách hàng 6 tình huống | Lật thẻ：`MINIGAME-TEST-REPORT.md` (tệp cục bộ) |
| Quản trị trò chơi 19.9.0 | Điều kiện/lật thẻ 143, phần thưởng 69, cấu hình quản trị/tải lên 119 | Cấu hình quản trị：`GAME-ADMIN-TEST-REPORT.md` (tệp cục bộ) |
| Tự động vào trò chơi 19.9.1 | Trực tiếp 47, HTTP 92, tổng 139; 17 kiểm tra DOM bằng mã giao diện thực tế | Tự động vào trò chơi：`GAME-ENTRY-TEST-REPORT.md` (tệp cục bộ) |
| Thông báo tiếp nhận đơn 19.1 | Cú pháp, chữ ký mã hóa, truyền tải mô phỏng, sự kiện Worker và giao diện | [Thông báo](NOTIFICATION-TEST-REPORT.md) |
| Gói cập nhật 19.9.1 | Phiên bản, hàm băm nguồn, nội dung ZIP và quyền 0644 của 163 tệp | Hướng dẫn cập nhật：`UPDATE-19.9.1.md` (tệp cục bộ) |
| Hình thẻ HUIYI v3 | Kích thước sáu hình, alpha thực tế, dung lượng tệp, tính toàn vẹn của ZIP và tham chiếu trong trang xem trước | Hướng dẫn bộ hình：`designs/game-cards/season-01-huiyi-small-v3/README.md` (tệp cục bộ) |

Kết quả 144 của 19.8.0 và 143 của 19.9.0 là chênh lệch số kiểm tra HTTP của cùng bộ tình huống lật thẻ khi số mặt thẻ đã lộ ban đầu khác nhau, không có nghĩa là chức năng bị giảm. Các kiểm chứng cục bộ nêu trên và việc mở website/sửa giao diện đã xác nhận trên shop được ghi nhận riêng. Tiền thực tế vào ngân hàng, quyền truy cập iPOS, định vị Google và thông báo trên thiết bị thật được nghiệm thu theo phạm vi triển khai tương ứng.

## 17. Công việc bảo trì còn lại và vòng nghiệm thu tiếp theo

### Các hạng mục bảo trì đã đối chiếu

1. **Quyền tài nguyên công khai của gói cài đặt 19.9.0**: cả 163 tệp trong ZIP shop-install hiện được lưu với quyền 0600, bao gồm assets/css/app.css và assets/js/app.js. Trước khi cung cấp lại gói cài mới, cần đặt lại quyền theo mục đích của tài nguyên công khai và cấu hình riêng tư, đồng thời xác minh sau khi giải nén. Gói cập nhật 19.9.1 đã sửa thành 0644; trạng thái của hai gói khác nhau.
2. **Nội dung email thông báo đơn nhóm**: email dạng văn bản thuần và HTML vẫn có lời nhắc cố định “trạng thái thanh toán do thành viên tự báo”. Cần sửa nội dung để phù hợp quy tắc hiện tại “thành viên báo, trưởng nhóm xác nhận”; bản thân quy trình xác nhận thanh toán hiện hành đã được triển khai trong mã.
3. **Tính nhất quán của tài liệu phiên bản**: một số tài liệu cập nhật lịch sử giữ mô tả cơ sở dữ liệu dùng chung và chưa triển khai tại thời điểm đó. Môi trường hiện tại căn cứ vào cập nhật 2026-10-07 của AGENTS.md và nhật ký này. Nội dung phiên bản 19.9.0 còn sót ở phần cuối README cần được thống nhất.
4. **Bàn giao gói cài đặt phiên bản mới**: gói cập nhật mã mới nhất hiện là 19.9.1, còn gói cài trên cơ sở dữ liệu trống là 19.9.0. Các biện pháp bảo vệ trong mã nguồn trình cài đặt đã hoàn thành; chưa bàn giao riêng gói cài đặt 19.9.1.

### Thứ tự nghiệm thu chức năng trên shop

- [ ] Dùng gói cập nhật 19.9.1 mới nhất để kiểm tra định dạng giao diện khách hàng, trang sản phẩm, giỏ hàng và quản trị.
- [ ] Cấu hình topping tặng thực tế, cỡ ly đồ uống, phần thưởng được bật, xác suất và số ngày hiệu lực.
- [ ] Tải lên sáu hình thẻ v3, bắt đầu lượt mới để kiểm tra khả năng nhận diện ở kích thước nhỏ trên điện thoại.
- [ ] Cùng một khách hàng gửi đơn 10 ly với ưu đãi Mua 10 ly – bốc thăm trúng thưởng, giữ trang hoàn tất ở phía trước.
- [ ] Cửa hàng đánh dấu chính đơn đó đã hoàn tất, xác nhận khách nhận 1 lượt và vào trò chơi sau khoảng 5 giây.
- [ ] Ghép đủ 6 cặp, xác nhận chỉ trừ 1 lượt và phát 1 phiếu; tải lại trang hoặc thử lại không phát thêm phiếu.
- [ ] Ở đơn tiếp theo đáp ứng điều kiện của phiếu, kiểm tra giảm giá và ghi nhận sử dụng một lần; giữ phiếu khi chưa đủ điều kiện.
- [ ] Kiểm tra lối vào của khách, thành viên, qua nhiều thiết bị và trưởng nhóm; mỗi người chỉ nhận lượt và phiếu thưởng thuộc về mình.
- [ ] Dùng ít nhất hai thiết bị đặt nhóm để kiểm tra chia tiền, báo thanh toán, trưởng nhóm xác nhận, tính lại sau khi loại thành viên và chỉ gửi một đơn.
- [ ] Đối chiếu chuyển khoản/tiền mặt thực tế, email đến người nhận, lịch chạy thông báo và thông báo khi điện thoại ở phía trước/trên màn hình khóa.
- [ ] Khi các dịch vụ bên ngoài thật đã được cấu hình, nghiệm thu quy tắc nguồn iPOS và gợi ý cửa hàng bằng Google.
- [ ] Sau khi nghiệm thu shop, sao lưu và lên kế hoạch riêng để cập nhật website chính thức order.

## 18. Chỉ mục mã nguồn và tài liệu

| Chức năng | Mã nguồn chính |
| --- | --- |
| Sản phẩm và giỏ hàng | index.php, product.php, topping.php, cart.php, actions/add_to_cart.php, actions/update_cart.php |
| Thanh toán và đơn hàng | checkout.php, actions/submit_order.php, success.php, lib/helpers.php |
| Ưu đãi và ví thành viên | lib/promotions.php, lib/promotion_views.php, lib/member_rewards.php, lib/v170.php |
| Đặt nhóm và thanh toán | group/index.php, group/action.php, group/state.php, lib/group_order.php, lib/group_payments.php, lib/group_views.php |
| Thành viên và nhập dữ liệu | member/, admin/members.php, admin/member-settings.php, lib/member.php, lib/member_orders.php, lib/member_sync.php |
| Lối vào trò chơi | lib/game_entry.php, actions/game_status.php, assets/js/game-entry.js |
| Lật thẻ và phiếu thưởng | minigame/, actions/minigame.php, lib/minigame.php, lib/minigame_matching.php, lib/game_rewards.php |
| Quản trị trò chơi và tải lên | admin/game.php, lib/game_config.php, lib/game_reward_admin.php, lib/game_media.php |
| Phân công đơn | admin/stores.php, lib/dispatch.php, lib/dispatch_views.php |
| Tiếp nhận đơn và thông báo | admin/order.php, mobile-admin/, lib/v179.php, lib/push_service.php, lib/push_api.php, bin/process-push-queue.php |
| Cài đặt và môi trường | setup.php, install.sql, lib/bootstrap.php, AGENTS.md, INSTALL-SHOP.md |
| Nội dung email thông báo cần cập nhật | lib/v179.php, dòng 106, 143 |
| Bộ hình thẻ | designs/game-cards/season-01-huiyi-small-v3/ |

- [Giới thiệu dự án](README.md)
- [Quy tắc môi trường triển khai](AGENTS.md)
- Cập nhật 19.9.1 và sửa quyền tệp：`UPDATE-19.9.1.md` (tệp cục bộ)
- Quản trị trò chơi 19.9.0：`UPDATE-19.9.0.md` (tệp cục bộ)
- Hướng dẫn cài đặt shop：`INSTALL-SHOP.md` (tệp cục bộ)
- [Cách tái hiện kiểm thử phát triển](tests/README.md)

Khi bổ sung phiên bản vào nhật ký về sau, cập nhật mốc phiên bản hiện hành, trạng thái triển khai thực tế, thay đổi chức năng, phạm vi kiểm chứng và các việc chưa hoàn thành; giữ nguyên nội dung tại thời điểm của các phiên bản đã phát hành.

---

<a id="traditional-chinese"></a>

# HUIYI 點餐系統開發日誌

[回到越南文](#tieng-viet)

紀錄範圍：本機工作區 19.9.1 程式與既有交付檔案。GitHub 的 `codex/promotions-member-rewards` 分支程式目前為 19.7.0；此次發布雙語開發日誌。尚未同步至分支的檔案，保留路徑並標為「本機檔案」。

更新日期：2026 年 10 月 7 日，越南時間 UTC+7<br>
目前程式版本：19.9.1<br>
手機接單介面版本：1.2<br>
通知核心版本：19.1<br>
最新卡面素材版本：HUIYI 小卡版 v3

目前系統已具備一般點餐、團購分帳、平台會員、優惠擇一、買10抽獎翻牌遊戲、商品與門市後台，以及 Email 和 Web Push 接單通知。最新 19.9.1 補上訂單完成頁的遊戲機會更新與自動入口；顧客必須等店家完成合資格訂單、真正取得機會後，才會進入遊戲。

正式站與測試站已使用分開的資料庫。shop 更新後的公開樣式檔已恢復正常，完整的線上訂單到遊戲領券流程列入下一輪驗收。最新六款 HUIYI 卡面已完成素材與預覽，待上傳至小遊戲後台。

## 一 目前版本與部署狀態

| 項目 | 目前狀態 |
| --- | --- |
| 專案程式 | VERSION 為 19.9.1，包含前版團購、會員、派送、小遊戲與後台設定功能 |
| 正式站 | order.huiyitea.vn 已正式運作；線上版本待核對，正式更新須在 shop 驗收後安排 |
| 測試站 | shop.huiyitea.vn 為後續修改的優先部署與測試目標 |
| 資料庫 | 使用者於 2026-10-07 確認 order 與 shop 已分開；原共用資料庫限制已解除 |
| shop 網站目錄 | /home/huiyitea/shop.huiyitea.vn |
| shop 私有設定 | 資料庫須指向 shop，APP_BASE_URL 須為 https://shop.huiyitea.vn |
| shop 連線修復 | 已補上 huiyitea_shop 帳號對同名資料庫的 ALL PRIVILEGES；首頁與後台登入頁回應 200 |
| shop 介面修復 | 更新後公開 CSS／JavaScript 的 403 已處理；32 個公開靜態檔案已恢復讀取，L／XL 價格分行靠右已確認 |
| 最新程式更新包 | `dist/drink-order-cpanel-v19.9.1-update.zip` (本機檔案)，739,539 bytes，163 個檔案，一般檔案權限 0644 |
| 空白資料庫安裝包 | `dist/drink-order-cpanel-v19.9.0-shop-install.zip` (本機檔案) 已存在；其公開檔案權限仍需修正，見維護待辦 |
| 最新卡面 | 六張 HUIYI 透明 PNG 與離線預覽已完成，尚未上傳後台或設定為線上卡面 |

程式版本、手機介面版本與素材版本各自管理。卡面 v3 表示第三版圖片素材，可搭配後台現有主題上傳使用。

## 二 全站功能總覽

| 模組 | 已完成的功能 | 主要入口 |
| --- | --- | --- |
| 商品瀏覽 | 三語切換、分類、推薦、新品、熱門、訂購數顯示、L／XL 價格 | 首頁 index.php |
| 飲品客製 | 杯型、冰量、甜度、推薦與其他加料、數量 | product.php |
| 單售加料 | 獨立加料商品及單售價格 | topping.php |
| 購物車 | 數量調整、刪除品項、小計、優惠選擇 | cart.php |
| 一般結帳 | 會員資料帶入、現金／轉帳、收款 QR、備註、發票資料 | checkout.php |
| 團購 | 建團、邀請與 QR、共同選購、付款分攤、團主確認、移除未付款團員、一次送單 | group/ |
| 會員 | 電話註冊登入、配送資料、自己的訂單、遊戲機會與獎券、iPOS 唯讀資料 | member/ |
| 優惠 | 不使用、買4送1、買10抽獎、私人獎項券四選一 | 一般購物車及團主頁 |
| 小遊戲 | 12 張牌、6 組配對、伺服器牌局、續玩、隨機發券 | minigame/ |
| 小遊戲後台 | 活動開關、三主題、卡背、六組圖片、獎項與機率 | admin/game.php、admin/settings.php |
| 商品後台 | 商品、分類、加料、冰甜設定、價格、圖片、排序與啟用 | admin/ |
| 接單後台 | 四種訂單狀態、品項、顧客、付款、發票、派送與通知狀態 | admin/、mobile-admin/ |
| 門市與派送 | 門市資料、位置、最近門市建議、人工指派與變更紀錄 | admin/stores.php、訂單詳細頁 |
| 通知 | 多收件人 Email、重寄、前景音效、Web Push、重試佇列與診斷 | 系統設定及兩個接單介面 |
| PWA | 前台與接單介面加入主畫面、靜態資源快取 | 網站 manifest 與 Service Worker |

## 三 一般顧客點餐流程

### 商品與價格呈現

首頁提供越文、英文及中文內容。可依分類瀏覽飲品，查看新品、熱門與推薦品項；可單售的加料另有購買入口。訂購數顯示由後台開關控制。

有杯型商品的 L、XL 各有自己的價格。自 19.8.1 起，首頁推薦卡片與分類卡片以 L 在上、XL 在下的兩行呈現，杯型與金額靠右，金額的右邊緣對齊。單一價格商品維持一行。

### 選購到完成頁

1. 顧客選擇飲品，進入商品頁。
2. 選擇可售杯型、冰量與甜度；依需要選推薦加料或其他加料。
3. 單杯價為飲品本體加上所選加料價格。每次加入數量為 1–20 份。
4. 伺服器重新確認商品啟用狀態、杯型、冰甜選項與加料，將品項保存到此瀏覽器的購物車。
5. 在購物車調整數量或刪除。每列最多 20 份；數量為零時移除該列。
6. 選擇一種優惠，確認小計、折扣與應付金額。
7. 結帳填入姓名、電話、配送地址、付款方式、備註，以及需要時的發票資料。會員可帶入已保存的個人資料，訪客也可下單。
8. 伺服器驗證目前品項、優惠與登入歸屬，於同一資料庫交易保存訂單及品項。
9. 訂單成立後清空一般購物車，顯示完成頁；通知與地址定位在成立後處理。
10. 店家從接單後台處理訂單。選買10抽獎的顧客可在完成頁等待機會發放。

訂單保存當時的商品名稱、杯型、冰甜、加料、單價、數量與折扣。後續修改商品資料不直接改寫已成立訂單。送出前若優惠或帳號改變，舊報價失效，需重新確認。

### 付款與發票資料

一般訂單提供現金與銀行轉帳。轉帳區顯示後台已啟用的收款帳戶及 QR 圖片；實際入帳仍由人員核對。

勾選需要發票時，公司名稱、稅號、地址、電話及 Email 為必填，公司代表為選填。這個功能保存開票所需資料，電子發票開立仍屬店家後續作業。

## 四 優惠選擇與計價規則

每筆一般或團購訂單只能選一種優惠。三種優惠與「不使用優惠」共四個選項。

| 選擇 | 本次訂單處理 | 後續遊戲機會 |
| --- | --- | --- |
| 不使用優惠 | 按商品與加料金額結帳 | 不因本次訂單取得買10機會 |
| 買4送1 | 每滿 5 杯折抵飲品本體，不折抵加料 | 不兼發買10機會 |
| 買10抽獎 | 本次金額不折抵；符合杯數與完成條件後發 1 次機會 | 每筆合資格訂單最多 1 次 |
| 私人獎項券 | 使用一張有效且屬於目前錢包的券，依該券條件折抵 | 不兼發買10機會 |

一般買4送1按可折抵杯數選取價格最低的幾杯飲品本體。團購則以整團最低飲品基本價格乘以免費杯數，再限制於團主的自購及墊付責任金額；其他自行付款團員的款項不扣優惠。

只有團主可為團購選優惠。截止時固定優惠與報價，移除未付款團員後按原凍結規則重新計算；杯數降到 10 杯以下時，原買10抽獎資格亦隨之重新判定。

獎項券於真正下單成功時核銷。不符合條件、已過期、已使用或非本人所有的券，不能兌換。一般訂單有有效券卻沒有可折抵金額時，保留券並要求重新選擇。團購若團主自購與墊付責任均為零，不套用及不消耗優惠券；其他正金額付款人全部確認後，仍可按原價送單，且不兼發抽獎機會。歷史固定金額券保留其原有兌換規則。

## 五 團購建立與共同選購

### 建團與邀請

團購頁使用三個步驟：建立團購單、分享購物車、統一結帳／分開結帳。團主可只開團，自己不訂飲料；也可將一般購物車複製到新團。

- 每團從建立起保留 4 小時，重新開放或更換邀請不延長。
- 每團最多 50 人、200 筆品項；每筆商品 1–20 份。
- 同一瀏覽器 24 小時內最多建立 20 團。
- 重送同一建團請求沿用同一團與同一到期時間。
- 團主及團員身分依原瀏覽器裝置判定，與平台會員登入分別處理。
- 團員透過邀請填入同團唯一暱稱加入，不必先註冊會員。
- 同一已加入裝置再次開啟邀請，返回自己的團購。

### 連結與 QR

開放且未逾時的團購，僅團主可取得分享連結、QR 圖片、下載及複製介面。QR 在瀏覽器本地產生，不依賴外部 QR 服務。

支援圖片分享的裝置可分享 QR 與邀請文字；其他裝置使用網址分享，沒有系統分享能力時可複製連結。團主更換邀請後，舊連結與舊 QR 立即失效，既有團員保留。截止、取消、送出或逾時後不再提供邀請區。

邀請是加入入口，並不授予團主管理權。團員可見暱稱、品項與付款資訊；團主電話、配送地址、發票資料與裝置識別不包含在團員狀態回應。

### 選購與同步

每位團員可完整編輯自己的商品選項。團主可調整他人的數量或移除品項。至少有一項商品即自動顯示已選好，刪除最後一項則回到選購狀態；不需另按「我已選好」，也不要求每位加入者都有購買才可截止。

可見的團購頁約每 5 秒更新狀態，背景暫停同步。一般更新保留已展開卡片與正在編輯的內容；截止或逾時等狀態改變仍由伺服器判定。

## 六 團購付款確認與正式送單

### 主流程

```mermaid
flowchart TD
  A["團主建團並分享"] --> B["團員加入及選購"]
  B --> C["團主選優惠與結帳方式"]
  C --> D["截止收單 固定品項與金額"]
  D --> E{"結帳方式"}
  E -->|統一結帳| F["團主確認自己的應付款"]
  E -->|分開結帳| G["分別列出各人的付款責任"]
  G -->|自行轉帳或現金| H["團員回報付款"]
  H --> I["團主核對並確認已付款"]
  G -->|團主自購及墊付| P["正金額併入團主責任"]
  P --> F
  F --> J["全部正金額付款責任已確認"]
  I --> J
  J --> K["團主填配送資料並送單"]
  K --> L["建立一張正式訂單"]
  L --> M["店家接單 派送及完成"]
```

團購主要狀態為 open、locked、submitted，另可取消；逾時依建立時間判定。分開結帳仍建立一張店家正式訂單，品項中保留各團員名稱，並保存分帳快照。

### 付款責任

| 情況 | 應付款責任 | 確認方式 |
| --- | --- | --- |
| 統一結帳 | 團主承擔整團 | 團主確認自己的應付 |
| 團員自行轉帳或現金 | 各團員自己的品項 | 團員回報後，由團主核對確認 |
| 團主墊付 | 併入團主應付 | 隨團主付款確認，不重複計入 |
| 團主自己訂購 | 併入團主應付 | 團主可直接確認 |
| 應付為零 | 不新增付款責任 | 不要求付款確認 |

自行付款的狀態為 pending → requested → paid。團員按「確認付款」只到待團主確認，不能自行把款項確認為已付款。團主核對目前金額後確認，保存確認人、時間與金額。

所有正金額付款責任確認後，團主才可結單；結帳頁與最後送單動作都會再次核對。團主填入一組顧客、配送、備註及發票資料，整團成為一張正式訂單。重送已送出的團購返回既有訂單，不新增第二張。

### 截止後移除未付款團員

在有效期限內、正式送單前，團主可移除未付款或待確認的其他團員。不能移除自己或已有效確認付款的人。移除時品項一起移除，保存紀錄，原裝置不能重新加入或操作該團。

已截止團購依原凍結價格及優惠規則重算：

1. 金額未變的付款確認保留。
2. 金額改變的舊確認失效，團員需依新金額重新回報，團主再次確認。
3. 團主自己的應付改變時，可直接重新確認。
4. 舊結帳頁報價失效，須重新載入。
5. 已有人回報或確認付款時，禁止重開或取消，以免改動已付款內容。

## 七 平台會員與 iPOS 資料流程

### 平台帳號

會員使用越南電話與平台密碼註冊、登入。電話先統一格式，重複電話不能重複註冊；密碼雜湊保存。註冊成功後自動登入並進入會員頁，註冊錯誤則回到獨立註冊頁。

會員可保存姓名與配送地址、結帳帶入資料、查看自己登入後成立的最近 50 張平台訂單，並查看可用遊戲機會與獎項券。訂單會員歸屬由登入狀態決定，同電話的歷史訪客訂單不自動合併。

目前限制包括密碼 8–72 位元組、每 IP 每小時註冊 6 次、每 IP 15 分鐘登入 30 次，以及每電話 15 分鐘登入 10 次。登入、登出及換帳號會更新登入狀態並清除上一個人的私人券選擇。

### 訪客獎勵轉入會員

訪客仍可點餐與獲得遊戲機會，獎勵保存於原瀏覽器的伺服器錢包。使用同一瀏覽器登入會員後，可用機會、歷史遊玩與獎券一次轉入該會員錢包；轉入有紀錄，原餘額歸零。

會員換裝置登入同一帳號可使用同一錢包與接續會員牌局。未領獎的訪客牌局不轉入；登入後接續會員既有牌局，沒有時才開新局，不額外扣機會。未完成訂單之後取得的機會，依原訂單錢包的轉入歸屬處理。

### iPOS 單向匯入

1. 會員先建立平台帳號。
2. 店員在後台通話確認電話屬於本人，再允許外部資料匯入。
3. 後台設定確認可用的 iPOS HUB 授權、門市來源及來源時區。
4. 伺服器只讀取會員資料與會員消費紀錄。
5. 門市姓名、生日、等級、點數及消費另行顯示，平台姓名、地址、密碼與訂單保留。
6. 消費紀錄需符合 channel／source／sale_type 來源規則與會員歸屬。
7. 完整取得所有分頁後才一次保存；查詢失敗或資料不完整時保留上次完整資料與更新時間。

iPOS 匯入預設關閉。平台消費、會員修改及遊戲獎券不回寫 iPOS；門市等級與點數作為來源資料顯示，平台抽獎使用自己的錢包。門市記錄金額不作為銀行或現金對帳結果。

## 八 買10抽獎資格與完成頁入口

### 發放一次機會的必要條件

新訂單同時符合以下條件，店家設為已完成後才發放：

- 選擇買10抽獎。
- 同一張正式訂單至少 10 杯飲品。
- 沒有同時使用買4送1、獎項序號或其他折扣。
- 訂單由後台設為 done。
- 該筆訂單尚未處理過機會發放。

每筆最多發放 1 次：9 杯無機會、10 杯有 1 次、20 杯仍為 1 次；不跨單累計，單賣加料不計飲品杯數。團購由團主取得整團訂單的機會，並核對目前帳號或原裝置歸屬。

店家完成同一訂單多次不會重複發放。發放對象依訂單保存的會員或訪客錢包決定，不依操作後台的人員帳號。

### 從送單到遊戲

```mermaid
flowchart TD
  A["選買10抽獎並送出滿10杯訂單"] --> B["完成頁顯示等待店家完成"]
  B --> C["店家將訂單設為 done"]
  C --> D["伺服器核對資格並發放1次"]
  D --> E["原顧客完成頁取得 ready 狀態"]
  E --> F["自動進入同站 minigame"]
  F --> G["開局或接續12張牌"]
  G --> H["完成全部6組配對"]
  H --> I["同一交易扣1次並發1張券"]
  I --> J["會員或原瀏覽器查看獎勵"]
  J --> K["後續合資格訂單使用優惠券"]
```

完成頁保持開啟及前景時，每 5 秒查詢實際狀態；真正取得機會、活動可玩且獎項備妥後才自動進入。若載入完成頁時已可玩，伺服器直接以 303 轉入同站遊戲頁。

完成頁保留「更新遊戲機會」與可用時的「進入小遊戲」按鈕。團購送出頁由既有同步更新團主的入口。頁面在背景時延後跳轉，恢復前景或網路後重新查詢。

狀態包含等待、可玩、活動暫停、獎項準備中、機會已使用及取消。查詢只讀取目前使用者的訂單與餘額，不會提早發放、開局、扣次或發券。換帳號清除上一個人的完成頁私人狀態。

## 九 翻牌遊戲與隨機領獎

### 遊戲操作

- 一局 12 張牌，6 組配對，每種圖案出現兩次。
- 伺服器安全亂數洗牌並保存牌局，只回傳已翻開或配對的圖案。
- 顧客依序翻牌，相同圖案完成配對，不同則約 950 毫秒後蓋牌。
- 沒有遊玩時間上限，步數、速度與錯配不改變獲獎機率。
- 重新整理或斷線可恢復同一局；會員可跨裝置接續。
- 每個錢包只有一個可恢復牌局；完成後需明確使用下一次機會才開新局。
- 開局不扣機會，全部六組配對完成並成功發券時才扣 1 次。

最後一組配對先保存遊戲完成狀態，前端再請求領獎。領獎完成標記、扣次、抽獎、遊玩紀錄與發券在同一資料庫交易內。發券失敗會回復領獎交易，重新嘗試可恢復；重複領獎回傳同一結果，避免多次扣次或重複發券。

伺服器驗證牌局擁有者與操作序號，過舊請求只同步目前狀態。翻牌限制為每個瀏覽器 session 每分鐘 240 次，超限回應 429。

### 預設獎項

以下是程式初始預設；線上活動以後台已儲存設定及當局快照為準。

| 獎項 | 預設機率 | 後續訂單使用條件 | 預設有效期 |
| --- | ---: | --- | --- |
| 指定免費加料 1 份 | 50% | 至少 1 杯付費飲品，並把指定加料加選在飲品上；只免 1 份 | 7 天 |
| 折抵 5,000đ | 35% | 至少 1 杯付費飲品，訂單小計滿 50,000đ | 7 天 |
| 指定免費飲品 1 杯 | 15% | 買 4 杯付費飲品，另加入指定商品及杯型的贈品杯，共至少 5 杯 | 7 天 |

指定贈飲只免飲品本體，加料另計；贈品杯與零價飲品不計入所需付費杯數。指定加料須附加在飲品上，單獨購買加料不符合。

獎券從領取起計算有效期，每券用一次，用於後續訂單。商品、杯型與使用條件保存於券本身，後台修改不追改已發券。

### 機率算法

機率以 0.01% 為最小單位，轉成整數權重：100% 為 10,000。啟用且大於 0% 的獎項權重合計必須為 10,000。

發獎時伺服器取得 1–10,000 的安全隨機整數，依獎池順序累積權重選中一項。0% 或停用項目不會抽中。配對圖案、翻牌順序及時間不影響抽選。

團購兌換新隨機獎券時，贈品須屬於團主自購或墊付品項，折扣只扣團主款項。團主責任金額不足以完整折抵新券時，保留券，不部分消耗。

## 十 小遊戲後台與卡面素材

### 活動設定流程

1. 管理員登入，在一般設定頁啟用小遊戲。
2. 進入 Mini Game 後台選主題。
3. 上傳卡背及最多六個配對正面圖案，或使用預設圖片。
4. 新增、編輯或停用獎項，設定商品、杯型及使用條件。
5. 確認所有啟用獎項機率合計 100%，儲存。
6. 以有機會的測試帳號開始新局，確認圖片、機率與條件。

未備妥有效獎項時，活動顯示準備中並保留機會。設定儲存使用版本核對，較舊頁面不能直接覆蓋另一位管理員的新設定。

### 可設定欄位

| 分類 | 功能與限制 |
| --- | --- |
| 內建主題 | 童年回憶、茶香花園、星夜茶會 |
| 配對圖片 | 卡背 1 個、正面 6 個；每個正面在 12 張牌中出現兩次 |
| 圖片格式 | PNG、JPEG、WebP；單張最多 2 MiB，每邊最多 4096 px |
| 圖片儲存 | uploads/minigame/，受控隨機檔名；既有圖保留供舊局使用 |
| 獎項數量 | 最多 12 個，可新增、刪除或停用 |
| 獎項類型 | 指定加料、指定飲品、金額折抵 |
| 商品選擇 | 有效啟用且價格為正的商品；指定可售飲品杯型 |
| 名稱 | 越文、英文及中文，各最多 160 字；留白時依商品或金額產生 |
| 機率 | 小數點後兩位；啟用項目合計 100% |
| 折抵金額 | 啟用項目為整數 1–100,000,000 |
| 最低小計 | 0–100,000,000 |
| 最低付費飲品杯數 | 1–100 |
| 有效天數 | 1–365 |

新主題、圖片、獎池與條件在新局開始時保存。已進行的牌局保留原快照，已發券保留原條件。暫停活動會阻擋翻牌與未發獎的領獎，保留進度和機會；已發獎結果仍可查看。

自訂正面圖片顯示通用「圖案／Hình／Picture＋編號」標籤。後台錢包列表取最近 200 筆，其統計是列表範圍加總。

### 卡面設計演進

| 素材版本 | 內容 | 狀態 |
| --- | --- | --- |
| 第一季 v1 | 上學、教室、課間、運動會、放學紅茶、畢業等六種場景 | 已保存六張圖片、預覽與素材包 |
| 小卡 v2 | 書包、書本、紙飛機、獎盃、紅茶杯、畢業帽，改為單一大主體 | 已保存透明 PNG 與小尺寸預覽 |
| HUIYI 小卡 v3 | 男孩頭像、女孩頭像、Q 版商店、飲料、紙飛機、Logo | 使用者指定六主題，已產出；待後台上傳 |

v3 沿用附件 HUIYI 角色與品牌風格，突出頭像、紅蝴蝶結、商店輪廓與紅白品牌元素。六張均為 1254 × 1254 px 透明 RGBA PNG，單張約 0.67–1.28 MiB，符合後台限制。預覽提供 40、50、70 px 對照與 360 px 手機的 4 欄 12 張卡片示意。

- 最新 HUIYI 六款素材包：`designs/game-cards/S01-huiyi-small-v3-6-cards.zip` (本機檔案)
- HUIYI 小卡預覽：`designs/game-cards/season-01-huiyi-small-v3/index.html` (本機檔案)
- 素材使用順序：`designs/game-cards/season-01-huiyi-small-v3/README.md` (本機檔案)
- 設計提示詞：`designs/game-cards/season-01-huiyi-small-v3/prompts.json` (本機檔案)

卡面是記憶配對視覺，與獎項內容、機率分別設定。六張圖案不代表六種獎品。

## 十一 商品與接單後台

### 商品與一般設定

飲品可管理三語名稱、分類、單價或 L／XL 價格、商品圖片、冰甜設定、推薦加料、排序、啟用、新品及熱門標記。商品圖片接受 JPEG、PNG、WebP，最大 5 MiB。

加料可管理三語名稱與說明、附加價格、是否單售、單售價格、圖片、排序及狀態。分類可新增、編輯、排序、停用；只有沒有商品的分類可刪除。冰量 I1–I4、甜度 S1–S5 的三語標籤與選項可編輯。

系統設定包含三語店名、幣別、一般／深紅主題、首頁橫幅與商品連結、訂購數顯示、買4送1代碼與開關、小遊戲開關、Email 與接單音效。收款帳戶可新增、編輯、排序、啟用、停用、刪除及更換 QR 圖片。

### 訂單處理

後台提供 new、accepted、done、cancelled 四種狀態。通常操作為新單 → 接單 → 完成，必要時取消；目前介面可直接選擇四種狀態，沒有強制每次只能往下一階段。

訂單詳細頁包含顧客、品項、加料、備註、付款、團購訂購人及分帳、發票資料、派送及 Email 狀態。設為完成時處理買10資格的一次性發放。

完整版列表每次取最近 200 筆。手機版每次取最近 120 筆，提供狀態篩選、訂單卡片、一鍵接單、詳細頁、電話撥號及門市派送。兩個後台共用管理帳號、資料庫與訂單。

## 十二 門市管理與派送流程

門市後台管理名稱、地址、電話、Google 地圖連結、緯度經度及是否接受派送。有效且啟用、具有完整位置的門市可參與距離建議。

1. 普通或團購訂單先完成資料庫提交。
2. 啟用地址定位時，系統查詢送餐地址的位置。
3. 從有效啟用門市中計算直線距離，提出最近門市建議。
4. 人員採用建議、人工改選其他啟用門市，或清除指派。
5. 保存操作人、時間及當時門市名稱與地址快照。
6. 門市之後改名或停用，歷史指派仍保留當時資料。

最近門市是派單參考，並不計算道路距離、行車時間或承諾到貨時間。沒有金鑰、地址不明確、查無結果或服務失敗時，訂單仍成立並可人工指派。啟用但未設定座標的門市也可人工選取。

Google 查詢所得座標暫存 30 天，到期後需重新取得；人工或有效定位連結登記的位置依其來源規則保留。建議更新不覆蓋已人工指派門市，多人同時派單以版本核對防止覆寫，完成或取消訂單不再接受派送修改。

## 十三 接單通知與 PWA

### Email

可設定最多 10 個去重收件人並寄測試信。正式訂單通知包含顧客、品項、付款與團購分帳明細，保存成功、部分成功或失敗狀態、嘗試次數、時間及診斷。完整版訂單頁可重新寄送。

通知在訂單提交後執行，寄送失敗不使已成立訂單回滾。團購未送出前的購物車更動不逐次寄送新訂單通知。

### 前景音效

後台在前景時，可使用預設或上傳音效，按秒、分鐘或持續到接單提醒。人員需先點擊啟用聲音；接單後停止新單提示。

### 系統推播

Web Push 提供裝置訂閱驗證、啟用／修復、停用、伺服器測試、15 秒延遲測試及診斷。完整版與手機版使用各自的 Service Worker 範圍。

新訂單先建立發送工作並嘗試首送。暫時性錯誤保留佇列，由 bin/process-push-queue.php 排程重試，最多四次嘗試，通知有效期一小時。404／410 會停用失效訂閱；訂單已接單、取消、完成或過期時不再補送新單提示。

「推播服務接受」「手機已處理通知」與「人員接單」是不同狀態。鎖屏聲音與顯示受手機系統控制，自訂 MP3 的持續播放功能用於前景接單頁。實機送達及主機排程運作列入部署驗收。

### 加入主畫面與快取

前台、完整後台及手機後台提供 PWA 入口，可加入主畫面。前台快取靜態資源；登入頁、訂單頁與私人 API 不作離線訂單快取。下單與即時狀態仍需網路。

## 十四 安裝更新與資料保護

### 空白 shop 資料庫全新安裝

安裝器用於真正空白的 shop 專用資料庫，生成私有連線設定與管理帳號，設定越南時區 Asia/Ho_Chi_Minh。最低需求為 PHP 8.1、PDO MySQL 與 session，相關上傳目錄須可寫。

安裝防護包括獨立 CSRF、表單驗證、後台密碼至少 8 碼、站台檔案鎖、拒絕非空白資料庫，以及已有 config.local.php 時鎖定。完成後移除安裝入口及 SQL 檔，再建立分類、商品、加料、收款帳戶、門市與遊戲獎項。

### 既有 shop 更新

2026-10-07 排查時，shop 資料庫已有 27 個資料表與商品、訂單。此站採既有網站更新流程，保留資料與私有設定。

將更新包按原路徑合併到 /home/huiyitea/shop.huiyitea.vn，使 index.php、actions/、lib/、admin/、minigame/ 位於同一根目錄，不另加 ZIP 資料夾，也不把整份更新包只放入 minigame/。

保留 config.php、各 .local.php 私有設定、uploads/、品牌圖示及根目錄 .htaccess；既有資料庫使用自動升級流程。shop 測試與安裝操作使用 shop 目錄及資料庫，正式 order 另行安排。

### 更新包權限修復紀錄

首批 19.9.1 更新包以 0600 保存檔案權限，解壓後公開 CSS／JavaScript 回應 403，造成畫面失去樣式。已修復 shop 的 32 個公開靜態檔案，並重製 19.9.1 更新包，163 個一般檔案均明確為 0644。

公開資源通常為 0644、目錄為 0755。私有設定保留原權限，依檔案用途處理，不對整站一律套用公開權限。

### 帳號與資料

管理登入與會員登入分開。密碼以雜湊驗證，登入更新 session；修改資料的表單核對 CSRF。會員訂單、遊戲錢包與團購操作依各自歸屬核對。

推播、會員匯入及地圖金鑰保存於私有設定，更新包不包含真實憑證。圖片上傳驗證實際格式，遊戲圖片目錄限制腳本執行。敏感狀態使用 private／no-store。

## 十五 功能開發版本紀錄

版本依功能演進排列。下表保留原版本完成內容，現行操作規則以前述章節為準。

| 版本 | 完成內容 |
| --- | --- |
| 19.1 | 接單通知核心修復；裝置訂閱驗證、正確 Worker 範圍、加密推播、重試佇列、診斷與前景鈴聲 |
| 19.2 系列 | 團購核心、共同購物車與團主送單 |
| 19.2.1 | 團購保留期固定為建立起 4 小時，三語說明與期限同步 |
| 19.2.2 | 邀請 QR、分享與下載、更換邀請同步 |
| 19.3.0 | 統一／分開結帳、現金／轉帳／團主墊付、付款快照與團主優惠責任 |
| 19.3.1 | 三步驟文案、購物車有品項自動已選好 |
| 19.3.2 | 客戶前台三語說明精簡，結帳用語統一 |
| 19.4.0 | 平台獨立會員、人工電話確認、iPOS 單向資料匯入、平台訂單與結帳預填 |
| 19.5.0 | 團員回報後由團主確認、全部付款確認才結單、移除未付款團員與重新計價 |
| 19.5.1 | 獨立註冊頁、錯誤回跳及註冊成功自動登入 |
| 19.6.0 | 每單優惠擇一、會員獎勵錢包、跨裝置及訪客一次轉入 |
| 19.7.0 | 門市管理、最近門市建議、人工派送、變更紀錄及私有地圖設定 |
| 19.8.0 | 附件翻牌遊戲整合、買10資格、6 組配對、三類隨機獎券與後續訂單兌換 |
| 19.8.1 | 首頁 L／XL 價格分行、靠右、金額對齊與快取版本調整 |
| 19.9.0 | 小遊戲三主題、卡背與六圖上傳、可配置獎項與機率、開局快照 |
| 19.9.1 | 完成頁每 5 秒讀取真正發放狀態、自動遊戲入口、團主入口；更新包權限修復 |
| 素材 v1 至 v3 | 第一季校園場景 → 單物件小卡 → 最新六款 HUIYI 角色與品牌卡面 |

### 2026 年 10 月 7 日環境與交付紀錄

- 確認 order 與 shop 已分開資料庫，後續測試優先使用 shop。
- 提供空白 shop 安裝說明；排查現有站時確認資料庫已有內容，改以連線授權與更新方式處理。
- 經使用者授權補上 shop 資料庫權限，網站與後台登入恢復可開啟。
- 完成 19.9.1 遊戲自動入口程式及本機驗證。
- 修復使用者更新後的公開樣式檔權限，確認商品卡片恢復 L／XL 分行靠右。
- 產出三版遊戲卡面；最新 v3 按男孩、女孩、Q 版商店、飲料、紙飛機及 Logo 六主題完成。
- 整理目前功能、操作流程、版本、驗證與維護事項，建立本開發日誌。

## 十六 驗證紀錄

下列為各版本保留的最終測試紀錄，不跨版本加總；重跑次數亦不累計為新增覆蓋。本次日誌編寫未重新建立測試訂單。

| 範圍與版本 | 已記錄驗證 | 報告 |
| --- | --- | --- |
| 團購選購 19.3.1 | MariaDB／畫面 154、HTTP 53、瀏覽器 24 | [團購選購](GROUP-READINESS-TEST-REPORT.md) |
| 平台會員 19.4.0 | 整合 441、會員純函式 83；外部資料使用模擬回應 | [會員](MEMBER-TEST-REPORT.md) |
| 團主付款確認 19.5.0 | 新整合 260；含既有整合為 592，另有價格與版面檢查 | [付款確認](GROUP-APPROVAL-TEST-REPORT.md) |
| 註冊入口 19.5.1 | 表單／跳轉 76、介面 28，共 104 | [註冊更新](UPDATE-19.5.1.md) |
| 優惠與會員錢包 19.6.0 | 新整合 405、介面 126；後續相容回歸另記錄 406 | [優惠](PROMOTION-TEST-REPORT.md)、遊戲回歸：`MINIGAME-TEST-REPORT.md` (本機檔案) |
| 門市派送 19.7.0 | 新功能程式 410、三語介面 399，共 809 | [派送](DISPATCH-TEST-REPORT.md) |
| 翻牌 19.8.0 | 遊戲 144、獎項純函式 45、前端 6 情境 | 翻牌：`MINIGAME-TEST-REPORT.md` (本機檔案) |
| 小遊戲後台 19.9.0 | 資格／翻牌 143、獎項 69、後台／上傳 119 | 後台設定：`GAME-ADMIN-TEST-REPORT.md` (本機檔案) |
| 自動入口 19.9.1 | 直接 47、HTTP 92，共 139；實際前端腳本 DOM 17 | 自動入口：`GAME-ENTRY-TEST-REPORT.md` (本機檔案) |
| 接單通知 19.1 | 語法、加密簽章、模擬傳輸、Worker 事件及介面 | [通知](NOTIFICATION-TEST-REPORT.md) |
| 19.9.1 更新包 | 版本、來源雜湊、ZIP 內容與 163 個檔案 0644 權限 | 更新說明：`UPDATE-19.9.1.md` (本機檔案) |
| HUIYI 卡面 v3 | 六圖尺寸、實際 alpha、檔案大小、ZIP 完整性與預覽引用 | 素材說明：`designs/game-cards/season-01-huiyi-small-v3/README.md` (本機檔案) |

19.8.0 的 144 與 19.9.0 的 143 是同批翻牌案例在不同初始已揭露牌數下的 HTTP 計數差，不代表功能減少。以上本機驗證與 shop 已確認的開啟／介面修復分別記錄；真實銀行入帳、iPOS 授權、Google 定位與實機通知使用各自的部署驗收。

## 十七 維護待辦與下一輪驗收

### 已核對的維護事項

1. **19.9.0 安裝包公開權限**：目前保存的 shop-install ZIP 163 個檔案均為 0600，包含 assets/css/app.css 與 assets/js/app.js。再次提供全新安裝包前，需依公開資源與私有設定用途重製權限並驗證解壓。19.9.1 更新包已修為 0644，兩包狀態不同。
2. **團購通知信文字**：純文字與 HTML 信件仍有「付款狀態由團員自行回報」的固定提示。需改為符合目前「團員回報、團主確認」的文字，現行付款確認流程本身已實作。
3. **版本文件一致性**：部分歷史更新文件保留當時的共用資料庫及未部署說明；目前環境以 AGENTS.md 的 2026-10-07 更新及本日誌為準。README 後段殘留的 19.9.0 版本文字待統一。
4. **新版安裝交付**：目前最新程式更新包為 19.9.1，空白安裝包為 19.9.0；來源安裝器防護已完成，尚無另行交付的 19.9.1 安裝包。

### shop 功能驗收順序

- [ ] 使用最新 19.9.1 更新包確認前台、商品頁、購物車與後台樣式。
- [ ] 設定實際贈送加料、飲品杯型、啟用獎項、機率及有效天數。
- [ ] 上傳 v3 六款卡面，開始新局檢查手機小尺寸辨識。
- [ ] 同一顧客送出選買10抽獎的 10 杯訂單，完成頁保持前景。
- [ ] 店家將同單設為完成，確認取得 1 次機會並約 5 秒進入遊戲。
- [ ] 完成 6 組配對，確認只扣 1 次、發 1 張券；重新整理與重試不重發。
- [ ] 在下一單滿足該券條件，確認折抵與一次核銷；未達條件保留券。
- [ ] 檢查訪客、會員、跨裝置與團主入口，各自只能取得本人機會及獎券。
- [ ] 用至少兩個團購裝置確認分帳、回報、團主確認、移除後重算與單一送單。
- [ ] 核對實際轉帳／現金、Email 收件、通知排程與手機前景／鎖屏通知。
- [ ] 在已設定的真實外部服務條件下驗收 iPOS 來源規則與 Google 門市建議。
- [ ] shop 驗收完成後，備份並另行安排正式 order 更新。

## 十八 程式與文件索引

| 功能 | 主要程式 |
| --- | --- |
| 商品及購物車 | index.php、product.php、topping.php、cart.php、actions/add_to_cart.php、actions/update_cart.php |
| 結帳及訂單 | checkout.php、actions/submit_order.php、success.php、lib/helpers.php |
| 優惠與會員錢包 | lib/promotions.php、lib/promotion_views.php、lib/member_rewards.php、lib/v170.php |
| 團購與付款 | group/index.php、group/action.php、group/state.php、lib/group_order.php、lib/group_payments.php、lib/group_views.php |
| 會員與匯入 | member/、admin/members.php、admin/member-settings.php、lib/member.php、lib/member_orders.php、lib/member_sync.php |
| 遊戲入口 | lib/game_entry.php、actions/game_status.php、assets/js/game-entry.js |
| 翻牌與獎券 | minigame/、actions/minigame.php、lib/minigame.php、lib/minigame_matching.php、lib/game_rewards.php |
| 遊戲後台與上傳 | admin/game.php、lib/game_config.php、lib/game_reward_admin.php、lib/game_media.php |
| 派送 | admin/stores.php、lib/dispatch.php、lib/dispatch_views.php |
| 接單與通知 | admin/order.php、mobile-admin/、lib/v179.php、lib/push_service.php、lib/push_api.php、bin/process-push-queue.php |
| 安裝與環境 | setup.php、install.sql、lib/bootstrap.php、AGENTS.md、INSTALL-SHOP.md |
| 通知信待更新文字 | lib/v179.php 第 106、143 行 |
| 卡面素材 | designs/game-cards/season-01-huiyi-small-v3/ |

- [專案說明](README.md)
- [部署環境規則](AGENTS.md)
- 19.9.1 更新與權限修復：`UPDATE-19.9.1.md` (本機檔案)
- 19.9.0 小遊戲後台：`UPDATE-19.9.0.md` (本機檔案)
- shop 安裝說明：`INSTALL-SHOP.md` (本機檔案)
- [開發測試重現方式](tests/README.md)

後續日誌新增版本時，更新目前基線、實際部署狀態、功能變更、驗證範圍與未完成事項；已發布版本紀錄保留原時點內容。
',
];
