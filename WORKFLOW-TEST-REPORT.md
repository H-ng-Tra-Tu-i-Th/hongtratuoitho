# Kiểm chứng sơ đồ thao tác 19.12.0 / 操作流程圖驗證

## Tiếng Việt

Ngày: 2026-10-08, UTC+7. Chỉ kiểm tra cục bộ; chưa triển khai shop hoặc order.

- PHP: 3.162 khẳng định tài liệu đạt. 28 sơ đồ có đầy đủ nút, nhánh và bản văn bản; đường dẫn trang tồn tại, snapshot khớp UPDATE, đầu vào không hỗ trợ được hiển thị dưới dạng văn bản đã escape. Nhãn HTML / script không được thực thi.
- HTTP riêng tư: 146 kiểm tra đạt. Khách và thành viên không đọc / tải được tài liệu quản trị; quản trị mô phỏng đọc được phiên bản mặc định 19.12.0 và tải cả bốn bản ghi. Tải 19.12.0 khớp từng nội dung UPDATE. Đường dẫn tùy ý và truy cập trực tiếp PHP tài liệu bị từ chối; cache riêng tư được giữ.
- Trình duyệt: phản hồi HTML thực của trang PHP trong fixture được lưu làm preview cục bộ. Cả 28 sơ đồ và 250 kết nối được vẽ. Ở desktop rộng 1.265 px, không có nhãn nhánh chồng nhãn / nút; không có lỗi JavaScript được ghi nhận.
- Kiểm tra responsive với viewport 390×844 và 320×740: chiều rộng trang tương ứng 375 / 305 px (trừ thanh cuộn), scrollWidth bằng clientWidth. Sơ đồ rộng cuộn riêng, tự căn giữa lúc thay kích thước và có hướng dẫn vuốt ngang. Kiểm tra nhãn trên mobile không phát hiện chồng nhau.
- Mục lục chuyển đến đúng chương trình thu hộ; tiêu đề được quan sát khoảng 156 px dưới đầu viewport. Bản nền 19.9.1 vẫn có 36 chương và bốn sơ đồ hiển thị, với đường quay về bộ quy trình mới.
- Preview bỏ JavaScript giữ đủ 28 phần văn bản đang mở và 250 bước kết nối; không hiển thị canvas rỗng. CSS / sự kiện in chuẩn bị bản văn bản đầy đủ; chưa nghiệm thu PDF hoặc máy in thật.
- Gói được xây từ danh sách file của 19.11.0. Kiểm tra CRC, SHA256 từng file, phiên bản, quyền file Unix 0644, snapshot lịch sử không đổi và mã nghiệp vụ không đổi. Không đóng gói cấu hình, dữ liệu, tests, fixture, screenshots hay công cụ thử.

HTTP dùng bootstrap, đăng nhập và khung trang mô phỏng trong `.git/local-testing`, chỉ nghe loopback, không tải cấu hình hoặc kết nối database của ứng dụng. Preview không chứa tài khoản thật hay thông tin khách. Đây là kiểm chứng trang tài liệu, không phải kiểm thử lại thanh toán, chuyển tiền thật hoặc nghiệm thu shop. Đã dừng máy chủ thử sau kiểm tra. Bằng chứng ảnh lưu tại `outputs/workflows-19120/desktop-flow.png` và `mobile-flow.png`, không nằm trong ZIP triển khai.

Gói đầy đủ bao gồm 19.11.0; nâng từ shop 19.10.0 cần cả các bảng thanh toán mới. Tuân thủ thứ tự shop rồi order, giữ cấu hình và dữ liệu hiện có; quy trình xem `UPDATE-19.12.0.md`.

## 繁體中文

日期：2026-10-08，UTC+7。僅本機驗證，尚未部署 shop 或 order。

- PHP 文件檢查 3,162 項通過：28 張圖的節點、分支、文字內容完整；入口路徑存在、快照與 UPDATE 一致。不支援的語法以跳脫文字顯示，HTML／script 標籤不執行。
- 隔離 HTTP 146 項通過：訪客／會員不能閱讀或下載管理文件；模擬管理員可讀預設 19.12.0 及下載四版紀錄，新版下載與 UPDATE 一致。任意檔案路徑及 PHP 文件直接存取被拒絕，保留私人禁止快取標頭。
- 瀏覽器使用隔離 PHP 頁面實際回應保存的本機預覽：28 張圖及 250 條連線均已顯示。桌面有效寬度 1,265 px，分支標籤互相及與節點間未發現重疊；未記錄到 JavaScript 錯誤。
- 手機視窗 390×844、320×740：扣除捲軸後 clientWidth 分別 375／305 px，scrollWidth 均相同，整頁沒有橫向溢出。寬圖獨立橫向捲動，尺寸改變時置中，顯示左右滑動提示。手機分支文字未發現重疊。
- 目錄可跳到代收章節，觀察標題約在視窗頂端下方 156 px。19.9.1 基準仍有 36 章與四張可顯示流程圖，並可找到新版流程入口。
- 移除 JavaScript 的預覽保留 28 組展開的文字流程及 250 條步驟，不顯示空白畫布。列印樣式與事件提供完整文字流程；未實際驗收 PDF／印表機輸出。
- 更新包依 19.11.0 固定檔案清單建立，核對 CRC、逐檔 SHA256、版本、Unix 檔案 0644、歷史快照保留及業務程式未變。排除設定、資料、tests、測試登入入口、截圖與工具。

HTTP 使用 `.git/local-testing` 中的模擬 bootstrap、登入及外框，只監聽 loopback，未載入應用程式私有設定或資料庫。預覽不含真實帳號或顧客資料。這是文件功能驗證，沒有重新測試付款交易、實際轉帳或 shop 業務流程。檢查後已停止測試服務。畫面證據在 `outputs/workflows-19120/desktop-flow.png` 與 `mobile-flow.png`，不放入部署 ZIP。

完整更新包含 19.11.0，從 shop 19.10.0 更新仍需新增付款表。依 shop 先行、order 後續的順序，保留原設定與資料；部署步驟見 `UPDATE-19.12.0.md`。
