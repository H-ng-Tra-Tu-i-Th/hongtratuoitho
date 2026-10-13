# Duy trì tài liệu phát triển / 維護開發者文件

## Tiếng Việt

Mỗi lần cập nhật hệ thống, thêm bản ghi mới theo phiên bản. Giữ nguyên `19.9.1.php` và các bản đã phát hành; bản 19.9.1 là toàn bộ nhật ký chức năng và quy trình ngày 2026-10-07.

1. Soạn `UPDATE-x.y.z.md`, toàn bộ tiếng Việt trước, toàn bộ tiếng Trung phồn thể sau.
2. Tạo `lib/developer/x.y.z.php` theo cấu trúc của `19.10.0.php`: kiểm tra `HUIYI_DEVELOPER_DOCS`, rồi trả về `version` và chuỗi `markdown`.
3. Thêm đúng phiên bản, ngày ghi nhận và tiêu đề `title_vi`, `title_zh` vào `manifest.php`. Trang quản trị tự sắp xếp phiên bản mới nhất trước bằng thứ tự phiên bản số.
4. Ghi thay đổi chức năng, quy trình chịu ảnh hưởng, kiểm chứng thực tế, trạng thái triển khai và việc còn lại. Phân biệt kiểm thử cục bộ, nghiệm thu shop và triển khai order.
5. Cập nhật `VERSION`; đưa bản ghi, manifest và tài liệu mới vào gói cập nhật, kiểm tra trang quản trị và tải nhật ký. Thử nghiệm trên shop trước khi bố trí order.
6. Sau khi giải nén và xác nhận cập nhật, chuyển ZIP đã tải lên vào thư mục sao lưu ngoài thư mục website. Gói chứa tài liệu quản trị; không để URL tải ZIP công khai trở thành đường đọc tài liệu bỏ qua đăng nhập. Dùng yêu cầu không có session xác nhận URL ZIP cũ không cho tải xuống (403 hoặc 404), đồng thời kiểm tra trang và tải nhật ký vẫn yêu cầu đăng nhập quản trị.

Trang chỉ đọc nội dung được đăng ký trong manifest; không đưa cấu hình, mật khẩu, khóa, thông tin khách hàng hoặc log máy chủ vào bản ghi. Không thêm liên kết tới tệp riêng tư hoặc đường dẫn không tồn tại.

Từ 19.12.0, bản ghi chứa bộ quy trình được đánh dấu `workflows => true` trong manifest; lối vào Sơ đồ thao tác chọn bản mới nhất có cờ này. Chỉ thêm cờ khi bản mới có đầy đủ bộ quy trình. Dùng Mermaid `flowchart TD`, nút có nhãn trong dấu ngoặc kép, mũi tên `-->` và nhánh `-->|điều kiện|`; không dùng HTML, liên kết, directive hay vòng lặp. Bộ hiển thị cục bộ vẽ sơ đồ và giữ bản văn bản để đọc / in; nội dung không được gửi ra dịch vụ khác. Mỗi ô ghi rõ người thực hiện và kiểm tra đường dẫn trang theo mã nguồn.

## 繁體中文

每次更新系統都新增對應版本的紀錄。保留 `19.9.1.php` 與已發布版本；19.9.1 是 2026-10-07 的完整功能與流程日誌。

1. 撰寫 `UPDATE-x.y.z.md`，完整越南文在上、完整繁體中文在下。
2. 依 `19.10.0.php` 格式建立 `lib/developer/x.y.z.php`：先檢查 `HUIYI_DEVELOPER_DOCS`，再回傳 `version` 與完整 `markdown` 字串。
3. 在 `manifest.php` 追加版本、日期及 `title_vi`、`title_zh` 標題。頁面按數字版本排序，最新版在前。
4. 記錄功能變更、受影響流程、實際驗證、部署狀態與待辦，區分本機測試、shop 驗收及 order 正式部署。
5. 更新 `VERSION`；更新包納入新紀錄、manifest 與說明，檢查後台閱讀及下載。先驗收 shop，再安排 order。
6. 解壓並確認更新後，將已上傳 ZIP 移至網站目錄外的備份資料夾。更新包含管理文件，不可讓公開 ZIP 下載成為繞過登入的閱讀入口。以無 session 的請求確認原 ZIP 網址禁止下載（403 或 404），並確認文件頁及日誌下載仍須管理者登入。

頁面只讀取 manifest 中的固定文件；紀錄不包含私有設定、密碼、金鑰、顧客資料或主機紀錄。引用文件須確認存在，私有檔案不提供入口。

自 19.12.0 起，完整流程集在 manifest 標示 `workflows => true`，「操作流程圖」入口選取最新有此標記的版本。新版本有完整流程集時才標記。使用 Mermaid `flowchart TD`、雙引號節點、`-->` 箭頭及 `-->|條件|` 分支，不使用 HTML、連結、指令或循環。本機顯示程式繪圖並保留閱讀／列印的文字步驟，不傳送至外部服務；節點註明執行者，頁面路徑需依程式核對。
