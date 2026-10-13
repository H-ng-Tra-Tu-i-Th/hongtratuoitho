<?php
if (!defined('HUIYI_DEVELOPER_DOCS')) { http_response_code(404); exit; }
return ['version'=>'19.15.0','markdown'=><<<'HUIYI_19150_DOC'
<a id="tieng-viet"></a>

# 19.15.0 — Cải tiến thanh toán, đối soát và lịch sử

Ngày ghi nhận: 2026-10-12, Asia/Saigon. Tài liệu mô tả phiên bản mã nguồn 19.15.0; các bản lịch sử giữ nguyên.

## Chức năng và phạm vi thay đổi

- Thêm sổ sự kiện thanh toán cửa hàng và nhóm; lưu số phải trả, số đã nhận/chuẩn bị, số hoàn, lý do, người thực hiện và mã giao dịch. Không xóa lịch sử khi sửa sai hoặc loại thành viên.
- Từ chối báo sai, ghi thực thu tiền mặt/chuyển khoản, điều chỉnh sai sót, đăng ký hoàn tiền thủ công và đối soát thiếu/thừa. Không kết nối ngân hàng, không tự trả tiền hoặc tự hoàn tiền.
- Bảo vệ báo cáo nhóm trước xóa; tách hạn chọn món và hạn thanh toán. Có báo/thu tiền thì giữ khả năng kết toán, thêm gia hạn có lý do và kiểm tra chênh lệch.
- Dùng dịch vụ trạng thái chung với phiên bản, lý do và lịch sử cho desktop/mobile; thêm lọc trạng thái thanh toán và CSV tài chính có phân quyền.
- Giữ nhiều đơn khách trong session, ràng buộc thông báo với mã đơn, giữ bản nháp khi nhập sai, xử lý gửi lại cùng biểu mẫu. Thêm sao chép và QR, làm mới trạng thái khi quay lại.
- Nâng cấp bổ sung schema; giữ đơn cũ, tài khoản/QR gốc và biên nhận đã xác nhận. Nhóm trả trực tiếp không bị yêu cầu trả tổng lần nữa.

## Sơ đồ thao tác đầy đủ

Vai trò hiện hành: Chủ sở hữu quản lý tài khoản và toàn bộ quản trị; Quản trị truy cập chức năng quản trị được phép; Nhân viên chỉ xử lý đơn không truy cập danh mục, cấu hình, tài liệu hay xuất báo cáo tài chính. Khách, thành viên và trưởng nhóm theo quyền sở hữu riêng.

## 01. Khách đặt đơn thông thường

```mermaid
flowchart TD
 A["Khách: chọn đồ uống và ưu đãi"] --> B["Khách: điền giao hàng và hóa đơn trên checkout.php"]
 B --> C{"Hệ thống: thông tin hợp lệ?"}
 C -->|Không| D["Hệ thống: giữ bản nháp và đánh dấu trường cần sửa"]
 C -->|Có| E["Hệ thống: tính tiền và lưu đơn cùng biên nhận trong một giao dịch"]
 E --> F["Hệ thống: ghi kết quả gửi đơn theo mã yêu cầu của session"]
 F --> G["Khách: mở success.php hoặc payment.php"]
 G --> H["Khách: kiểm tra đúng người nhận rồi thanh toán"]
```

Gửi lại cùng biểu mẫu đã lưu sẽ dẫn về đơn đó. Khi chưa xác định kết quả commit, không hướng dẫn đặt đơn lần nữa. Khách giữ quyền xem tối đa 50 đơn đã tạo trong cùng session; đăng nhập, đăng xuất hoặc đổi tài khoản xóa quyền khách cũ. Thành viên chỉ xem đơn gắn với tài khoản của mình. Đổi thiết bị không tự khôi phục đơn khách.

## 02. Khách thanh toán và cửa hàng đối soát

```mermaid
flowchart TD
 A["Người đặt hoặc trưởng nhóm: mở payment.php của mình"] --> B{"Hệ thống: đơn còn được thanh toán?"}
 B -->|Đã hủy| C["Hệ thống: không cho trả thêm và hiển thị đối soát hoặc hoàn tiền"]
 B -->|Còn hiệu lực| D["Khách: đối chiếu số tiền, tài khoản và mã đơn"]
 D --> E["Khách: chuyển khoản hoặc giao tiền mặt thực tế"]
 E --> F["Khách: báo đã trả; hệ thống lưu nơi nhận và QR"]
 F --> G["Nhân viên: kiểm tra thực nhận trên admin/order.php hoặc mobile-admin/order.php"]
 G --> H{"Nhân viên: kết quả đối soát?"}
 H -->|Khớp| I["Nhân viên: ghi thực thu và mã giao dịch ngân hàng"]
 H -->|Chưa thấy tiền| J["Nhân viên: từ chối báo cáo với lý do, giữ lịch sử"]
 H -->|Thiếu hoặc thừa| K["Nhân viên: ghi số thực thu và xử lý chênh lệch"]
 I --> L["Hệ thống: giữ sự kiện thu tiền và người thực hiện"]
 K --> L
```

Nhân viên có thể ghi tiền mặt thực nhận mà không chờ khách báo. Báo đã trả không chứng minh tiền vào ngân hàng. Sửa sai và ghi hoàn tiền là nghiệp vụ thủ công có lý do, không gửi lệnh ngân hàng. Hoàn tiền không vượt phần tiền còn giữ; mã giao dịch tránh ghi trùng vào cùng tài khoản. Không yêu cầu nhóm trả trực tiếp cửa hàng trả tổng lần nữa. Khách có thể sao chép tài khoản, số tiền, nội dung và lưu QR; khi quay lại từ ứng dụng ngân hàng, trang cập nhật trạng thái, cảnh báo thay đổi và không yêu cầu trả lại tiền đã trả.

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

## 04. Nhóm trả trực tiếp: chốt tiền và xử lý ngoại lệ

```mermaid
flowchart TD
 A["Trưởng nhóm: chốt danh sách và báo giá"] --> B["Hệ thống: bắt đầu hạn thanh toán riêng 24 giờ"]
 B --> C["Người phải trả: thanh toán theo mã riêng HYG và báo đã trả"]
 C --> D["Trưởng nhóm: đối chiếu khoản thực tế hoặc tiền đã chuẩn bị"]
 D --> E{"Trưởng nhóm: báo cáo đúng?"}
 E -->|Có| F["Trưởng nhóm: xác nhận, giữ số đã thu và lịch sử"]
 E -->|Không| G["Trưởng nhóm: từ chối có lý do để người trả sửa báo cáo"]
 F --> H{"Hệ thống: mọi khoản đã xác nhận và không có tiền cần hoàn?"}
 H -->|Có| I["Trưởng nhóm: gửi đơn qua checkout.php"]
 H -->|Có chênh lệch| J["Trưởng nhóm: đối soát và ghi hoàn tiền thực tế trước khi gửi"]
```

Không xóa người có báo cáo chờ duyệt hoặc còn khoản đã thu. Lịch sử báo, duyệt, sửa, đổi số phải trả, hoàn và loại thành viên được giữ. Bỏ người chưa trả có thể đổi ưu đãi; số phải trả mới không ghi đè tiền thực tế cũ. Nhóm đã có báo hoặc thu tiền vẫn có lối kết toán sau hạn; trưởng nhóm có thể gia hạn có lý do. Muốn hủy sau thu phải xử lý báo cáo và tiền đang giữ trước. Cửa hàng có thể ghi thực thu cho đơn nhóm trong sổ đối soát mà không tạo yêu cầu thanh toán tổng mới.

## 05. Nhóm thu hộ: thành viên → trưởng nhóm → cửa hàng

```mermaid
flowchart TD
 A["Trưởng nhóm: bật thu hộ và cố định tài khoản khi tạo nhóm"] --> B["Thành viên: chọn món; trưởng nhóm chốt báo giá"]
 B --> C["Thành viên: trả trưởng nhóm theo mã thanh toán riêng"]
 C --> D["Thành viên: báo đã trả; trưởng nhóm đối chiếu và xác nhận thu hộ"]
 D --> E["Trưởng nhóm: xác nhận phần tự đặt và ứng trước đã chuẩn bị"]
 E --> F{"Hệ thống: xác nhận đủ và không có chênh lệch phải hoàn?"}
 F -->|Có| G["Trưởng nhóm: gửi đơn; hệ thống lưu tổng phải trả cửa hàng"]
 F -->|Không| H["Trưởng nhóm: xử lý báo sai, tiền chênh lệch hoặc hoàn tiền"]
 G --> I["Trưởng nhóm: mở payment.php và trả tổng đơn cho cửa hàng"]
 I --> J["Nhân viên: kiểm tra và ghi thực thu cửa hàng"]
```

Thu hộ của trưởng nhóm và thực thu cửa hàng là hai lớp riêng. Xác nhận phần tiền đã chuẩn bị không phải bằng chứng ngân hàng hay cửa hàng đã nhận tiền. Chỉ trưởng nhóm hoặc tài khoản thành viên đã gửi đơn mới được báo tổng khoản cửa hàng. Phần trưởng nhóm chuẩn bị, khoản đã thu và khoản hoàn được giữ trong lịch sử nhóm; cửa hàng đối soát theo quy trình 02.

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
 B --> C["Hệ thống: kiểm tra, cấp 1 lượt mỗi 10 ly; không cấp trùng"]
 C --> D{"Có cơ hội và hoạt động sẵn sàng?"}
 D -->|Chưa| E["Khách: giữ cơ hội, chờ cập nhật / hoạt động sẵn sàng"]
 D -->|Có| F["Khách: vào game hoặc tiếp tục bàn đang chơi"]
 F --> G["Khách: ghép đủ 6 cặp trong 12 thẻ"]
 G --> H["Hệ thống: cấp một phiếu và trừ một cơ hội cùng giao dịch"]
 H --> I["Khách: xem điều kiện và dùng phiếu ở đơn sau"]
```

Không có ưu đãi khác trong đơn; chỉ đếm ly đồ uống. Mỗi 10 ly trong cùng một đơn được 1 cơ hội: 9 ly = 0, 10–19 ly = 1, 20–29 ly = 2, 30 ly = 3. Không cộng ly giữa các đơn; topping mua riêng không tính. Giỏ mới và nhóm chốt mới dùng quy tắc này; đơn đã gửi và nhóm đã chốt giữ số lượt trong báo giá cũ. Nhóm cũ mở lại và chốt lại dùng quy tắc mới. Khi xóa người tham gia, giảm lượt theo số ly còn lại, không vượt số lượt đã chốt. Nhóm cấp cho trưởng nhóm. Đánh dấu nhận tiền không tự hoàn tất đơn và không tự cấp cơ hội. Trang thành công có thể tự chuyển khi đủ điều kiện và ở tiền cảnh. Không trừ cơ hội lúc bắt đầu; tải lại tiếp tục cùng bàn. Gửi nhận thưởng lặp không cấp thêm phiếu. Điểm vào: `success.php`, `group/index.php`, `minigame/index.php`.

## 09. Nhân viên nhận, xử lý đơn và đối soát

```mermaid
flowchart TD
 A["Nhân viên: mở admin/index.php hoặc mobile-admin/index.php"] --> B["Nhân viên: lọc chưa trả, chờ đối soát, đã thu hoặc chờ hoàn"]
 B --> C["Nhân viên: mở đơn và kiểm tra nội dung cùng trạng thái tiền"]
 C --> D["Nhân viên: nhận đơn bằng phiên bản hiện tại và lý do"]
 D --> E["Nhân viên: hoàn tất sau khi xử lý đơn"]
 E --> F["Hệ thống: lưu trạng thái và cấp cơ hội theo chính sách hiện hành trong giao dịch"]
 C --> G["Nhân viên: hủy có lý do; nếu còn tiền giữ thì chờ hoàn"]
 G --> H["Nhân viên: hoàn tiền thực tế rồi ghi khoản hoàn"]
 F --> I["Hệ thống: giữ lịch sử trạng thái và tài chính"]
 H --> I
```

Trang cũ không ghi đè phiên bản mới. Luồng hợp lệ: mới → đã nhận hoặc hủy; đã nhận → hoàn tất hoặc hủy; hoàn tất → hủy có lý do; đã hủy không mở lại. Hoàn tất không tự xác nhận tiền vào tài khoản. Hủy giữ chính sách thưởng và phiếu đã sử dụng hiện hành; không tự thu hồi thưởng hoặc khôi phục phiếu. Quản trị/Chủ sở hữu xuất CSV đối soát theo ngày Việt Nam và tài khoản tại admin/payment_export.php; tài khoản chỉ xử lý đơn không có quyền xuất báo cáo tài chính.

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

## Kiểm chứng, triển khai và việc còn lại

Kết quả kiểm chứng thực tế được ghi trong `PAYMENT-FLOW-TEST-REPORT.md`. Chỉ kết quả đã chạy mới được đánh dấu đạt; sơ đồ không phải bằng chứng ngân hàng thực nhận hoặc đã triển khai.

Gói cập nhật đầy đủ bao gồm 19.14.5 và các bản trước. Đích được phép là shop.huiyitea.vn với database và webroot riêng. Xác nhận config.local.php của shop và APP_BASE_URL=https://shop.huiyitea.vn; giữ cấu hình riêng, uploads, icons và .htaccess; không chạy setup.php hay install.sql, không reset dữ liệu. Sao lưu trước cập nhật; sau giải nén di chuyển ZIP ra ngoài webroot và kiểm tra URL cũ không cho tải. Không triển khai order trong yêu cầu này.

Trạng thái khi tạo tài liệu: đã chuẩn bị mã và kiểm thử cục bộ; chưa xác nhận triển khai shop. Bằng chứng nghiệm thu/triển khai sau đó nằm trong báo cáo giao hàng. Cần tài khoản chủ quản để nghiệm thu cPanel/shop, QR ngân hàng thực và quy trình tiền thật. Chưa có webhook ngân hàng, chuyển tiền tự động, hoàn tự động hoặc tự khôi phục quyền đơn khách trên thiết bị khác. Hủy giữ chính sách thưởng/phiếu hiện hành. Không quay về bản cũ sau khi đã ghi sự kiện mới nếu chưa đối soát và có kế hoạch tương thích.

---

<a id="traditional-chinese"></a>

# 19.15.0 — 付款、對帳與歷程優化

記錄日期：2026-10-12，Asia/Saigon。本文件描述 19.15.0 程式；已發布歷史紀錄保持不變。

## 功能與受影響流程

- 新增店家及團購付款事件簿，保存應付、已實收／備妥、退款、理由、操作者及銀行流水號。更正或移除團員不刪除原付款證據。
- 提供拒絕誤報、直接登記現金／轉帳實收、更正、人工退款及少付／多付對帳；不串接銀行，不自動付款或退款。
- 保護待確認團員回報，分開選購與付款期限。已有報款／收款仍保留結清入口，增加有理由的延長與差額檢查。
- 桌面／手機採同一個含版本、理由與歷程的狀態服務；加入付款狀態篩選及有權限的財務 CSV 匯出。
- 同一 session 保存多筆訪客付款權限，回報提示綁訂單，表單驗證失敗保留草稿，同一表單重送回原單。新增複製、QR 及返回頁面更新。
- 以加法式升級資料結構，保留舊訂單、原帳戶／QR 及已核款紀錄；直接付店家的團購不再要求支付全團款。

## 完整操作流程圖

現行角色：擁有者可管理帳號及全部後台；管理員存取授權管理功能；只處理訂單的店員不可存取目錄、設定、文件或匯出財務報表。顧客、會員與團主仍依各自所有權操作。

## 01. 顧客一般訂餐

```mermaid
flowchart TD
 A["顧客：選飲料與優惠"] --> B["顧客：在 checkout.php 填配送及發票"]
 B --> C{"系統：資料是否有效？"}
 C -->|否| D["系統：保存草稿並標示需修正欄位"]
 C -->|是| E["系統：計算金額並以同一交易保存訂單與收款資料"]
 E --> F["系統：依 session 表單識別碼保存送單結果"]
 F --> G["顧客：開啟 success.php 或 payment.php"]
 G --> H["顧客：核對收款對象後付款"]
```

重送同一份已成立表單會返回原訂單。提交結果不明時不鼓勵重新下單。同一 session 最多保留五十筆訪客訂單付款權限；登入、登出或切換帳號會清除舊訪客權限。會員只能查看綁定自己帳號的訂單，不自動將訪客訂單轉給新登入帳號。訪客跨裝置不自動恢復權限。

## 02. 顧客付款與店家對帳

```mermaid
flowchart TD
 A["下單人或團主：開啟自己的 payment.php"] --> B{"系統：訂單是否仍可付款？"}
 B -->|已取消| C["系統：不提供再付款，顯示對帳或退款處理"]
 B -->|有效| D["顧客：核對金額、帳戶及訂單備註"]
 D --> E["顧客：實際轉帳或交付現金"]
 E --> F["顧客：回報已付款，系統保存收款對象及 QR"]
 F --> G["店員：在桌面或手機後台核對實收"]
 G --> H{"店員：對帳結果？"}
 H -->|相符| I["店員：登記實收及銀行流水號"]
 H -->|查無入帳| J["店員：填理由拒絕回報，保留歷程"]
 H -->|少付或多付| K["店員：登記實收並處理差額"]
 I --> L["系統：保留收款事件與操作者"]
 K --> L
```

店員可直接登記已收到現金，不必等待顧客回報。付款回報不能證明銀行入帳。更正及退款登記均為有理由的人工帳務操作，不會向銀行發送轉帳或退款指令。退款不得超過仍持有的款項；同一帳戶的銀行流水號防止重複登記。直接付店家的團購不再要求支付全團款。顧客可複製帳號、金額及備註，放大或保存 QR；由銀行 App 返回時更新狀態，帳戶變更會提示核對及勿重複付款。

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

## 04. 直接付店家的團購：結算與例外處理

```mermaid
flowchart TD
 A["團主：截止選購並固定報價"] --> B["系統：開始獨立二十四小時付款期限"]
 B --> C["應付款人：以專屬 HYG 備註付款並回報"]
 C --> D["團主：核對實際付款或備妥款項"]
 D --> E{"團主：回報是否正確？"}
 E -->|是| F["團主：確認並保存已核對款及歷程"]
 E -->|否| G["團主：填理由拒絕，讓付款人修正回報"]
 F --> H{"系統：全部確認且沒有應退差額？"}
 H -->|是| I["團主：透過 checkout.php 送單"]
 H -->|有差額| J["團主：對帳並先登記實際退款"]
```

待確認回報及仍有已核對款的團員不得直接移除。回報、確認、更正、改價、退款與移除均保留歷程。移除未付款人可能影響優惠；新應付金額不會覆寫原實收證據。已有回報或收款的團購到期後仍可完成結清；團主可附理由延長付款期限。收款後要取消，須先處理回報及仍持有款項。店家可以登記直接付款團購的實收，不另建立要求顧客再付全團的流程。

## 05. 團主代收：團員 → 團主 → 店家

```mermaid
flowchart TD
 A["團主：開團時啟用代收並固定帳戶"] --> B["團員：選購，團主截止並固定報價"]
 B --> C["團員：以專屬付款備註支付團主"]
 C --> D["團員：回報，團主核對並確認代收"]
 D --> E["團主：確認自購與墊付款已備妥"]
 E --> F{"系統：全部確認且無應退差額？"}
 F -->|是| G["團主：送單，系統保存店家應收全團總額"]
 F -->|否| H["團主：處理誤報、差額或退款"]
 G --> I["團主：在 payment.php 支付店家全團款"]
 I --> J["店員：核對並登記店家實收"]
```

團主代收與店家實收是兩層獨立紀錄。團主確認備妥自己的出資，不是銀行或店家已收款證據。只有團主或原下單會員可回報店家全團款。團購核對款、團主出資及退款保留在團購歷程；店家依流程 02 另行核帳。

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
 B --> C["系統：核對資格，每滿十杯發一次；不重複入帳"]
 C --> D{"已有機會且活動準備完成？"}
 D -->|尚未| E["顧客：保留機會，等待更新或活動準備"]
 D -->|已就緒| F["顧客：進入遊戲或接續原牌局"]
 F --> G["顧客：完成 12 張牌的 6 組配對"]
 G --> H["系統：同一交易發一張券並扣一次機會"]
 H --> I["顧客：查看條件，在下一筆合資格訂單使用"]
```

訂單不能併用其他優惠，只計飲品杯數。單筆每滿 10 杯獲 1 次：9 杯 0 次、10–19 杯 1 次、20–29 杯 2 次、30 杯 3 次。杯數不跨單累計，單買配料不計入。新購物車與新截止團購依此計算；已成立訂單、已截止團購保留原報價次數，重新開放再截止才使用新規則。移除團員依剩餘杯數下修，但不超過原截止次數。團購由團主取得。確認收款不會自動完成訂單或發機會。完成頁在前景且符合條件時可自動進入遊戲。開局不扣機會，重新整理可接續；重複領獎不重複發券。入口：`success.php`、`group/index.php`、`minigame/index.php`。

## 09. 店員接單、完成與對帳

```mermaid
flowchart TD
 A["店員：開啟桌面或手機訂單列表"] --> B["店員：篩選未付、待核款、已收或待退款"]
 B --> C["店員：開單並核對品項與付款狀態"]
 C --> D["店員：以目前版本及理由接單"]
 D --> E["店員：處理訂單後標示完成"]
 E --> F["系統：在交易內保存狀態及依既有政策發放抽獎機會"]
 C --> G["店員：附理由取消，有持有款則待退款"]
 G --> H["店員：實際退款後登記退款"]
 F --> I["系統：保留訂單狀態與帳務歷程"]
 H --> I
```

舊頁面不能覆寫新版本。合法流程：新單 → 接單或取消；已接單 → 完成或取消；完成 → 有理由的取消；取消後不重新開單。完成不自動確認銀行入帳。取消沿用已發放機會與已用優惠券的既有政策，不自動收回獎勵或返還券。擁有者／管理員可在 admin/payment_export.php 依越南日期及帳戶匯出 CSV；只處理訂單的角色無財務報表匯出權。

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

## 實際驗證、部署與未完成事項

實際執行的結果記錄於 `PAYMENT-FLOW-TEST-REPORT.md`；只標示已跑過的檢查，流程圖不代表銀行已入帳或已完成線上部署。

完整更新包包含 19.14.5 及前版。此次部署目標只限 shop.huiyitea.vn 的獨立資料庫與網站目錄。核對 shop 的 config.local.php 及 APP_BASE_URL=https://shop.huiyitea.vn，保留私有設定、uploads、icons 及 .htaccess；不執行 setup.php、install.sql 或重置資料。更新前備份；解壓後將 ZIP 移至網站目錄外，確認原公開下載網址不可取用。本次不部署 order。

文件產製時狀態：程式及本機測試已準備，尚未核實 shop 部署；後續部署／驗收證據另列交付報告。主機及 shop 後台驗收需可用管理登入；真實銀行 QR、入帳與實際退款操作仍需帳戶持有人核對。尚無銀行 webhook、自動轉帳、自動退款或訪客跨裝置權限恢復。取消沿用既有獎勵／優惠券政策。新金流事件寫入後，回退舊版前須先完成對帳與相容處理。

HUIYI_19150_DOC
];
