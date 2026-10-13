<?php
if (!defined('HUIYI_DEVELOPER_DOCS')) { http_response_code(404); exit; }
return ['version'=>'19.11.0','markdown'=><<<'PAYMENT_RELEASE_19110'
<a id="tieng-viet"></a>

# 19.11.0 — Tài khoản chi nhánh, trưởng nhóm thu hộ và xác nhận cửa hàng nhận tiền

Ngày ghi nhận: 2026-10-07, UTC+7.

## 1. Phạm vi và quyết định đã xác nhận

- Vẫn cho phép chọn chuyển khoản và gửi đơn khi chưa cấu hình tài khoản. Thông báo rõ cửa hàng sẽ cung cấp thông tin khi xác nhận đơn; không cung cấp nút báo đã trả tới một tài khoản chưa xác định.
- Bổ sung cả thông tin chuyển khoản chi nhánh trong quản trị và tài khoản riêng của trưởng nhóm khi tạo nhóm.
- Quy trình thu hộ: **thành viên trả cho trưởng nhóm → trưởng nhóm xác nhận thu hộ → trưởng nhóm gửi đơn → trưởng nhóm trả toàn bộ tiền đơn cho cửa hàng → nhân viên kiểm tra và xác nhận cửa hàng đã nhận tiền**.
- Giữ quy tắc đề xuất chi nhánh sau khi đơn được tạo. Chỉ chi nhánh do nhân viên thực sự chỉ định mới là nơi nhận tiền; đề xuất không tự chuyển tài khoản.

## 2. Thay đổi chức năng

### Tài khoản chi nhánh

Tại **Quản trị → Chi nhánh → Thêm / Sửa**, nhân viên được phép nhập ngân hàng, số tài khoản, tên chủ tài khoản, bật/tắt tài khoản và tải hoặc xóa ảnh QR. Ba trường chữ bắt buộc khi bật tài khoản. Dữ liệu tài khoản được lưu trong cùng giao dịch và kiểm tra phiên bản với dữ liệu chi nhánh; lỗi ảnh hoặc thông tin tài khoản không lưu một nửa thay đổi.

### Trưởng nhóm thu hộ

Khi tạo nhóm, mở mục thu hộ, bật lựa chọn và nhập đủ ngân hàng/số tài khoản/chủ tài khoản; QR là tùy chọn. Nhóm mới mặc định thanh toán riêng để thành viên trả phần của mình cho trưởng nhóm. Nếu đổi sang thanh toán chung, trưởng nhóm trả hộ cả nhóm. Tài khoản thu hộ cố định ngay khi tạo; không có thao tác thay QR sau khi thành viên đã trả.

- Thành viên chuyển khoản thấy tài khoản và QR của trưởng nhóm, không dùng tài khoản cửa hàng.
- Thành viên tiền mặt giao tiền cho trưởng nhóm rồi báo đã trả.
- Thành viên chọn trưởng nhóm ứng trước không phải tự chuyển tiền.
- Trưởng nhóm xác nhận tiền của mình và khoản trả hộ đã chuẩn bị; thao tác này **không** xác nhận cửa hàng nhận tiền.
- Số tiền tự đặt/ứng trước của trưởng nhóm khác với tổng tiền trả cửa hàng. Tổng tiền cửa hàng lấy từ đơn đã lưu, không lấy từ số tiền trình duyệt gửi lên.

### Trả tiền cửa hàng và đối soát

Đơn thông thường và nhóm thu hộ gửi đơn trước, sau đó mở trang thanh toán có mã đơn:

1. Chưa chỉ định chi nhánh: dùng tài khoản trụ sở đang hoạt động; nếu không có thì chờ thông tin từ cửa hàng.
2. Đã chỉ định chi nhánh: dùng tài khoản chi nhánh đang bật; nếu chưa cấu hình thì hiển thị lời nhắc chờ cửa hàng cung cấp.
3. Người trả báo đã trả đúng số tiền vào đúng tài khoản, hoặc đã giao tiền mặt. Trạng thái là **đã báo, chờ cửa hàng xác nhận**, không tự trở thành đã nhận tiền.
4. Quản trị máy tính và điện thoại đều có nút xác nhận riêng. Nhân viên phải kiểm tra chuyển khoản/tiền mặt thực nhận; lưu thời gian và người xác nhận.
5. Nếu nơi nhận thay đổi trước khi báo đã trả, yêu cầu cũ bị từ chối. Nếu đã chuyển vào tài khoản cũ, không chuyển lại; liên hệ cửa hàng đối soát tài khoản ban đầu. Sau khi báo đã trả, lưu bản chụp nơi nhận tiền và QR. Việc sửa tài khoản hoặc đổi chi nhánh giao hàng không yêu cầu khách trả lần nữa.

Trang thành công, trang nhóm đã gửi và danh sách đơn của thành viên có điểm truy cập thanh toán phù hợp quyền sở hữu. Chỉ khách sở hữu đơn hoặc trưởng nhóm được báo khoản cửa hàng; thành viên khác không được báo thay tổng tiền nhóm. Thành viên đã đăng nhập đúng tài khoản người gửi có thể mở lại khoản thanh toán của mình trên thiết bị khác.

### Sửa lỗi phát hiện trước đó

- JavaScript nhận cả radio và trường ẩn của phương thức thanh toán; nhóm thanh toán chung không còn tự ẩn phần ngân hàng/QR.
- Tăng phiên bản script và Service Worker để tránh dùng mã cũ từ cache.
- Email HTML và văn bản thuần phân biệt báo đã trả/chờ trưởng nhóm, xác nhận thu hộ và khoản cửa hàng; không gắn nhãn tự báo cho quy trình có trưởng nhóm duyệt. Đơn giao thức cũ vẫn giữ mô tả lịch sử.

## 3. Tương thích và dữ liệu

Tự bổ sung ba bảng độc lập: `transfer_store_accounts`, `transfer_group_accounts`, `transfer_order_receipts`. Ghi phiên bản schema sau khi tạo thành công và dùng khóa khi nâng cấp. Không xóa bảng cũ, không chạy lại `install.sql`, không thay đổi tài khoản riêng trong cấu hình.

Nhóm cũ hoặc nhóm không bật thu hộ vẫn dùng quy trình trả trực tiếp cửa hàng trước đây. **Không tạo khoản yêu cầu trả toàn bộ lần thứ hai cho các nhóm này.** Bản ký cũ không thêm thành phần thu hộ; chỉ nhóm thu hộ mới ký thêm chế độ này. Đơn cũ không được tự suy đoán là đã nhận tiền hoặc chưa nhận tiền. Tổng bằng 0 được ghi “không cần thanh toán”, không giả lập nhân viên đã xác nhận.

Trạng thái nhận tiền độc lập với trạng thái chế biến/giao đơn và phát cơ hội trò chơi. Không thay đổi quy tắc thưởng, ưu đãi, phát hành cơ hội hoặc tự đánh dấu đơn hoàn tất.

## 4. An toàn

- Các thao tác cần phiên người dùng hợp lệ, CSRF và quyền tương ứng; báo khoản cửa hàng ràng buộc số tiền, nơi nhận, revision và dấu vết QR hiện tại.
- Khóa hàng và phiên bản khoản thanh toán ngăn báo/nút xác nhận lặp tạo nhiều lần cập nhật.
- QR chi nhánh/thu hộ lưu dạng dữ liệu ảnh trong cơ sở dữ liệu, không tạo file PHP hoặc URL ảnh trưởng nhóm công khai. Chỉ JPG/PNG/WEBP hợp lệ, tối đa 2MB và 4096×4096; từ chối SVG, file giả và đường dẫn giả.
- URL QR kiểm tra quyền, trả đúng MIME và `nosniff`, không lưu cache riêng tư. Thông tin tài khoản được escape trước khi hiển thị.
- Đơn hủy không cho khách tiếp tục báo thanh toán. Trường hợp đã trả và cần hoàn tiền phải đối soát với cửa hàng, không tự hoàn tiền.

## 5. Kiểm chứng thực tế

Tất cả kiểm tra dưới đây chạy trong bản sao nguồn riêng và MariaDB/HTTP chỉ nghe `127.0.0.1`, cơ sở dữ liệu dùng riêng cho kiểm thử. Email, Google/iPOS và kết nối ngoài của ứng dụng đã chặn. Không chuyển tiền thật, không gửi email thật, không tạo đơn ở shop hoặc order.

| Nhóm kiểm tra | Kết quả |
| --- | --- |
| Tính tiền nhóm thuần | 2.832 kiểm tra đạt |
| Tính phần thưởng thuần | 69 kiểm tra đạt |
| Nhóm duyệt thanh toán trực tiếp | 73 kiểm tra đạt |
| Route HTTP nhóm hiện có | 77 kiểm tra đạt |
| Bảo vệ cuối cùng của nhóm | 29 kiểm tra đạt |
| Mutation/gửi đơn đồng thời cũ | 52 kiểm tra đạt |
| Nhánh mới, thu hộ, gửi đơn và đối soát | 69 khẳng định + 59 kiểm tra HTTP/helper đạt |
| Khóa khoản thanh toán, quyền và tổng bằng 0 | 41 kiểm tra đạt; ba lượt đồng thời báo lặp và ba lượt đồng thời xác nhận |
| JavaScript, radio/trường ẩn và cache | 16 kiểm tra đạt |
| Tài liệu phát triển thuần | 520 kiểm tra đạt |
| Trang/tải tài liệu quản trị thực tế | 9 khẳng định + 13 kiểm tra HTTP/helper đạt |

Upload dùng multipart thật; route quản trị máy tính và điện thoại, QR có/không có quyền, thay nơi nhận trước/sau khi báo, CSRF, giả số tiền, phương thức sai, SVG và mail hai định dạng đã được kiểm chứng. Đã dừng cả hai dịch vụ kiểm thử sau khi hoàn tất. Lần chạy đầu có lỗi khóa `text` của mã kiểm thử email; API thực dùng `body`, đã sửa mã kiểm thử và chạy lại toàn bộ thành công. Không thay API email để chiều theo kiểm thử sai.

## 6. Trạng thái triển khai và bước tiếp theo

- Mã nguồn và kiểm thử cục bộ đã hoàn thành. Phiên bản này **chưa triển khai lên shop hoặc order**; không coi kết quả của 19.10.0 là nghiệm thu cho 19.11.0.
- Đích thử nghiệm là `shop.huiyitea.vn`, không phải order. Trước cập nhật phải kiểm tra `config.local.php` trỏ đúng cơ sở dữ liệu shop độc lập và `APP_BASE_URL=https://shop.huiyitea.vn`.
- Sao lưu file website và cơ sở dữ liệu shop. Hợp nhất gói cập nhật vào đúng thư mục website shop, giữ `config.php`, mọi `.local.php`, `uploads/`, `.htaccess` và biểu tượng. Không xóa website và không chạy cài đặt lại.
- Tài khoản DB shop cần quyền tạo ba bảng mới và ghi marker schema. Mở trang quản trị để chạy nâng cấp, xác nhận VERSION, mục Nhà phát triển và kiểm thử chi nhánh/thu hộ trong môi trường shop riêng.
- Sau cập nhật, chuyển ZIP ra ngoài webroot; xác nhận URL ZIP cũ trả 403/404. Công khai file 0644, thư mục 0755, cấu hình giữ quyền ban đầu.
- Tài khoản/QR ngân hàng thực, việc quét bằng ứng dụng ngân hàng, đối soát số tiền thật, kiểm thử trình duyệt điện thoại trên shop và triển khai order vẫn chưa nghiệm thu. Không có kết nối ngân hàng tự xác minh, tự thanh toán hoặc tự hoàn tiền.
- Nếu cần quay lại bản cũ, dừng nhận đơn mới trước và sao lưu toàn bộ ba bảng mới; không xóa dữ liệu báo/thu tiền. Nhóm thu hộ đang mở và đơn thu hộ đã tạo không tương thích với quy tắc trả trực tiếp của mã cũ; không chỉ ghi đè mã cũ rồi tiếp tục nhận tiền.

---

<a id="traditional-chinese"></a>

# 19.11.0 — 門市收款資訊、團主代收與店家核款

紀錄日期：2026-10-07，UTC+7。

## 1. 範圍與已確認決策

- 沒有收款帳戶時仍可選轉帳並送出訂單；清楚提示店家在確認訂單時另行提供資訊，不提供向未知帳戶回報已付款的按鈕。
- 同時提供後台門市收款資訊與開團時團主自己的收款帳戶。
- 代收流程：**團員付給團主 → 團主確認代收 → 團主送出訂單 → 團主支付全團款給店家 → 店員核對實際收款**。
- 保留訂單成立後才建議門市的安排；只有店員實際確認指派的門市才成為收款對象，建議門市不會自動改變帳戶。

## 2. 功能變更

### 門市收款資訊

於 **後台 → 門市 → 新增／編輯** 填寫銀行、帳號、戶名，啟用／停用帳戶，並上傳或移除 QR。啟用時三項文字必填。收款資料與門市資料使用同一交易及修訂檢查，圖片或帳戶驗證失敗不會只保存部分變更。

### 團主代收

開團時展開代收選項、勾選並填妥銀行／帳號／戶名；QR 可選。新代收團預設分開結帳，讓團員付自己的金額給團主。若改為一起結帳，表示團主代付整團。帳戶在開團時固定，沒有在團員付款後任意更換 QR 的操作。

- 轉帳團員看見團主帳戶與 QR，不使用店家帳戶。
- 現金團員交給團主後再回報付款。
- 選團主墊付者不需自己轉帳。
- 團主確認已備妥自購及墊付款；此操作**不是**店家收款確認。
- 團主自己的出資額與店家應收全團款分開顯示。店家應收取自已保存訂單，不採用瀏覽器傳入的金額。

### 支付店家與核款

一般訂單與代收團購先送出訂單，再進入帶有訂單編號的付款頁：

1. 尚未確認指派門市：使用啟用的總部帳戶；沒有帳戶則等待店家提供資訊。
2. 已指派門市：使用該門市啟用的帳戶；尚未配置則明確提示等待店家提供。
3. 付款者確認已付正確金額給正確帳戶，或已交付現金。狀態為 **已回報、待店家核款**，不自動視為入帳。
4. 桌面及手機後台均有獨立收款確認。店員須核對實際轉帳／現金，並保存核款時間與確認人。
5. 回報前若收款資訊已改變，拒絕舊回報；已付舊帳戶者切勿重複付款，須聯繫店家核對原收款帳戶。回報後保存收款對象與 QR 快照；改帳戶或更換配送門市，不會要求客戶再付款一次。

一般訂單完成頁、已送單的團購頁及會員訂單列表提供符合權限的付款入口。只有訂單本人或團主可回報店家款項，其他團員不能代為回報全團款。原下單會員登入同一帳號時，可跨裝置返回自己的付款頁。

### 修正原先發現的問題

- JavaScript 同時識別付款 radio 與固定隱藏欄位；團購一起結帳不再自動隱藏銀行／QR 區塊。
- 更新腳本及 Service Worker 快取版本，避免沿用舊程式。
- HTML 與純文字通知信分清自行回報／待團主確認、團主代收與店家款項；有團主審核的流程不再誤標為純自行回報。舊協定訂單保留歷史語意。

## 3. 相容性與資料

加法式新增三個獨立資料表：`transfer_store_accounts`、`transfer_group_accounts`、`transfer_order_receipts`；建立成功後才保存 schema 版本，升級時使用鎖。沒有刪除舊資料表、重跑 `install.sql` 或改寫私有帳戶設定。

既有團購與未勾選代收的新團，仍使用原本直接付店家的流程。**不會對這些團購再建立一筆要求支付全團款的紀錄。** 舊版簽章不加入代收成分，只有新代收團才簽入代收模式。舊訂單不擅自推定已入帳或未入帳。金額為零記為「無需付款」，不假設店員已核款。

收款狀態與出餐／配送狀態及遊戲機會發放分開；不修改優惠、獎項、機會發放規則，也不自動將訂單設為完成。

## 4. 安全處理

- 操作須有有效 session、CSRF 及相應權限；店家付款回報綁定金額、收款對象、修訂與目前 QR 指紋。
- 資料列鎖與付款版本避免重複回報／核款造成多次更新。
- 門市／團主 QR 以圖片資料保存於資料庫，不建立可執行 PHP 或公開猜測的團主圖片路徑。只接受有效 JPG／PNG／WEBP，最大 2MB、4096×4096；拒絕 SVG、偽造檔案及偽造來源路徑。
- QR 路由檢查授權，回傳正確 MIME、`nosniff` 並禁止私密內容快取；帳戶資料輸出前完整跳脫。
- 取消訂單不再允許顧客繼續回報付款。已付款需退款者仍由店家人工對帳，不自動退款。

## 5. 本次實際驗證

以下全部使用獨立來源副本，以及只監聽 `127.0.0.1` 的 MariaDB／HTTP 與專用測試資料庫。應用程式外寄郵件、Google／iPOS 與外部網路能力已阻擋。沒有實際轉帳、外寄郵件，沒有在 shop 或 order 建立測試訂單。

| 測試類別 | 結果 |
| --- | --- |
| 團購付款純函式 | 2,832 項通過 |
| 獎項計算純函式 | 69 項通過 |
| 既有團購付款直接整合 | 73 項通過 |
| 既有團購 HTTP 路由 | 77 項通過 |
| 團購最後防護 | 29 項通過 |
| 舊有併發操作與送單 | 52 項通過 |
| 門市、代收、送單與核款新流程 | 69 項斷言及 59 項 HTTP／helper 檢查通過 |
| 收款鎖、權限與零元流程 | 41 項通過；三輪同時重複回報與三輪同時核款 |
| JavaScript、radio／固定欄位及快取 | 16 項通過 |
| 開發者文件純函式 | 520 項通過 |
| 實際管理文件頁與下載 | 9 項斷言及 13 項 HTTP／helper 檢查通過 |

實際 multipart 上傳、桌面／手機核款路由、QR 正反向授權、回報前後更換收款對象、CSRF、金額偽造、錯誤付款方式、SVG 及兩種信件格式均已驗證。測試完成後兩個服務均已停止。首輪測試的郵件欄位鍵名誤用 `text`；既有 API 實際為 `body`，已修正測試並完整重跑通過，沒有為遷就錯誤測試而改動郵件 API。

## 6. 部署狀態與待辦

- 程式與本機測試完成；本版**尚未部署 shop 或 order**。19.10.0 的既有線上驗收不當成本版驗收。
- 測試目標為 `shop.huiyitea.vn`，不是 order。更新前核對 `config.local.php` 使用 shop 獨立資料庫，且 `APP_BASE_URL=https://shop.huiyitea.vn`。
- 先備份 shop 程式及資料庫；按原相對路徑合併更新包至 shop 網站目錄。保留 `config.php`、所有 `.local.php`、`uploads/`、根 `.htaccess` 與品牌圖示，不刪網站、不重跑安裝。
- shop 資料庫帳號須可建立三個新表並保存 schema 標記。開啟管理頁觸發升級，確認 VERSION、開發者頁及門市／代收流程，僅在 shop 的獨立環境驗收。
- 更新後將 ZIP 移至網站根目錄外，確認舊 ZIP 網址為 403／404。公開檔案 0644、目錄 0755，私有設定保留原權限。
- 真實銀行帳戶／QR、銀行 App 掃碼、真實入帳核對、shop 手機瀏覽器驗收及 order 正式更新尚未完成。沒有銀行自動驗款、自動支付或自動退款串接。
- 如須回復舊版，先停止接收新單並備份三個新表，不能刪除付款回報／收款資料。已開啟代收的團與已成立代收訂單不適用舊版直接付店家的責任規則；不能只覆蓋舊程式後繼續收款。
PAYMENT_RELEASE_19110
];
