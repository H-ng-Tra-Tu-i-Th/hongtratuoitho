# Báo cáo kiểm chứng 19.11.0 / 19.11.0 驗證報告

## Tiếng Việt

Ngày: 2026-10-07, UTC+7. Phạm vi: sửa luồng thanh toán khách hàng, tài khoản chi nhánh, trưởng nhóm thu hộ và xác nhận tiền cửa hàng. **Mã cục bộ đã kiểm chứng; chưa triển khai shop/order.**

### Kết quả

| Bộ kiểm thử | Kết quả thực tế |
| --- | --- |
| `group_payments_unit.php` | 2.832 kiểm tra đạt |
| `game_rewards_unit.php` | 69 kiểm tra đạt |
| Nhóm duyệt trực tiếp | 73 kiểm tra đạt |
| HTTP nhóm | 77 kiểm tra đạt |
| Bảo vệ cuối cùng | 29 kiểm tra đạt |
| Đồng thời sửa/gửi đơn nhóm | 52 kiểm tra đạt |
| `transfer_frontend.js` | 16 kiểm tra đạt |
| `transfer_payments_integration.php` | 69 khẳng định và 59 kiểm tra HTTP/helper đạt |
| `transfer_receipts_concurrency.php` | 41 kiểm tra đạt |
| `transfer_release_http.php` | 9 khẳng định và 13 kiểm tra HTTP/helper đạt |
| `developer_page_test.php` | 520 khẳng định đạt |

### Những quy trình đã chạy thật trong môi trường riêng

1. Thêm tài khoản chi nhánh, bật/tắt, kiểm tra đầu vào và rollback cả revision khi dữ liệu sai.
2. Upload multipart thật qua route quản trị. Ảnh PNG được lưu và trả lại đúng từng byte; ảnh SVG bị từ chối mà không lưu một phần thay đổi.
3. Tạo nhóm thu hộ, thành viên chuyển khoản/tiền mặt/ứng trước/không có món, báo đã trả và trưởng nhóm duyệt.
4. Nhóm chưa thu đủ bị chặn; nhóm thu đủ tạo một đơn. Khoản cửa hàng lấy **tổng tiền nhóm**, không nhầm thành phần tự đặt/ứng trước của trưởng nhóm.
5. Chưa chỉ định chi nhánh thì tài khoản đề xuất không được dùng làm nơi nhận tiền. Chỉ sau chỉ định mới hiển thị tài khoản chi nhánh; không có tài khoản vẫn cho gửi đơn và nhắc chờ thông tin.
6. Thay nơi nhận/QR trước khi báo làm quote cũ không hợp lệ. Người đã trả được nhắc không trả lần nữa, liên hệ cửa hàng đối soát tài khoản ban đầu.
7. Sau khi báo, giữ nguyên tài khoản và QR đã dùng kể cả đổi chi nhánh giao hàng. Báo lặp không tăng revision lần nữa.
8. Desktop và mobile-admin xác nhận tiền riêng, lưu nhân viên/thời gian, không thay trạng thái xử lý đơn.
9. Ba lượt chạy hai worker báo trùng đồng thời, và ba lượt hai nhân viên xác nhận đồng thời: chỉ một lần chuyển trạng thái, giữ người xác nhận đầu tiên.
10. CSRF sai, giả số tiền, sai phương thức, thành viên/khách ngoài nhóm xem khoản cửa hàng hoặc QR không có quyền đều bị chặn.
11. Tổng bằng 0 hiển thị không cần trả, không giả lập nhân viên đã nhận tiền; thành viên phải đúng người sở hữu đơn và khách vãng lai phải đúng session.
12. Nội dung tài khoản được escape; QR trụ sở không chấp nhận đường dẫn vượt thư mục.
13. Radio thường và trường ẩn nhóm đều hiển thị đúng vùng chuyển khoản; cash/split ẩn vùng này và cache đổi sang bản mới.
14. Email HTML/văn bản thuần giữ đúng báo/duyệt/thu hộ và lịch sử giao thức 1. Nhóm trả trực tiếp cũ không phát sinh yêu cầu trả toàn bộ lần thứ hai.
15. Trang phát triển thực tế đọc bản mới bằng quản trị, tải markdown đúng snapshot; đọc/tải chưa đăng nhập chuyển tới đăng nhập; PHP tài liệu/helper truy cập trực tiếp nhận 404 và không có nội dung.

### Cách ly và giới hạn

Bản sao nguồn, dữ liệu và fixture chỉ dùng cho kiểm thử, với MariaDB/HTTP giới hạn trên loopback. App không được gửi email, gọi Google/iPOS hay kết nối mạng ngoài; driver chỉ gọi HTTP local. Đã dừng các dịch vụ sau mỗi lần chạy. Không dùng dữ liệu khách thật, không chuyển tiền thật, không tạo đơn trên shop/order.

Ảnh fixture chỉ chứng minh nhận upload, định dạng và quyền truy cập; **không chứng minh QR ngân hàng thật có đúng tài khoản hoặc quét được bằng ứng dụng ngân hàng**. Nội dung đó phải được chủ tài khoản cấu hình và nghiệm thu. Không có webhook ngân hàng, tự xác minh nhận tiền, tự thanh toán hay tự hoàn tiền.

Lần chạy đầu có lỗi kiểm thử email dùng khóa `text`; API hiện hữu dùng `body`. Đã sửa kiểm thử, giữ API và chạy lại toàn bộ. Kết quả ở bảng là lần chạy cuối của nguồn 19.11.0, không phải kết quả suy đoán.

### Gói cập nhật và triển khai

Gói dùng white-list của 19.10.0, thêm đúng các file thanh toán mới, snapshot 19.11.0 và báo cáo. Builder kiểm tra PHP, CRC, SHA256 từng file, nội dung khớp nguồn đã thử, file thường Unix 0644 và không có entry thư mục. Giữ nguyên ZIP 19.10.0 cùng hai snapshot 19.9.1/19.10.0; không đưa cấu hình, upload, icons, công cụ, fixture, tests hoặc dữ liệu riêng vào ZIP.

Cập nhật chỉ dành cho website đã cài. Sao lưu shop, kiểm tra database độc lập/base URL, hợp nhất đường dẫn và giữ cấu hình/upload/.htaccess; không cài lại hoặc xóa database. Thử trên `shop.huiyitea.vn` trước, chỉ sau nghiệm thu mới sắp xếp cập nhật `order.huiyitea.vn`. Chưa đăng nhập quản trị hosting, chưa tải ZIP lên máy chủ, chưa triển khai hai site và chưa commit/push Git trong lần sửa này. Chi tiết quy trình và rollback xem [UPDATE-19.11.0.md](UPDATE-19.11.0.md).

---

## 繁體中文

日期：2026-10-07，UTC+7。範圍：客戶付款修正、門市帳戶、團主代收及店家核款。**本機程式已驗證；尚未部署 shop／order。**

### 實際結果

| 測試組別 | 已核對結果 |
| --- | --- |
| `group_payments_unit.php` | 2,832 項通過 |
| `game_rewards_unit.php` | 69 項通過 |
| 既有付款審核直接整合 | 73 項通過 |
| 既有團購 HTTP | 77 項通過 |
| 最後防護 | 29 項通過 |
| 併發團購修改／送單 | 52 項通過 |
| `transfer_frontend.js` | 16 項通過 |
| `transfer_payments_integration.php` | 69 項斷言及 59 項 HTTP／helper 通過 |
| `transfer_receipts_concurrency.php` | 41 項通過 |
| `transfer_release_http.php` | 9 項斷言及 13 項 HTTP／helper 通過 |
| `developer_page_test.php` | 520 項斷言通過 |

### 已在隔離環境實際執行的流程

1. 新增門市帳戶、啟用／停用、輸入驗證；資料錯誤時連門市修訂號一併回滾。
2. 真正 multipart 管理上傳。PNG 保存及讀取逐位元組一致；SVG 被拒絕且不保存部分變更。
3. 開啟代收團，涵蓋轉帳、現金、團主墊付、無品項團員，並回報、由團主確認。
4. 未收齊款項禁止送單；確認齊全後只建一筆訂單。店家應收採**全團金額**，不誤用團主自購／墊付出資額。
5. 尚未確認派單時，不把建議門市當收款對象。確認指派後才顯示門市帳戶；沒有帳戶仍可送單並提示等待資訊。
6. 回報前更改帳戶／QR 使舊付款引用失效；已轉帳者收到勿重複付款、聯繫店家核對原帳戶的提示。
7. 回報後固定帳戶及 QR，改配送門市不重收款；重複回報不再次增加修訂。
8. 桌面及手機後台獨立核款，保存確認店員及時間，不改變出餐／配送狀態。
9. 三輪兩個 worker 同時重複回報、三輪兩個店員同時核款，只轉換一次狀態並保留第一位確認人的紀錄。
10. 錯誤 CSRF、金額偽造、錯誤付款方式、其他團員／外人查看店家款項或未授權 QR 均拒絕。
11. 零元顯示無需付款，不偽裝店員已核款；會員必須是訂單本人，訪客必須符合原 session。
12. 帳戶文字完整跳脫；總部 QR 不接受越界檔案路徑。
13. 一般 radio 與團購固定隱藏欄位均正確顯示轉帳區；現金／直接分帳隱藏，快取更新至新版。
14. HTML／純文字通知信正確區分回報、團主確認、代收及協定 1 歷史語意；舊直接付店家的團不再收一次全團款。
15. 真實 PHP 管理文件頁可讀新版及下載與快照相同的 markdown；未登入讀取／下載導向登入頁，文件與 helper PHP 直接存取為 404 且沒有內容。

### 隔離與限制

使用獨立來源副本、資料與 fixture；MariaDB／HTTP 只監聽 loopback。應用程式不能外寄郵件、呼叫 Google／iPOS 或連線外部；driver 只呼叫本機 HTTP。每輪結束均停止服務。沒有使用真實客戶資料、沒有轉帳，沒有在 shop／order 建測試訂單。

圖片 fixture 只驗證上傳、格式及授權，**不證明真實銀行 QR 的帳戶內容正確或銀行 App 可掃碼**；須由帳戶持有人配置及驗收。沒有銀行 webhook、自動驗款、自動支付或自動退款。

首輪信件測試誤用 `text` 鍵；既有 API 實際為 `body`。已修正測試、保留 API 並完整重跑。表列為最終 19.11.0 來源的實際結果，不是推測。

### 更新包與部署

更新包沿用 19.10.0 白名單，僅加入新付款檔案、19.11.0 快照及報告。產製器核對 PHP、CRC、逐檔 SHA256、與已測來源一致、Unix 一般檔案 0644，並且沒有顯式資料夾項目。19.10.0 ZIP 及 19.9.1／19.10.0 文件快照保持不變；ZIP 不包含設定、uploads、icons、工具、fixture、tests 或私有資料。

這是既有網站更新包。先備份 shop，核對獨立資料庫及 base URL，再按相對路徑合併，保留設定／上傳／`.htaccess`，不重新安裝或清空資料庫。先在 `shop.huiyitea.vn` 驗收，再安排 `order.huiyitea.vn`。本次沒有登入主機管理、上傳 ZIP 或部署兩站，也沒有建立 Git commit／push。操作與回復注意事項見 [UPDATE-19.11.0.md](UPDATE-19.11.0.md)。
