<a id="tieng-viet"></a>

# 19.10.0 — Trang tài liệu dành cho nhà phát triển trong giao diện quản trị

Ngày ghi nhận: 2026-10-07, UTC+7.

## Nội dung bổ sung

- Bổ sung điểm truy cập **Nhà phát triển** trong giao diện quản trị. Quản trị viên đăng nhập có thể đọc tài liệu trực tiếp trong hệ thống đặt hàng.
- Dùng bản nhật ký phát triển song ngữ đã tổng hợp ở phiên bản **19.9.1** làm mốc tài liệu ban đầu. Giữ đầy đủ 18 chương về chức năng, quy trình thao tác, quy tắc ưu đãi, thành viên, đơn nhóm, trò chơi, thông báo, cài đặt, phiên bản, kiểm chứng và công việc còn lại.
- Sắp xếp **toàn bộ nội dung tiếng Việt ở phía trên, toàn bộ nội dung tiếng Trung phồn thể ở phía dưới**. Giữ các bảng và nội dung quy trình để nhân viên và người phát triển cùng đối chiếu.
- Phân biệt phiên bản chương trình đang chạy với phiên bản của tài liệu gốc. Các thông tin triển khai và số lượng kiểm chứng trong bản 19.9.1 là ghi nhận tại thời điểm tổng hợp, không tự động trở thành kết quả nghiệm thu của phiên bản mới.
- Từ phiên bản này, mỗi lần cập nhật bổ sung một bản ghi theo phiên bản và ngày phát hành; giữ lại bản gốc 19.9.1 và các bản ghi đã phát hành để tra cứu lịch sử.

## Quy trình sử dụng

1. Quản trị viên đăng nhập vào giao diện quản trị hệ thống đặt hàng.
2. Mở **Nhà phát triển** từ menu quản trị.
3. Xem các bản ghi cập nhật theo phiên bản, rồi tra cứu mốc 19.9.1 để đối chiếu toàn bộ chức năng và quy trình.
4. Đọc phần tiếng Việt trước; chuyển đến phần tiếng Trung phồn thể khi cần đối chiếu thuật ngữ hoặc nội dung.
5. Khi bàn giao một phiên bản mới, bổ sung bản ghi của phiên bản đó cùng chương trình và gói cập nhật.

## Quy tắc bổ sung cho những lần cập nhật sau

Mỗi bản ghi mới gồm: phiên bản, ngày ghi nhận, thay đổi chức năng, quy trình chịu ảnh hưởng, kết quả kiểm chứng thực tế, trạng thái triển khai và các việc còn lại. Nội dung mới được thêm vào lịch sử; không sửa nội dung của bản đã phát hành để biến một trạng thái cũ thành trạng thái hiện tại.

Nếu một hạng mục đang chờ kiểm chứng hoặc chưa triển khai, ghi rõ trạng thái đó. Chỉ cập nhật kết quả đã quan sát hoặc đã kiểm chứng; phân biệt thử nghiệm cục bộ, nghiệm thu trên shop và triển khai lên order.

## Kiểm chứng và triển khai

Kiểm chứng cục bộ đã đạt **512 khẳng định kiểm tra PHP thuần** và **122 khẳng định kiểm tra HTTP riêng**: danh sách phiên bản, toàn bộ 18 chương trong mỗi ngôn ngữ của mốc 19.9.1, bốn sơ đồ song ngữ, thứ tự ngôn ngữ, bảng và quy trình, xử lý HTML/liên kết không an toàn, yêu cầu phiên quản trị, tải nhật ký, phản hồi riêng không lưu cache và từ chối phiên bản hoặc đường dẫn không hợp lệ. HTTP chạy trang thực tế với bootstrap, đăng nhập và khung quản trị mô phỏng; không nạp cấu hình thật hoặc kết nối cơ sở dữ liệu. Chi tiết xem [DEVELOPER-PAGE-TEST-REPORT.md](DEVELOPER-PAGE-TEST-REPORT.md).

Đã triển khai trang mới lên **shop.huiyitea.vn** và kiểm tra bằng phiên quản trị thực tế: mở từ menu, chuyển giữa 19.10.0/19.9.1, đổi nhãn giao diện vi/en/zh, đọc đầy đủ 18 chương mỗi ngôn ngữ, 20 bảng và bốn sơ đồ của mốc 19.9.1, dùng mục lục và liên kết tiếng Trung phồn thể, tải nhật ký bằng trình duyệt. CSS/JavaScript trả về 200 và khớp từng byte với nguồn; VERSION là 19.10.0, trang chủ trả về 200. Khi xem ở khung điện thoại 390×844, tài liệu không tràn theo chiều ngang và bảng rộng cuộn trong khung riêng. Khách chưa đăng nhập nhận 302 về trang đăng nhập khi đọc/tải; các tệp PHP tài liệu và helper truy cập trực tiếp nhận 404, không xuất nội dung. Nút in đã hiện qua JavaScript, nhưng chưa tạo PDF hoặc nghiệm thu in bằng máy in. Không thay đổi cấu trúc cơ sở dữ liệu, không tạo đơn thử nghiệm và không cập nhật **order.huiyitea.vn**. Chi tiết và SHA256 tệp tải xuống có trong báo cáo kiểm chứng.

## Vị trí cài đặt

Gói này cập nhật website hiện có. Hợp nhất theo đúng đường dẫn vào `/home/huiyitea/shop.huiyitea.vn`; điểm truy cập mới là `/admin/developer.php`. Giữ `config.php`, mọi tệp `.local.php`, `uploads/`, `.htaccess` gốc và biểu tượng thương hiệu; không cài lại cơ sở dữ liệu. Tệp công khai dùng quyền 0644, thư mục 0755; cấu hình riêng giữ quyền ban đầu.

Sau khi giải nén và xác nhận cập nhật, chuyển tệp ZIP cập nhật đã tải lên ra ngoài thư mục website, vào thư mục sao lưu như `/home/huiyitea/shop-dev-backup-20261007-1910`. ZIP chứa toàn bộ tài liệu nền; nếu để trong thư mục website, người chưa đăng nhập có thể tải ZIP và đọc tài liệu mà không qua trang quản trị. Sau khi chuyển, dùng yêu cầu không có session để xác nhận đường dẫn ZIP cũ không cho tải xuống (403 hoặc 404). Đây là bước bắt buộc khi triển khai.

Bản sao lưu VERSION và layout trước khi cập nhật shop được giữ tại `/home/huiyitea/shop-dev-backup-20261007-1910`. Mốc tài liệu 19.9.1 được giữ nguyên; bản ghi 19.10.0 này được hoàn thiện bằng các kết quả đã quan sát trong lần bàn giao.

---

<a id="traditional-chinese"></a>

# 19.10.0 — 管理後台新增開發者文件頁

紀錄日期：2026-10-07，UTC+7。

## 新增內容

- 管理後台新增 **開發者** 入口。管理員登入後，可直接在訂餐系統內閱讀文件。
- 以 **19.9.1** 已整理的雙語開發日誌作為初始文件基線。完整保留 18 章功能、操作流程、優惠規則、會員、團購、遊戲、通知、安裝、版本、驗證與待辦。
- **完整越南文在上、完整繁體中文在下**，保留表格與流程內容，方便店家人員與開發者共同查閱。
- 將目前執行的程式版本與原始文件版本分開呈現。19.9.1 文件中的部署狀態與測試數量是整理當時的紀錄，不會自動成為新版驗收結果。
- 自本版起，每次更新依版本與日期追加一筆紀錄；保留 19.9.1 原始基線及已發行紀錄，供日後查詢歷史。

## 使用流程

1. 管理員登入訂餐系統管理後台。
2. 從後台選單開啟 **開發者**。
3. 依版本查看更新紀錄，再查閱 19.9.1 基線的完整功能與流程。
4. 優先閱讀越南文，需要核對用語或內容時跳至繁體中文。
5. 交付下一版時，將該版更新紀錄隨程式與更新包一併加入。

## 後續追加規則

每筆新增紀錄包含版本、紀錄日期、功能變更、受影響流程、實際驗證結果、部署狀態與尚未完成事項。將新版內容追加至歷史，不以修改已發行紀錄的方式將過去狀態改成目前狀態。

尚待驗證或尚未部署的項目需明確標示；只登錄實際觀察或驗證的結果，並區分本機測試、shop 驗收與 order 正式部署。

## 驗證與部署

本機驗證已通過 **512 項 PHP 純函式斷言**與 **122 項私有 HTTP 斷言**，涵蓋版本清單、19.9.1 各語言完整 18 章、四張雙語流程圖、語言順序、表格與流程、不安全 HTML／連結處理、管理者 session 限制、下載日誌、私密且不快取的回應，以及非法版本和越界路徑拒絕。HTTP 使用實際新頁面與隔離模擬的 bootstrap、登入、後台外框，不載入真實設定或連接資料庫。詳見 [DEVELOPER-PAGE-TEST-REPORT.md](DEVELOPER-PAGE-TEST-REPORT.md)。

新頁面已部署至 **shop.huiyitea.vn**，並以真實管理者 session 確認選單入口、19.10.0／19.9.1 切換、vi/en/zh 介面標籤、19.9.1 各語言完整 18 章、20 個表格及四張流程圖、章節目錄、繁中跳轉與瀏覽器下載。CSS／JavaScript 回應 200 且逐位元組符合來源，VERSION 為 19.10.0，首頁回應 200。手機視窗 390×844 下，文件沒有水平溢出，寬表格在獨立容器內橫向捲動。未登入者讀取或下載皆以 302 導向登入頁；直接請求文件 PHP 與 helper 回應 404 且沒有內容。列印按鈕已經由 JavaScript 顯示，但尚未實際產出 PDF 或完成印表機驗收。沒有資料庫結構變更、沒有建立測試訂單，**order.huiyitea.vn** 尚未更新；詳細觀察與下載檔 SHA256 見驗證報告。

## 安裝位置

此版為既有網站更新包。將更新包按原路徑合併至 `/home/huiyitea/shop.huiyitea.vn`，新版入口為 `/admin/developer.php`。保留 `config.php`、所有 `.local.php`、`uploads/`、根目錄 `.htaccess` 與品牌圖示，不重新安裝資料庫。公開檔案權限 0644、目錄 0755；私有設定維持原權限。

解壓並確認更新後，必須將已上傳的更新 ZIP 移到網站目錄以外的備份資料夾，例如 `/home/huiyitea/shop-dev-backup-20261007-1910`。ZIP 內包含完整文件基準；若留在網站目錄，未登入者可能直接下載 ZIP，繞過管理後台登入閱讀文件。移檔後，以無 session 的請求確認原 ZIP 網址禁止下載（403 或 404）。此為部署必做步驟。

更新前的 shop VERSION 與 layout 已備份於 `/home/huiyitea/shop-dev-backup-20261007-1910`。19.9.1 文件基準保留原樣；本次交付的 19.10.0 紀錄依實際觀察完成定稿。
