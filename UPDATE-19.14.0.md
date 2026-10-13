<a id="tieng-viet"></a>

# 19.14.0 — Ba vai trò quản trị và menu ô biểu tượng

Ngày: 2026-10-08, UTC+7. Tiếp tục mã 19.13.0, giữ thanh toán 19.11.0, sơ đồ 19.12.0 và mỗi 10 ly một cơ hội của 19.13.0.

## 1. Trạng thái dự án đã kiểm tra

Kiểm tra chỉ đọc ngày 2026-10-08 lúc 10:42 UTC+7: `shop.huiyitea.vn/VERSION` trả 19.12.0; `order.huiyitea.vn/VERSION` trả 19.2.2. Hai trang công khai đọc bình thường. Đây là số VERSION công khai, không phải đối chiếu toàn bộ file hoặc cơ sở dữ liệu trên hosting. ZIP 19.11.0 trong bảng bên không phải gói mới nhất; mã trước lần sửa này đã là 19.13.0.

19.14.0 **chưa triển khai shop hoặc order**. Không tạo, sửa hoặc xóa tài khoản thật; không commit/push Git trong lần sửa này. Gói cập nhật tiếp tục nguồn hiện tại, không quay lại 19.11.0.

## 2. Ma trận quyền đã xác nhận

| Chức năng | Chủ sở hữu | Quản trị viên | Nhân viên đơn hàng |
| --- | --- | --- | --- |
| Tạo, sửa, xóa tài khoản; đổi vai trò hoặc mật khẩu | Có | Không | Không |
| Danh sách, chi tiết, nhận đơn và đổi trạng thái | Có | Có | Có |
| Chỉ định chi nhánh trong đơn, đối soát khoản tiền đơn | Có | Có | Có |
| Thông báo, chuông và quản trị đơn trên điện thoại | Có | Có | Có |
| Danh mục, hoạt động, thành viên, chi nhánh, tài khoản ngân hàng, thiết lập và tài liệu | Có | Có | Không |

Chủ sở hữu và quản trị viên chỉ khác quyền quản lý tài khoản. Nhân viên đơn hàng xem các đơn trong hệ thống; bản này chưa thêm phân quyền theo chi nhánh. Thông tin chi nhánh/nhận tiền cần cho xử lý đơn vẫn có trong chi tiết đơn, nhưng nhân viên đơn hàng không sửa danh mục chi nhánh hoặc tài khoản ngân hàng.

Ngoài quản trị, nhân viên không được dùng override quản lý thành viên hoặc lấy QR chi nhánh bằng ID đơn thuần; QR gắn với đơn hợp lệ vẫn có quyền. Tài khoản thành viên cá nhân vẫn theo quyền khách hàng bình thường.

Nhân viên đơn hàng chỉ được các route quản trị `index.php`, `order.php`, `api_new_orders.php`, `push_subscription.php`, `login.php`, `logout.php`. Truy cập trực tiếp GET/POST vào tính năng khác bị 403 phía máy chủ; không chỉ ẩn menu. Route quản trị mới mặc định không mở cho vai trò này.

## 3. Quản lý tài khoản

Chủ sở hữu mở **Tài khoản → Tạo tài khoản**, nhập tên hiển thị tùy chọn, tên đăng nhập, vai trò và hai lần mật khẩu. Tên đăng nhập mới dài 3–80 ký tự chữ/số hoặc `. _ @ -`, bắt đầu bằng chữ/số. Mật khẩu ít nhất 8 ký tự, tối đa 72 bytes; lưu bằng `password_hash`, không lưu hoặc hiển thị bản rõ.

Mỗi thẻ mở **Sửa tài khoản** để đổi tên, tên đăng nhập, vai trò hoặc mật khẩu. Để trống hai ô mật khẩu khi giữ mật khẩu cũ. Sau sửa, mọi thiết bị của tài khoản phải đăng nhập lại. Sửa chính mình sẽ đăng xuất sau khi lưu. Xóa yêu cầu xác nhận trong giao diện và hủy quyền đăng nhập của tài khoản. Không xóa hoặc hạ vai trò chủ sở hữu cuối cùng, kể cả hai chủ sở hữu thao tác đồng thời.

Mỗi thao tác kiểm tra session, CSRF, quyền thực tế trong DB và revision. Khóa hàng theo thứ tự cố định để bảo vệ số chủ sở hữu. Tên trùng, dữ liệu/ID/role sai và bản sửa cũ bị từ chối. Nhật ký ghi người, tài khoản đích, thao tác và thông tin không bí mật; không lưu mật khẩu hoặc hash trong audit. Tìm kiếm thẻ diễn ra trong trình duyệt, không gửi thông tin tài khoản tới dịch vụ ngoài.

## 4. Giao diện theo ảnh tham chiếu

Menu ngang đổi thành thanh trái có các nhóm **Đơn hàng**, **Danh mục & hoạt động**, **Chi nhánh & thành viên**, **Hệ thống & quyền**. Mỗi mục là ô biểu tượng với nhãn Việt/Trung; khi chọn English hiển thị English/Việt. Mục đang mở có viền/biểu tượng đỏ. Giữ các route hiện có, không thêm module giả theo các mục chưa tồn tại trong ảnh.

Thanh trên nền trắng giữ ngôn ngữ, thông báo và chuông; góc tài khoản hiển thị tên/vai trò. Máy tính có nút thu gọn thanh trái; màn hình nhỏ có menu mở, lớp nền và Escape đóng. Không có JavaScript vẫn dùng menu và form phía máy chủ. Tài khoản hiển thị thành thẻ hai cột trên máy tính, một cột trên điện thoại. Biểu tượng SVG nhỏ viết trong source, không có CDN/font icon hay thư viện frontend mới cần cài ở hosting.

Nhân viên đơn hàng chỉ có ô Đơn hàng và Nhận đơn điện thoại. Quản trị viên không có ô quản lý tài khoản. Giữ cơ chế thông báo đơn, đăng ký push và chuông; ảnh kiểm thử là dữ liệu giả, không phải screenshot nghiệm thu shop.

## 5. Nâng cấp dữ liệu và tương thích

Bổ sung `role`, `display_name`, `auth_version` vào bảng `admins` và bảng `admin_account_audit`; ghi marker chỉ khi nâng cấp thành công. Không chạy lại installer, không xóa dữ liệu, không đổi mật khẩu cũ.

Lần nâng cấp đầu, nếu chưa có chủ sở hữu thì tài khoản có **ID nhỏ nhất** được đặt chủ sở hữu, những tài khoản khác là quản trị viên. Trước triển khai, kiểm tra đó có đúng tài khoản gốc cần giữ quyền cao nhất. Sau đăng nhập, chủ sở hữu có thể tạo thêm chủ sở hữu bằng giao diện. Cài mới từ source mới tạo tài khoản đầu là chủ sở hữu.

Session cũ chỉ được tiếp tục nếu tài khoản chưa từng sửa (`auth_version=1`). Tài khoản đã sửa, đổi mật khẩu/vai trò hoặc xóa không được dùng session cũ để vào desktop/mobile/API. Phiên bản xác thực không được lấy từ role gửi lên trình duyệt.

Thanh toán khách hàng, trưởng nhóm thu hộ, chỉ định cửa hàng và quy tắc mỗi 10 ly giữ nguyên. Xác nhận tiền vẫn cần khách đã báo hợp lệ và nhân viên kiểm tra thực nhận; không bỏ bước này để làm kiểm thử qua. Vai trò mới không tự trả tiền hoặc tự cấp cơ hội trò chơi. Bộ sơ đồ đầy đủ lịch sử 19.13.0 được giữ nguyên; ma trận quyền trong bản này là quy tắc hiện tại.

## 6. Kiểm chứng thực tế và giới hạn

Đã chạy trong nguồn/DB riêng và HTTP/MariaDB chỉ trên loopback; email, cURL và API ngoài của app bị chặn. Không sử dụng dữ liệu thật hoặc thay đổi tài khoản thật.

- Migration từ bảng `admins` cũ thật, giữ nguyên hash, bảo vệ chủ sở hữu, ba vai trò, đầu vào và session: 49 kiểm tra đạt.
- HTTP thực tế: 85 khẳng định và 90 kiểm tra helper/route đạt; bao gồm GET/POST bị cấm, CSRF, desktop/mobile login, đổi mật khẩu, role, xóa và hủy session.
- Nhân viên đơn hàng thực sự đọc chi tiết, nhận một đơn giả và xác nhận tiền mặt **sau khách báo**; không chỉ kiểm tra đường dẫn.
- Ba lượt hai chủ sở hữu cùng tự hạ vai trò: mỗi lượt chỉ một thành công; một bị chặn vì là chủ sở hữu cuối.
- Chrome: 35 kiểm tra đạt; Việt/Trung/English ở desktop, chiều rộng 1440/768/390/320, tìm kiếm, menu, Escape, role và fallback không JS. Đã chụp ảnh desktop, mobile và nhân viên đơn hàng.
- Regression: tính nhóm 2.832, phần thưởng 69, nhóm trực tiếp 73, HTTP 77, bảo vệ 29, đồng thời 52, chuyển khoản mới 69 + 59 helper, khóa khoản nhận 41 và frontend 16 kiểm tra đạt.
- Ưu đãi/mỗi 10 ly trực tiếp 236 và migration 41 kiểm tra đạt. Không dựa vào kết quả 19.13.0 để suy đoán bản mới.

Đã sửa lỗi hẹp màn hình do style ngôn ngữ cũ chiếm toàn chiều rộng của toolbar mới. Lần thêm kiểm thử nhân viên xác nhận cash ban đầu thiếu bước khách báo; đã sửa fixture theo quy trình đúng, không nới logic khoản tiền. Dịch vụ kiểm thử đều đã dừng. Số kiểm tra tài liệu/gói cuối cùng và chi tiết xem báo cáo đi kèm.

Chưa nghiệm thu tài khoản thật, push trên điện thoại thật, screenshot shop, phần mềm ngân hàng hoặc rollout production. Không có kiểm tra độ bền kéo dài/thử tải hosting trong bản này.

## 7. Cập nhật shop và rollback

1. Sao lưu file shop, DB và cấu hình. Xác nhận DB shop độc lập và `APP_BASE_URL=https://shop.huiyitea.vn`; không áp dụng vào thư mục/order DB.
2. Gói dành cho website đã cài: hợp nhất theo đường dẫn, giữ `config.php`, `.local.php`, `.htaccess`, `uploads/` và icons. Không cài lại, không xóa website.
3. DB cần quyền ALTER/CREATE và ghi marker. Mở trang đăng nhập/quản trị để chạy migration. Kiểm tra tài khoản gốc ID nhỏ nhất và vai trò chủ sở hữu.
4. Trong shop tạo tài khoản thử ba vai trò; kiểm tra owner-only, nhân viên chỉ đơn, session sau đổi password/role, desktop/mobile và các menu. Những thao tác tài khoản thật cần chủ sở hữu tự xác nhận.
5. Kiểm tra VERSION 19.14.0, Nhà phát triển và tải markdown. Gói đầy đủ có các bản 19.11–19.13, không cần áp dụng ZIP từng bản trước.
6. Chuyển ZIP khỏi webroot; xác nhận URL ZIP không tải công khai. Chỉ sau nghiệm thu shop mới bố trí order.

Không chỉ chép code cũ để rollback: bản cũ không có vai trò nhân viên đơn hàng và có thể cho tài khoản đó quyền quản trị đầy đủ. Dừng tiếp nhận, sao lưu tài khoản/audit, và thiết kế phương án rollback giữ được giới hạn quyền trước khi thực hiện. Không xóa bảng audit hoặc mất lịch sử để quay lại.

---

<a id="traditional-chinese"></a>

# 19.14.0 — 三角色帳號權限與圖示卡片導覽

日期：2026-10-08，UTC+7。以 19.13.0 為基礎，保留 19.11.0 付款、19.12.0 流程圖與 19.13.0 每滿十杯一次抽獎。

## 1. 已核對的專案狀態

2026-10-08 10:42 UTC+7 唯讀核對：`shop.huiyitea.vn/VERSION` 為 19.12.0；`order.huiyitea.vn/VERSION` 為 19.2.2，兩站公開首頁正常。這是公開 VERSION 標記，不代表已逐檔核對主機程式或資料庫。側邊 19.11.0 ZIP 不是最新更新包；本次開始時本機已為 19.13.0。

19.14.0 **尚未部署 shop 或 order**，沒有新增、修改或刪除真實帳號，也沒有建立 Git commit／push。本版延續目前程式，不退回 19.11.0。

## 2. 已確認權限矩陣

| 功能 | 擁有者 | 管理員 | 操作員 |
| --- | --- | --- | --- |
| 新增、編輯、刪除帳號，變更角色／密碼 | 可 | 不可 | 不可 |
| 訂單列表、明細、接單及狀態更新 | 可 | 可 | 可 |
| 訂單內指派門市及核對店家收款 | 可 | 可 | 可 |
| 訂單通知、鈴聲及手機接單 | 可 | 可 | 可 |
| 商品、活動、會員、門市、銀行帳戶、設定及開發文件 | 可 | 可 | 不可 |

擁有者與管理員只差帳號管理權限。操作員可處理系統內的訂單，沒有另加門市範圍限制；明細中保留處理訂單必要的門市／收款資料，但不能修改門市清單或銀行帳戶設定。

後台以外也封鎖操作員的會員管理 override 與只帶門市 ID 的 QR 讀取；合法訂單綁定的收款 QR 仍可讀取，個人會員身份仍按一般顧客權限使用。

操作員後台白名單僅有 `index.php`、`order.php`、`api_new_orders.php`、`push_subscription.php`、`login.php`、`logout.php`。其他管理功能的直接 GET／POST 均由伺服器回應 403，不只是隱藏選單；未來新增的後台路由預設不對操作員開放。

## 3. 帳號管理

擁有者進入 **帳號管理 → 新增帳號**，填寫選用的顯示名稱、登入帳號、角色及兩次密碼。新帳號為 3–80 個英數字或 `. _ @ -`，首字須為英數字；密碼至少 8 個字元、最多 72 bytes，使用 `password_hash`，不保存或顯示明文。

帳號卡片展開 **編輯帳號** 可改名稱、帳號、角色或密碼，密碼兩欄留空則保留原密碼。修改後該帳號所有裝置都須重新登入；修改自己時保存後登出。刪除須在介面確認，該帳號所有登入失效。不能刪除或降級最後一位擁有者，即使兩位擁有者同時操作也會保護最後一位。

每次操作檢查 session、CSRF、資料庫實際權限及修訂。固定順序鎖定帳號資料列，保護擁有者數量；重複帳號、不合法資料／ID／角色及過期修訂均拒絕。操作紀錄只保存操作者、目標、操作與非機密資料，不保存密碼或 hash。搜尋在瀏覽器本機完成，不將帳號資料傳給外部服務。

## 4. 參照附圖的介面

原橫向選單改為左側 **接單作業、商品與活動、門市與會員、系統與權限** 四組。每項是圖示卡片，繁中／越文雙語標籤，English 模式為英／越；目前頁面使用紅色框線與圖示。保留已有路徑，不把參考圖中尚未存在的功能做成假模組。

白色頂欄保留語言、推播與鈴聲；帳號角落顯示名稱及角色。桌面可收合側欄，手機有開啟、遮罩及 Escape 關閉；停用 JavaScript 仍有導覽及伺服器表單。帳號列表桌面兩欄、手機單欄。圖示為程式內的小型 SVG，主機不需新增 CDN、圖示字型或前端套件依賴。

操作員僅看到訂單管理與手機接單；管理員沒有帳號管理卡片。保留訂單提醒、push 註冊與鈴聲原有機制。QA 截圖全部為虛構測試資料，不是 shop 線上驗收畫面。

## 5. 資料升級與相容性

在 `admins` 加入 `role`、`display_name`、`auth_version`，新增 `admin_account_audit`，升級成功才保存 marker。不重跑安裝、不清空資料或變更既有密碼。

第一次升級若尚無擁有者，將 **ID 最小的既有帳號** 設為擁有者，其他既有帳號為管理員；部署前須核對這是否為要保留最高權限的原始帳號。登入後擁有者可再新增擁有者。使用本版 source 全新安裝時，首個帳號明確為擁有者。

舊 session 只在帳號尚未修改（`auth_version=1`）時延續；已修改、改密碼／角色或刪除的帳號不能以舊 session 存取桌面、手機或 API。角色與驗證版本不信任瀏覽器送入的角色值。

客戶付款、團主代收、門市派單及每滿十杯規則維持。核款仍須顧客合法回報及店員確認實際收款，不為測試通過而省略；新角色不自動支付或發放遊戲機會。歷史 19.13.0 完整流程圖保留，本版權限矩陣為目前規則。

## 6. 實際驗證與限制

以獨立來源／資料庫和只監聽 loopback 的 HTTP／MariaDB 執行；封鎖應用程式外寄、cURL 與外部 API。沒有真實客戶資料或真實帳號變更。

- 真正舊 `admins` 結構 migration、hash 保留、擁有者防護、三角色、輸入與 session：49 項通過。
- 真實 HTTP：85 項斷言及 90 項 helper／route 通過，包含禁止 GET／POST、CSRF、桌面／手機登入、改密碼／角色、刪除與 session 撤銷。
- 操作員實際讀取明細、接一筆虛構訂單，並在**顧客回報後**確認現金收款，不只驗證路徑可開啟。
- 三輪兩位擁有者同時降級自己，每輪只一位成功、另一位被最後擁有者保護拒絕。
- Chrome：35 項通過；桌面繁中／越文／英文，1440／768／390／320 寬度、搜尋、側欄、Escape、角色及無 JS fallback；有桌面、手機及操作員實際截圖。
- Regression：團購計算 2,832、獎項 69、直接整合 73、HTTP 77、防護 29、併發 52、新轉帳 69＋59 helper、收款鎖 41、前端 16 項通過。
- 優惠／每十杯直接 236、migration 41 項通過，沒有把 19.13.0 舊測試當成本版結果。

已修正舊語言切換器占滿新工具列而造成的窄螢幕溢位。新增操作員現金核款測試最初缺少顧客回報，已按正確流程修正 fixture，沒有放寬核款程式。測試服務均已停止；最終文件／更新包的檢查項目及數量見附帶報告。

真實帳號、實機 push、shop 截圖、銀行 App 及正式 rollout 尚未驗收；本版沒有長時間壓力或主機效能測試。

## 7. shop 更新及回復

1. 備份 shop 程式、資料庫及設定，確認獨立資料庫與 `APP_BASE_URL=https://shop.huiyitea.vn`，不得套用至 order 目錄／資料庫。
2. 既有網站更新包按相對路徑合併，保留 `config.php`、`.local.php`、`.htaccess`、`uploads/` 及 icons，不重新安裝或清空網站。
3. 資料庫須有 ALTER／CREATE 及 marker 寫入權限；開啟登入／管理頁執行 migration，核對最小 ID 原始帳號及擁有者角色。
4. 在 shop 建三角色測試帳號，驗收 owner-only、操作員只處理訂單、改密碼／角色後的 session、桌面／手機及選單。真實帳號操作仍由擁有者確認。
5. 核對 VERSION 19.14.0、開發者文件與下載。完整包已含 19.11–19.13 功能，不需先逐個套用舊 ZIP。
6. 移走 webroot 中的 ZIP，確認不能公開下載；shop 驗收完成後才安排 order 更新。

不能只覆蓋舊程式回退：舊版沒有操作員角色，可能讓該帳號取得完整管理權限。須先暫停接單、備份帳號／audit，設計並核對仍保有權限限制的回復方式，不能刪除 audit 或丟失帳號紀錄來降版。
