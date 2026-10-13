<?php
if (!defined('HUIYI_DEVELOPER_DOCS')) { http_response_code(404); exit; }
return ['version'=>'19.12.0','markdown'=><<<'WORKFLOW_RELEASE_19120'
<a id="tieng-viet"></a>

# 19.12.0 — Sơ đồ thao tác khách hàng và quản trị

Ngày ghi nhận: 2026-10-08, UTC+7. Sơ đồ mô tả mã nguồn đến 19.12.0, bao gồm thanh toán 19.11.0; không phải xác nhận các chức năng này đã có trên website đang chạy.

## Cách đọc và phạm vi

14 sơ đồ cho khách đặt hàng, thành viên, trưởng nhóm và nhân viên. Mỗi ô ghi người thực hiện; ô màu vàng có dấu hỏi là điểm rẽ nhánh, chữ trên mũi tên là điều kiện. Dùng mục lục để đến từng quy trình. Sơ đồ rộng có thể cuộn ngang; mục văn bản bên dưới giữ đầy đủ các bước khi không chạy JavaScript hoặc khi in.

| Vai trò | Quy trình chính |
| --- | --- |
| Khách / thành viên | Đặt đồ uống, thanh toán, tài khoản, ưu đãi, trò chơi |
| Trưởng nhóm / người tham gia | Tạo nhóm, chọn đồ, chốt tiền, báo và duyệt thanh toán, thu hộ |
| Nhân viên / quản trị | Nhận đơn, xác nhận tiền, phân chi nhánh, danh mục, hoạt động, thành viên, thông báo |

**Ba việc độc lập:** thành viên báo đã trả; trưởng nhóm xác nhận đã thu; cửa hàng xác nhận thực nhận. Trạng thái giao đơn cũng độc lập với trạng thái tiền. Nhân viên chỉ xác nhận tiền sau khi kiểm tra thực tế; hệ thống không tự kiểm tra ngân hàng.

## 01. Khách đặt đơn thông thường

```mermaid
flowchart TD
 A["Khách: chọn đồ uống / topping"] --> B["Khách: chọn cỡ, đá, đường, số lượng"]
 B --> C["Khách: kiểm tra giỏ và chọn một ưu đãi"]
 C --> D["Khách: nhập giao hàng, thanh toán, hóa đơn nếu cần"]
 D --> E{"Hệ thống: dữ liệu và báo giá còn hợp lệ?"}
 E -->|Không| F["Khách: sửa thông tin hoặc xác nhận lại giỏ"]
 E -->|Có| G["Hệ thống: lưu đơn và chi tiết, cấp mã đơn"]
 G --> H["Khách: mở thanh toán từ trang thành công"]
 G --> I["Nhân viên: nhận đơn trong quản trị"]
```

Khách vãng lai vẫn đặt được; đăng nhập giúp điền thông tin đã lưu. Nếu yêu cầu hóa đơn, điền các trường bắt buộc; hệ thống lưu dữ liệu, cửa hàng thực hiện xuất hóa đơn sau. Thông báo và gợi ý chi nhánh chạy sau khi lưu đơn; lỗi thông báo không làm mất đơn. Điểm vào: `index.php`, `product.php`, `cart.php`, `checkout.php`, `success.php`.

## 02. Khách trả tiền cửa hàng và nhân viên xác nhận

Áp dụng cho đơn thường và tổng tiền cửa hàng của nhóm thu hộ. Không áp dụng yêu cầu trả lại toàn bộ cho nhóm trả trực tiếp cửa hàng.

```mermaid
flowchart TD
 A["Người đặt / trưởng nhóm: mở trang thanh toán của mình"] --> B{"Hệ thống: tổng tiền bằng 0?"}
 B -->|Có| C["Hệ thống: không cần thanh toán"]
 B -->|Không| D{"Phương thức đã chọn?"}
 D -->|Tiền mặt| E["Khách: giao tiền mặt cho cửa hàng"]
 D -->|Chuyển khoản| F{"Có tài khoản nhận đang hoạt động?"}
 F -->|Chưa có| G["Khách: chờ cửa hàng cung cấp thông tin"]
 F -->|Có| H["Khách: kiểm tra người nhận, QR và số tiền rồi chuyển"]
 H --> I["Khách: báo đã trả; chờ cửa hàng xác nhận"]
 E --> I
 I --> J["Nhân viên: kiểm tra tiền thực nhận"]
 J --> K{"Đã nhận đúng khoản tiền?"}
 K -->|Chưa khớp| L["Nhân viên: liên hệ đối soát; chưa xác nhận"]
 K -->|Đã khớp| M["Nhân viên: xác nhận nhận tiền, lưu người và thời gian"]
```

Chưa chỉ định chi nhánh thì dùng tài khoản trụ sở đang bật; đã chỉ định thì dùng tài khoản chi nhánh đang bật. Gợi ý chi nhánh không đổi nơi nhận tiền. Chưa có tài khoản vẫn được gửi đơn, nhưng chưa được báo chuyển khoản vào tài khoản chưa xác định. Nếu đã chuyển vào tài khoản cũ, không chuyển lần nữa: liên hệ cửa hàng. Sau khi báo đã trả, hệ thống giữ thông tin người nhận và QR tại thời điểm báo. Đơn hủy không nhận báo thanh toán mới; hoàn tiền phải xử lý riêng. Điểm vào: `payment.php`, `admin/order.php`, `mobile-admin/order.php`.

## 03. Tạo nhóm và cùng chọn đồ

```mermaid
flowchart TD
 A["Trưởng nhóm: tạo nhóm, có thể sao chép giỏ"] --> B["Trưởng nhóm: chọn thu hộ nếu cần và nhập tài khoản"]
 B --> C["Trưởng nhóm: chia sẻ liên kết hoặc QR mời"]
 C --> D["Thành viên: nhập biệt danh và chọn đồ của mình"]
 D --> E["Hệ thống: giỏ có món thì hiển thị đã chọn"]
 E --> F["Trưởng nhóm: chọn ưu đãi và chung / riêng"]
 F --> G["Trưởng nhóm: chốt nhóm, cố định món và tiền"]
 G --> H{"Nhóm có bật thu hộ?"}
 H -->|Có| I["Tiếp tục quy trình 05: trả trưởng nhóm"]
 H -->|Không| J["Tiếp tục quy trình 04: trả trực tiếp cửa hàng"]
```

Không cần mỗi người đều có món; trưởng nhóm có thể chỉ tạo nhóm. Vai trò nhóm dựa trên thiết bị gốc, tách biệt đăng nhập thành viên. Nhóm tồn tại 4 giờ từ lúc tạo; mở lại hoặc đổi lời mời không gia hạn. Đổi lời mời làm liên kết cũ mất hiệu lực. Nhóm đã chốt, gửi, hủy hoặc hết hạn không cho người mới vào. Tài khoản thu hộ được cố định lúc tạo nhóm. Điểm vào: `group/index.php`, `group/action.php`.

## 04. Nhóm trả trực tiếp cửa hàng: chốt và duyệt tiền

```mermaid
flowchart TD
 A["Hệ thống: cố định báo giá nhóm không thu hộ"] --> B{"Cách thanh toán?"}
 B -->|Chung| C["Trưởng nhóm: chuẩn bị và xác nhận khoản của mình"]
 B -->|Riêng| D["Hệ thống: phân số tiền từng người"]
 D -->|Tự trả| E["Thành viên: trả cửa hàng rồi báo đã trả"]
 D -->|Trưởng nhóm ứng| C
 E --> F["Trưởng nhóm: đối chiếu và duyệt thành viên"]
 C --> G{"Mọi khoản dương đã được xác nhận?"}
 F --> G
 G -->|Chưa| H["Trưởng nhóm: chờ hoặc bỏ người chưa trả; kiểm tra tiền mới"]
 G -->|Đủ| I["Trưởng nhóm: điền giao hàng và gửi một đơn"]
 I --> J["Cửa hàng: xử lý đơn; không yêu cầu trả tổng lần hai"]
```

Chỉ trưởng nhóm chọn ưu đãi. Giảm giá nhóm chỉ trừ phần tự đặt / ứng trước của trưởng nhóm. Người không có khoản phải trả không cần xác nhận. Bỏ người chưa trả có thể đổi ưu đãi, số tiền và điều kiện mua 10; khoản thay đổi phải báo / duyệt lại. Trưởng nhóm duyệt không phải xác nhận tiền của nhân viên; nhóm cũ giữ lịch sử thanh toán của mình và không tự tạo khoản thu tổng mới.

## 05. Nhóm thu hộ: thành viên → trưởng nhóm → cửa hàng

```mermaid
flowchart TD
 A["Hệ thống: chốt nhóm thu hộ và số tiền"] --> B{"Ai chịu khoản này?"}
 B -->|Thành viên tự trả| C["Thành viên: chuyển vào tài khoản trưởng nhóm hoặc giao tiền mặt"]
 B -->|Trưởng nhóm trả| D["Trưởng nhóm: chuẩn bị tiền tự đặt / ứng trước hoặc trả chung"]
 C --> E["Thành viên: báo đã trả cho trưởng nhóm"]
 E --> F["Trưởng nhóm: kiểm tra và xác nhận đã thu"]
 D --> G["Trưởng nhóm: xác nhận tiền của mình đã chuẩn bị"]
 F --> H{"Tất cả khoản dương đã xác nhận?"}
 G --> H
 H -->|Chưa| I["Trưởng nhóm: chờ / xử lý người chưa trả và kiểm tra lại tiền"]
 H -->|Đủ| J["Trưởng nhóm: gửi đơn; xem tổng tiền cửa hàng"]
 J --> K["Trưởng nhóm: trả toàn bộ tiền đơn cho cửa hàng và báo"]
 K --> L["Nhân viên: kiểm tra thực nhận rồi xác nhận cửa hàng nhận tiền"]
```

Nhóm thu hộ mặc định thanh toán riêng; có thể chọn chung để trưởng nhóm trả hộ cả nhóm. Tự trả của thành viên là trả **trưởng nhóm**, không dùng QR cửa hàng. Tổng trả cửa hàng lấy từ đơn đã lưu, khác phần tự đặt / ứng trước của trưởng nhóm. Thành viên khác không có quyền báo tổng tiền cửa hàng. Sau khi gửi đơn tiếp tục theo quy trình 02, kể cả chưa có tài khoản cửa hàng hoặc tổng bằng 0.

## 06. Thành viên: tài khoản, thông tin và lịch sử

```mermaid
flowchart TD
 A["Khách: mở trang thành viên"] --> B{"Đã có tài khoản nền tảng?"}
 B -->|Chưa| C["Khách: đăng ký số điện thoại và mật khẩu"]
 B -->|Có| D["Khách: đăng nhập"]
 C --> E["Hệ thống: xác thực và đăng nhập thành công"]
 D --> E
 E --> F["Thành viên: lưu tên và địa chỉ giao hàng"]
 E --> G["Thành viên: xem đơn của mình, cơ hội và phiếu thưởng"]
 G --> H["Thành viên: mở lại thanh toán đơn thuộc tài khoản"]
```

Đăng ký lỗi trở về trang đăng ký để sửa; đăng nhập sai phải nhập lại. Đơn khách cũ trùng điện thoại không tự gộp vào tài khoản. Đăng nhập cùng trình duyệt chuyển quyền phần thưởng khách theo quy tắc ví, không chuyển bàn chơi khách chưa nhận thưởng. Đăng nhập cùng tài khoản trên thiết bị khác dùng được ví thành viên; nhóm vẫn có quy tắc thiết bị riêng. Điểm vào: `member/register.php`, `member/index.php`, `member/action.php`.

## 07. Khách / trưởng nhóm chọn ưu đãi

```mermaid
flowchart TD
 A["Khách / trưởng nhóm: kiểm tra giỏ"] --> B{"Chọn một phương án"}
 B -->|Mua 4 tặng 1| C["Hệ thống: tính giảm phần đồ uống hợp lệ"]
 B -->|Mua 10 chơi game| D["Hệ thống: chưa giảm tiền; xét cơ hội khi hoàn tất đơn"]
 B -->|Phiếu thưởng| E["Hệ thống: kiểm tra chủ sở hữu, hạn và điều kiện phiếu"]
 B -->|Không ưu đãi| F["Hệ thống: giữ nguyên giá"]
 C --> G["Khách: kiểm tra tổng và xác nhận gửi đơn"]
 D --> G
 E --> G
 F --> G
 G --> H["Hệ thống: lưu đơn; chỉ dùng phiếu khi đơn thành công"]
```

Không cộng dồn các lựa chọn. Phiếu không hợp lệ hoặc không đủ điều kiện phải đổi lựa chọn / giỏ; không coi việc nhập mã là đã dùng thành công. Quà đồ uống cần thêm đúng ly quà ngoài số ly trả tiền; quà topping phải gắn vào đồ uống. Nhóm chỉ giảm phần trưởng nhóm chịu; không đủ trách nhiệm để dùng trọn phiếu mới thì giữ phiếu. Nội dung phiếu đã cấp giữ nguyên dù sau đó quản trị đổi phần thưởng.

## 08. Mua 10: nhận cơ hội, chơi và dùng thưởng

```mermaid
flowchart TD
 A["Khách / trưởng nhóm: chọn mua 10 và gửi đơn đủ 10 ly"] --> B["Nhân viên: hoàn tất đơn"]
 B --> C["Hệ thống: kiểm tra điều kiện và chỉ cấp một lần"]
 C --> D{"Có cơ hội và hoạt động sẵn sàng?"}
 D -->|Chưa| E["Khách: giữ cơ hội, chờ cập nhật / hoạt động sẵn sàng"]
 D -->|Có| F["Khách: vào game hoặc tiếp tục bàn đang chơi"]
 F --> G["Khách: ghép đủ 6 cặp trong 12 thẻ"]
 G --> H["Hệ thống: cấp một phiếu và trừ một cơ hội cùng giao dịch"]
 H --> I["Khách: xem điều kiện và dùng phiếu ở đơn sau"]
```

Không có ưu đãi khác trong đơn; chỉ đếm ly đồ uống, mỗi đơn tối đa một cơ hội kể cả 20 ly. Nhóm cấp cho trưởng nhóm. Đánh dấu nhận tiền không tự hoàn tất đơn và không tự cấp cơ hội. Trang thành công có thể tự chuyển khi đủ điều kiện và ở tiền cảnh. Không trừ cơ hội lúc bắt đầu; tải lại tiếp tục cùng bàn. Gửi nhận thưởng lặp không cấp thêm phiếu. Điểm vào: `success.php`, `group/index.php`, `minigame/index.php`.

## 09. Nhân viên nhận và xử lý đơn

```mermaid
flowchart TD
 A["Nhân viên: mở danh sách đơn trên máy tính / điện thoại"] --> B["Nhân viên: xem khách, món, nhóm, ghi chú và tiền"]
 B --> C{"Có thể xử lý đơn?"}
 C -->|Không| D["Nhân viên: liên hệ khách và hủy nếu cần"]
 C -->|Có| E["Nhân viên: nhận đơn và chỉ định chi nhánh"]
 E --> F["Cửa hàng: chuẩn bị và giao theo thông tin đơn"]
 F --> G["Nhân viên: cập nhật hoàn tất đơn"]
 G --> H["Hệ thống: kiểm tra cấp cơ hội mua 10 một lần"]
```

Đây là thứ tự thao tác thông thường; giao diện hiện cho chọn trực tiếp mới / đã nhận / hoàn tất / hủy, không ép từng bước. Xử lý tiền theo quy trình 02 độc lập với trạng thái đơn. Hủy không tự hoàn tiền; đối soát và hoàn tiền xử lý bên ngoài hệ thống. Quản trị máy tính và điện thoại dùng chung đơn và tài khoản. Điểm vào: `admin/index.php`, `admin/order.php`, `mobile-admin/index.php`, `mobile-admin/order.php`.

## 10. Quản trị chi nhánh, tài khoản và phân đơn

```mermaid
flowchart TD
 A["Quản trị: lưu chi nhánh, vị trí và trạng thái"] --> B["Quản trị: nhập tài khoản, tên chủ, ngân hàng; QR tùy chọn"]
 B --> C["Hệ thống: kiểm tra và lưu tài khoản cùng chi nhánh"]
 C --> D["Hệ thống: sau khi có đơn, thử gợi ý chi nhánh"]
 D --> E{"Có gợi ý vị trí phù hợp?"}
 E -->|Có| F["Nhân viên: kiểm tra rồi áp dụng hoặc chọn khác"]
 E -->|Không| G["Nhân viên: chọn chi nhánh thủ công"]
 F --> H["Hệ thống: lưu chi nhánh do người xác nhận"]
 G --> H
 H --> I["Khách: thấy tài khoản nhận theo chỉ định hiện tại"]
```

Gợi ý dùng khoảng cách thẳng, không phải thời gian giao hàng; thiếu dịch vụ bản đồ vẫn đặt và phân đơn thủ công. Không đổi phân đơn khi đã hoàn tất / hủy. Không đủ thông tin tài khoản thì không bật nhận chuyển khoản. Sau khi khách đã báo trả, khoản tiền giữ người nhận ban đầu dù đổi chi nhánh giao; đối soát tài khoản cũ, không yêu cầu trả lại. Điểm vào: `admin/stores.php`, `admin/order.php`, trang chi tiết đơn.

## 11. Quản trị danh mục và thông tin cửa hàng

```mermaid
flowchart TD
 A["Quản trị: chuẩn bị phân loại, mức đá / đường và topping"] --> B["Quản trị: nhập đồ uống và tên ba ngôn ngữ"]
 B --> C["Quản trị: giá, cỡ ly, ảnh, topping gợi ý và thứ tự"]
 C --> D["Quản trị: bật / tắt món và lưu"]
 D --> E["Quản trị: kiểm tra thẻ món, chi tiết và giỏ trên website"]
 E --> F["Quản trị: chỉnh tên quán, banner, chủ đề và hiển thị"]
 F --> G["Khách: đặt bằng dữ liệu danh mục hiện hành"]
```

Món có L / XL dùng giá từng cỡ. Phân loại có món không xóa trực tiếp; có thể tắt. Giá và tên trong đơn đã lưu là lịch sử tại lúc đặt, không bị thay khi sửa danh mục. Tài khoản trụ sở quản lý trong phần cài đặt thanh toán; tài khoản chi nhánh ở quy trình 10. Các trang: `admin/products.php`, `admin/categories.php`, `admin/toppings.php`, `admin/profiles.php`, `admin/settings.php`.

## 12. Quản trị hoạt động và phần thưởng Mini Game

```mermaid
flowchart TD
 A["Quản trị: bật hoạt động trong cài đặt"] --> B["Quản trị: chọn chủ đề, mặt sau và 6 mặt thẻ"]
 B --> C["Quản trị: thêm / sửa phần thưởng, điều kiện và hạn dùng"]
 C --> D{"Tổng xác suất phần thưởng đang bật là 100%?"}
 D -->|Chưa đúng| E["Quản trị: sửa xác suất và các thông tin lỗi"]
 D -->|Đúng| F["Quản trị: lưu cấu hình mới"]
 F --> G["Quản trị: kiểm tra bằng tài khoản thử trên shop"]
 G --> H["Hệ thống: dùng cấu hình mới cho lần mở bàn kế tiếp"]
```

Phải chuẩn bị đúng món / cỡ ly cho giải tặng đồ uống và topping cho giải tương ứng. Thẻ phối hình không quyết định giải; thời gian, số bước và số lần sai không đổi xác suất. Bàn đã mở và phiếu đã phát giữ điều kiện cũ. Nếu người khác đã sửa cấu hình, tải lại trước khi lưu. Điểm vào: `admin/settings.php`, `admin/game.php`.

## 13. Nhân viên xác minh thành viên và đọc dữ liệu iPOS

```mermaid
flowchart TD
 A["Khách: tạo tài khoản nền tảng"] --> B["Nhân viên: gọi xác nhận số điện thoại chính chủ"]
 B --> C["Nhân viên: ghi xác minh trong quản trị"]
 C --> D{"Đã bật kết nối và cấu hình nguồn hợp lệ?"}
 D -->|Chưa| E["Thành viên: tiếp tục dùng tài khoản nền tảng"]
 D -->|Rồi| F["Hệ thống: chỉ đọc hồ sơ và giao dịch iPOS đúng thành viên"]
 F --> G{"Đọc đủ và hợp lệ toàn bộ dữ liệu?"}
 G -->|Không| H["Hệ thống: giữ bản hoàn chỉnh lần trước"]
 G -->|Có| I["Hệ thống: lưu dữ liệu cửa hàng riêng cho thành viên xem"]
```

Tích hợp mặc định tắt; cần xác nhận quyền truy cập và quy tắc nguồn trước khi dùng. Không ghi đơn, mật khẩu, địa chỉ hay phiếu thưởng nền tảng ngược về iPOS. Dữ liệu điểm / giao dịch cửa hàng không phải chứng từ nhận tiền. Điểm vào: `admin/members.php`, `admin/member-settings.php`, `member/index.php`.

## 14. Nhân viên thiết lập và kiểm tra thông báo

```mermaid
flowchart TD
 A["Quản trị: cấu hình email và âm thanh / push cho thiết bị"] --> B["Nhân viên: bật âm thanh, cấp quyền thông báo và kiểm tra"]
 B --> C["Hệ thống: lưu đơn mới thành công"]
 C --> D["Hệ thống: gửi email và tạo công việc push"]
 D --> E{"Thông báo thành công?"}
 E -->|Không / một phần| F["Nhân viên: xem chẩn đoán; gửi lại email / kiểm tra hàng đợi"]
 E -->|Có| G["Nhân viên: mở đơn và nhận đơn"]
 F --> H["Nhân viên: vẫn kiểm tra danh sách đơn và xử lý"]
 G --> I["Hệ thống: ngừng nhắc mới cho đơn đã nhận"]
 H --> I
```

Email hoặc push hỏng không hủy đơn đã lưu. Push cần quyền thiết bị và tác vụ xử lý hàng đợi ở máy chủ; nhà cung cấp chấp nhận thông báo không chứng minh điện thoại đã hiển thị hoặc nhân viên đã nhận đơn. Âm thanh nền / khóa màn hình tùy hệ điều hành. Thử email / push sẽ gửi thông báo thật tới nơi được cấu hình; chỉ dùng địa chỉ và thiết bị thử đã xác nhận. Điểm vào: `admin/settings.php`, `admin/index.php`, `mobile-admin/index.php`.

## Bản cập nhật, kiểm chứng và triển khai

19.12.0 bổ sung lối vào Sơ đồ thao tác, 14 sơ đồ mỗi ngôn ngữ, nút quyết định và mũi tên có điều kiện. Tất cả tiếng Việt đứng trước toàn bộ tiếng Trung phồn thể. Các bản ghi 19.9.1, 19.10.0 và 19.11.0 được giữ nguyên; sơ đồ cũ cũng được trình bày bằng bộ hiển thị mới. Tải nhật ký vẫn giữ Mermaid dạng văn bản; in dùng danh sách bước đầy đủ. Không tải thư viện hoặc gửi tài liệu đến dịch vụ bên ngoài.

Chỉ thay đổi trang tài liệu và cách hiển thị, không thay đổi quy tắc đặt đơn, thanh toán hoặc schema so với 19.11.0. Chức năng nghiệp vụ trong sơ đồ được đối chiếu mã nguồn; đây không thay thế nghiệm thu nghiệp vụ trên shop.

Kiểm chứng bản này: 3.162 khẳng định tài liệu và 146 kiểm tra HTTP riêng tư đạt; 28 sơ đồ / 250 kết nối hiển thị trên trình duyệt, desktop và hai kích thước mobile không tràn ngang toàn trang. Bản văn bản không JavaScript và sơ đồ nền 19.9.1 đã kiểm tra. Xem `WORKFLOW-TEST-REPORT.md`. Chưa triển khai shop hoặc order; chưa nghiệm thu PDF / máy in thật, quét QR ngân hàng hoặc nhận tiền thật.

Gói 19.12.0 là cập nhật đầy đủ, bao gồm cả thay đổi 19.11.0. Nếu shop vẫn ở 19.10.0 thì lần cập nhật này cũng cần tạo ba bảng thanh toán của 19.11.0. Sao lưu shop, xác nhận cấu hình trỏ đúng database shop và `https://shop.huiyitea.vn`, giữ cấu hình / uploads / biểu tượng / `.htaccess`, không chạy lại cài đặt. Sau nghiệm thu shop mới bố trí order. Chuyển ZIP ra ngoài thư mục website sau giải nén; kiểm tra URL ZIP cũ trả 403/404 và trang tài liệu vẫn yêu cầu quản trị đăng nhập.

---

<a id="traditional-chinese"></a>

# 19.12.0 — 客戶與管理者操作流程圖

記錄日期：2026-10-08，UTC+7。流程依截至 19.12.0 的程式整理，包含 19.11.0 付款功能；不代表目前線上網站已具備全部新版功能。

## 閱讀方式與範圍

14 張圖涵蓋顧客、會員、團主、團員及管理人員。方框註明執行者；帶問號的黃色節點為判斷，箭頭上的文字為分支條件。透過章節目錄跳轉，較寬的圖可橫向捲動。每張圖下方保留完整文字步驟，停用 JavaScript 或列印時仍可閱讀。

| 角色 | 主要流程 |
| --- | --- |
| 顧客／會員 | 訂餐、付款、帳號、優惠、小遊戲 |
| 團主／團員 | 建團、選購、截止計價、付款回報與審核、代收 |
| 店員／管理者 | 接單、核款、派送、商品、活動、會員、通知 |

**三件事分開判斷：**團員回報已付款、團主確認已收款、店家確認實際入帳。訂單處理狀態也與收款狀態分開。店員核對實際款項後才能確認，系統沒有自動銀行驗款。

## 01. 客戶一般訂餐

```mermaid
flowchart TD
 A["顧客：選飲品或單售加料"] --> B["顧客：選杯型、冰甜、加料與數量"]
 B --> C["顧客：確認購物車並擇一優惠"]
 C --> D["顧客：填配送、付款及需要的發票資料"]
 D --> E{"系統：資料與目前報價有效？"}
 E -->|否| F["顧客：修正資料或重新確認購物車"]
 E -->|是| G["系統：保存訂單及品項，產生訂單號"]
 G --> H["顧客：從完成頁開啟付款入口"]
 G --> I["店員：在接單後台查看新單"]
```

訪客也能下單，會員可帶入已保存資料。需要發票時填齊必填欄位；系統保存資料，開票由店家後續處理。通知與門市建議在訂單成立後處理，通知失敗不會取消已成立訂單。入口：`index.php`、`product.php`、`cart.php`、`checkout.php`、`success.php`。

## 02. 客戶付店家與店員核款

適用一般訂單，以及代收團送單後的店家總款項。不會要求直接付店家的團購再次支付整團總額。

```mermaid
flowchart TD
 A["訂購人／團主：開啟自己的付款頁"] --> B{"系統：總額為零？"}
 B -->|是| C["系統：顯示無需付款"]
 B -->|否| D{"訂單選擇的付款方式？"}
 D -->|現金| E["顧客：將現金交給店家"]
 D -->|轉帳| F{"已有啟用的收款帳戶？"}
 F -->|尚未| G["顧客：等待店家提供收款資訊"]
 F -->|已有| H["顧客：核對收款人、QR 與金額後轉帳"]
 H --> I["顧客：回報已付款，等待店家核款"]
 E --> I
 I --> J["店員：核對實際收到的款項"]
 J --> K{"實際款項核對一致？"}
 K -->|尚未一致| L["店員：聯絡對帳，暫不確認入帳"]
 K -->|一致| M["店員：確認店家收款，保存確認人與時間"]
```

未指派門市時用啟用的總部帳戶；已指派時用啟用的該門市帳戶，建議門市不會自動改變收款人。未設定帳戶仍可送單，但不能回報轉到尚未確定的帳戶。若已轉到舊帳戶，勿重複付款，聯絡店家核對。回報後保存當時的收款資訊與 QR。取消訂單不再接受新付款回報，退款另行處理。入口：`payment.php`、`admin/order.php`、`mobile-admin/order.php`。

## 03. 建團與共同選購

```mermaid
flowchart TD
 A["團主：建立團購，可複製一般購物車"] --> B["團主：需要代收時啟用並填收款帳戶"]
 B --> C["團主：分享邀請連結或 QR"]
 C --> D["團員：輸入暱稱，選購自己的品項"]
 D --> E["系統：購物車有品項就顯示已選好"]
 E --> F["團主：選優惠及統一／分開結帳"]
 F --> G["團主：截止收單，固定品項與金額"]
 G --> H{"這個團是否啟用代收？"}
 H -->|是| I["接流程 05：先付給團主"]
 H -->|否| J["接流程 04：直接付給店家"]
```

不要求每人都有購買，團主也可只開團。團購角色依原裝置判定，與會員登入分開。每團自建立起保留 4 小時，重新開放或換邀請不延長時間。更換邀請會使舊連結失效；截止、送出、取消或逾時後不接受新加入。代收帳戶在建團時固定。入口：`group/index.php`、`group/action.php`。

## 04. 直接付店家的團購：確認款項與送單

```mermaid
flowchart TD
 A["系統：固定未啟用代收團的報價"] --> B{"結帳方式？"}
 B -->|統一| C["團主：準備並確認自己的應付款"]
 B -->|分開| D["系統：列出每人的付款責任"]
 D -->|自行付款| E["團員：付給店家後回報已付款"]
 D -->|團主墊付| C
 E --> F["團主：核對並確認團員付款"]
 C --> G{"全部正金額款項已確認？"}
 F --> G
 G -->|尚未| H["團主：等待或移除未付款者，核對重算金額"]
 G -->|已齊| I["團主：填配送資料，送出一張訂單"]
 I --> J["店家：處理訂單，不重收一次整團款"]
```

只有團主可選優惠，團購折扣只扣團主自購與墊付款。不需付款的人不用確認。移除未付款者可能改變優惠、金額及買 10 資格；變動款項需重新回報／確認。團主確認不等於店員確認入帳，舊團保留原付款歷史，不自行推定或新增一筆整團收款要求。

## 05. 團主代收：團員 → 團主 → 店家

```mermaid
flowchart TD
 A["系統：固定代收團品項與金額"] --> B{"這筆由誰出資？"}
 B -->|團員自行付| C["團員：轉到團主帳戶或將現金交團主"]
 B -->|團主負擔| D["團主：準備自購、墊付或統一付款金額"]
 C --> E["團員：回報已付給團主"]
 E --> F["團主：核對並確認已代收"]
 D --> G["團主：確認自己的款項已準備"]
 F --> H{"所有正金額款項已確認？"}
 G --> H
 H -->|尚未| I["團主：等待或處理未付款者，再核對金額"]
 H -->|已齊| J["團主：送單，查看店家應收總額"]
 J --> K["團主：支付整張訂單金額給店家並回報"]
 K --> L["店員：核對實收，再確認店家已收款"]
```

代收團預設分開結帳，也可改統一結帳由團主負擔全團。團員自行付款是付給**團主**，不使用店家 QR。店家總額以已保存訂單為準，不等於團主的自購／墊付款；其他團員不能代報整團店家款。送單後依流程 02 處理，包含店家尚未有帳戶或總額為零的情況。

## 06. 會員帳號、資料與訂單紀錄

```mermaid
flowchart TD
 A["顧客：開啟會員頁"] --> B{"已有平台帳號？"}
 B -->|沒有| C["顧客：以電話與密碼註冊"]
 B -->|已有| D["顧客：登入平台"]
 C --> E["系統：驗證資料並登入成功"]
 D --> E
 E --> F["會員：儲存姓名與配送地址"]
 E --> G["會員：查看自己的訂單、機會及獎項券"]
 G --> H["會員：重新開啟本人訂單付款頁"]
```

註冊錯誤回註冊頁修正，登入失敗需重新輸入。相同電話的歷史訪客訂單不自動合併。原瀏覽器登入後依錢包規則轉入訪客獎勵，不轉移未領獎的訪客牌局。同帳號換裝置可使用會員錢包，團購角色另有裝置規則。入口：`member/register.php`、`member/index.php`、`member/action.php`。

## 07. 顧客／團主選優惠與用券

```mermaid
flowchart TD
 A["顧客／團主：確認購物車"] --> B{"擇一使用哪個方案？"}
 B -->|買 4 送 1| C["系統：計算合資格飲品本體折扣"]
 B -->|買 10 抽獎| D["系統：本次不折抵，完成單後判定機會"]
 B -->|獎項券| E["系統：核對本人、有效期及使用條件"]
 B -->|不使用| F["系統：按原價計算"]
 C --> G["顧客：確認總額後送單"]
 D --> G
 E --> G
 F --> G
 G --> H["系統：保存訂單，成功後才核銷使用的券"]
```

優惠不能疊加。券無效或未達條件時，須調整選擇／購物車；輸入序號不表示已兌換。贈飲需要在付費杯數之外另加正確贈品杯，加料券須把指定加料附加在飲品上。團購只扣團主負擔；新券無法完整折抵時保留券。已發獎項券的條件不隨後台之後的設定變更。

## 08. 買 10 取得機會、遊戲與領獎

```mermaid
flowchart TD
 A["顧客／團主：選買 10 並送出滿 10 杯訂單"] --> B["店員：將訂單設為完成"]
 B --> C["系統：核對資格，該單只發放一次"]
 C --> D{"已有機會且活動準備完成？"}
 D -->|尚未| E["顧客：保留機會，等待更新或活動準備"]
 D -->|已就緒| F["顧客：進入遊戲或接續原牌局"]
 F --> G["顧客：完成 12 張牌的 6 組配對"]
 G --> H["系統：同一交易發一張券並扣一次機會"]
 H --> I["顧客：查看條件，在下一筆合資格訂單使用"]
```

訂單不能併用其他優惠，只計飲品杯數；20 杯仍每單最多一次。團購由團主取得。確認收款不會自動完成訂單或發機會。完成頁在前景且符合條件時可自動進入遊戲。開局不扣機會，重新整理可接續；重複領獎不重複發券。入口：`success.php`、`group/index.php`、`minigame/index.php`。

## 09. 店員接單與處理訂單

```mermaid
flowchart TD
 A["店員：開啟桌面或手機接單列表"] --> B["店員：查看顧客、品項、團購、備註與款項"]
 B --> C{"訂單可以處理？"}
 C -->|否| D["店員：聯絡顧客，必要時取消"]
 C -->|是| E["店員：接單並確認派送門市"]
 E --> F["店家：按訂單資料準備與配送"]
 F --> G["店員：更新為已完成"]
 G --> H["系統：判斷並一次性發放買 10 機會"]
```

圖示是一般作業順序；目前介面可直接選新單、已接單、完成、取消，未強制逐階段轉換。收款依流程 02 獨立處理，取消不自動退款，退款及對帳另行完成。桌面與手機後台共用訂單及管理帳號。入口：`admin/index.php`、`admin/order.php`、`mobile-admin/index.php`、`mobile-admin/order.php`。

## 10. 門市、收款帳戶與派送

```mermaid
flowchart TD
 A["管理者：保存門市資料、位置與啟用狀態"] --> B["管理者：填銀行、帳號、戶名，可上傳 QR"]
 B --> C["系統：驗證帳戶並與門市一起保存"]
 C --> D["系統：訂單成立後嘗試建議門市"]
 D --> E{"有可用的位置建議？"}
 E -->|有| F["店員：核對後採用建議或另選門市"]
 E -->|沒有| G["店員：人工選擇派送門市"]
 F --> H["系統：保存人員實際確認的指派"]
 G --> H
 H --> I["顧客：依目前指派查看對應收款帳戶"]
```

建議以直線距離計算，不代表送達時間；缺少地圖服務仍可下單與人工指派。完成／取消訂單不可再修改派送。收款資料不完整時不可啟用轉帳帳戶。顧客已回報付款後，即使變更配送門市，該筆款項仍保存原收款人，應核對原帳戶而非要求再付一次。入口：`admin/stores.php`、`admin/order.php`、訂單詳情。

## 11. 商品目錄與店家資訊維護

```mermaid
flowchart TD
 A["管理者：準備分類、冰甜選項與加料"] --> B["管理者：建立飲品與三語名稱"]
 B --> C["管理者：設定價格、杯型、圖片、推薦加料及排序"]
 C --> D["管理者：啟用或停用商品並保存"]
 D --> E["管理者：在前台核對商品卡、詳情與購物車"]
 E --> F["管理者：維護店名、橫幅、主題及顯示設定"]
 F --> G["顧客：依目前商品資料選購"]
```

L／XL 商品各自設定價格。有商品的分類不能直接刪除，可先停用。已成立訂單保留當時名稱與價格，編輯目錄不改寫歷史訂單。總部帳戶在付款設定管理，門市帳戶見流程 10。頁面：`admin/products.php`、`admin/categories.php`、`admin/toppings.php`、`admin/profiles.php`、`admin/settings.php`。

## 12. Mini Game 活動與獎項設定

```mermaid
flowchart TD
 A["管理者：在一般設定啟用活動"] --> B["管理者：選主題、卡背與六款配對卡面"]
 B --> C["管理者：新增或修改獎項、條件與有效期"]
 C --> D{"啟用獎項機率合計為 100%？"}
 D -->|否| E["管理者：修正機率與欄位錯誤"]
 D -->|是| F["管理者：儲存新設定"]
 F --> G["管理者：在 shop 以測試帳號驗收"]
 G --> H["系統：新設定套用於下一次開局"]
```

贈飲獎須準備正確商品及杯型，加料獎須設定對應加料。配對卡面不代表獎品，時間、步數及錯配不改變機率。已開局牌局與已發券保留原條件；另一位管理者已修改時，需重新載入再保存。入口：`admin/settings.php`、`admin/game.php`。

## 13. 店員驗證會員與 iPOS 單向匯入

```mermaid
flowchart TD
 A["顧客：建立平台帳號"] --> B["店員：通話確認電話屬於本人"]
 B --> C["店員：在會員後台記錄驗證"]
 C --> D{"已啟用連線且來源設定有效？"}
 D -->|尚未| E["會員：繼續使用平台本身功能"]
 D -->|已設定| F["系統：只讀取本人 iPOS 資料與消費"]
 F --> G{"已完整取得並驗證全部資料？"}
 G -->|否| H["系統：保留上次完整資料"]
 G -->|是| I["系統：另存門市資料供會員查看"]
```

外部串接預設關閉，須先確認授權與來源規則。平台訂單、密碼、地址、獎項券都不回寫 iPOS；門市點數或消費紀錄不能當作入帳證明。入口：`admin/members.php`、`admin/member-settings.php`、`member/index.php`。

## 14. 管理者設定與檢查接單通知

```mermaid
flowchart TD
 A["管理者：設定 Email、音效及裝置推播"] --> B["店員：啟用聲音、允許通知並測試"]
 B --> C["系統：成功保存新訂單"]
 C --> D["系統：寄通知信並建立推播工作"]
 D --> E{"通知成功？"}
 E -->|失敗或部分失敗| F["店員：查看診斷，重寄信或檢查佇列"]
 E -->|成功| G["店員：開啟並接下訂單"]
 F --> H["店員：仍從訂單列表確認並處理新單"]
 G --> I["系統：已接單後停止該單的新單提醒"]
 H --> I
```

通知失敗不取消訂單。推播需要裝置授權及主機的佇列處理排程；服務接受推播不代表手機已顯示或人員已接單。背景／鎖屏提示由作業系統控制。測試信與推播會實際送到設定的收件者或裝置，只使用已確認的測試對象。入口：`admin/settings.php`、`admin/index.php`、`mobile-admin/index.php`。

## 本版變更、驗證與部署

19.12.0 新增操作流程圖入口，每語言 14 張圖，以節點、判斷及條件箭頭呈現。完整越文在上、完整繁中在下；19.9.1、19.10.0、19.11.0 文件快照保留原樣，舊圖也使用新的顯示方式。下載日誌保留 Mermaid 文字，列印使用完整步驟清單。沒有外部圖表函式庫或文件傳送服務。

本次只修改文件頁及顯示方式，不改變 19.11.0 的訂單、付款規則或資料庫結構。流程內容已對照程式，但不取代 shop 的實際業務驗收。

本版驗證：文件 3,162 項與隔離 HTTP 146 項通過；瀏覽器顯示 28 張圖／250 條連線，桌面及兩種手機尺寸皆無整頁橫向溢出。已檢查停用 JavaScript 的文字流程與 19.9.1 舊版圖表。詳見 `WORKFLOW-TEST-REPORT.md`。尚未部署 shop 或 order；尚未驗收 PDF／實體印表機、銀行 QR 掃碼或真實收款。

19.12.0 是完整更新包，包含 19.11.0 的程式。如果 shop 仍為 19.10.0，此次更新也會涉及 19.11.0 的三張付款資料表。更新前備份 shop，確認連線到 shop 獨立資料庫且網址為 `https://shop.huiyitea.vn`；保留私有設定、uploads、圖示及 `.htaccess`，不重跑安裝。先驗收 shop，再安排 order。解壓後把 ZIP 移出網站目錄，確認舊 ZIP 網址為 403／404，文件頁仍須管理者登入。
WORKFLOW_RELEASE_19120
];
