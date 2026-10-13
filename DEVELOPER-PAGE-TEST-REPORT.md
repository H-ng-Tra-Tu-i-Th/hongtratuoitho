<a id="tieng-viet"></a>

# Báo cáo kiểm chứng trang Nhà phát triển — 19.10.0

Ngày ghi nhận: 2026-10-07, UTC+7. Mốc tài liệu: nhật ký phát triển song ngữ 19.9.1.

## Kết quả đã hoàn thành

| Nhóm kiểm tra | Kết quả | Phạm vi |
| --- | ---: | --- |
| Hàm xử lý tài liệu PHP | 512 khẳng định kiểm tra đạt | Danh sách phiên bản, toàn bộ tài liệu gốc, hai ngôn ngữ, trình bày Markdown và xử lý nội dung không an toàn |
| Trang PHP qua HTTP cục bộ | 122 khẳng định kiểm tra đạt | Trang và tài liệu thực tế chạy trong website riêng, với thành phần đăng nhập và khung quản trị mô phỏng |
| Quan sát sau triển khai trên shop | Đã hoàn thành các kiểm tra bên dưới | Phiên quản trị thực tế, đọc/chọn tài liệu, ngôn ngữ, tải nhật ký, bảo vệ truy cập và giao diện điện thoại; xuất PDF/in bằng máy in chưa nghiệm thu |

Các con số trên là số khẳng định tự động đã chạy và đạt trong hai bộ kiểm tra; không phải số lần nghiệm thu trên website thật.

## Kiểm tra xử lý tài liệu PHP

Chạy `tests/developer_page_test.php` bằng PHP CLI 8.4.26. Bộ kiểm tra nạp trực tiếp `lib/developer_docs.php`, không nạp cấu hình chương trình hoặc kết nối cơ sở dữ liệu.

- Bản ghi 19.9.1 và 19.10.0 có thể chọn từ danh sách cố định. Phiên bản được xếp theo số phiên bản ngữ nghĩa; 19.10.0 đứng trước 19.9.1.
- Bản 19.9.1 giữ đầy đủ 18 chương trong mỗi ngôn ngữ và hai sơ đồ trong mỗi ngôn ngữ. Nội dung tiếng Việt đứng trước tiếng Trung phồn thể; các thông tin và ký tự UTF-8 được giữ lại.
- Phiên bản không tồn tại, tên tệp và đường dẫn vượt ra ngoài danh sách cố định bị từ chối, không phát sinh nội dung tệp hoặc cảnh báo.
- Bảng, danh sách, bước đánh số, chữ đậm và mã nguồn vẫn đọc được. Tiêu đề trùng tên có mã mục khác nhau; các tài liệu có tiền tố riêng để tránh trùng liên kết mục.
- Sơ đồ giữ đầy đủ tên bước và nhãn nhánh Yes/No, được trình bày thành quy trình đọc được thay cho mã Mermaid thô.
- HTML, script, trình xử lý sự kiện và liên kết dùng `javascript:`, `data:`, `file:` hoặc địa chỉ bắt đầu bằng `//` không trở thành nội dung có thể thực thi. Liên kết HTTPS an toàn vẫn sử dụng được.
- Giao diện có nhãn tiếng Việt, tiếng Anh và tiếng Trung; nhãn và nội dung hợp lệ theo UTF-8.

## Kiểm tra trang qua HTTP riêng

Chạy `tests/developer_page_http_test.py`. Bộ kiểm tra tạo website tạm trong thư mục kiểm tra riêng, chỉ phục vụ tại `127.0.0.1`. Trang `admin/developer.php`, hàm tài liệu, các bản ghi và tệp giao diện là tệp triển khai thực tế; bootstrap, đăng nhập và khung quản trị là thành phần mô phỏng tối thiểu.

- Khách và hội viên thường được chuyển đến trang đăng nhập quản trị; không đọc được nội dung hoặc tải nhật ký trước khi có phiên quản trị.
- Phiên quản trị mô phỏng đọc được bản mới nhất 19.10.0 và chọn được toàn bộ mốc 19.9.1. Nội dung vẫn xếp tiếng Việt trước tiếng Trung phồn thể khi dùng giao diện vi/en/zh.
- Phản hồi tài liệu và tải xuống dùng `Cache-Control: private, no-store`; tệp tải xuống có tên phiên bản và nội dung Markdown song ngữ.
- Phiên bản giả, đường dẫn vượt thư mục, tên tệp PHP và tham số phiên bản dạng mảng nhận 404, không lộ cấu hình hoặc lỗi PHP.
- Truy cập trực tiếp manifest và các tệp PHP lưu tài liệu bị từ chối bằng 404, không xuất nội dung, kể cả khi có phiên quản trị mô phỏng.
- HTML của trang không xuất cảnh báo hoặc lỗi nghiêm trọng của PHP, không yêu cầu CDN bên ngoài.

Website kiểm tra không chứa cấu hình thật, thông tin đăng nhập cơ sở dữ liệu hoặc dữ liệu đơn hàng. PHP được tắt Email, cURL và luồng URL từ xa. Máy chủ HTTP được dừng sau kiểm tra; không có thao tác trên cơ sở dữ liệu.

## Quan sát thực tế trên shop sau triển khai

Trang mới đã triển khai tại **shop.huiyitea.vn/admin/developer.php**. VERSION và layout trước khi cập nhật được sao lưu tại `/home/huiyitea/shop-dev-backup-20261007-1910`. Những quan sát dưới đây dùng website và phiên quản trị thực tế, được ghi riêng với hai bộ kiểm tra cục bộ ở trên:

- Quản trị viên mở được trang qua menu mới, chuyển giữa 19.10.0 và 19.9.1. Nhãn giao diện thực tế vi/en/zh là **Nhà phát triển / Developer / 開發者**.
- DOM của mốc 19.9.1 có 18 chương tiếng Việt, 18 chương tiếng Trung phồn thể, 20 bảng và bốn sơ đồ; không có cảnh báo PHP. Nội dung tiếng Việt nằm phía trên tiếng Trung phồn thể.
- Mục lục đưa chương thứ hai đến vị trí cách mép trên màn hình khoảng 155 px; liên kết tiếng Trung phồn thể cũng đưa phần đích đến khoảng 155 px.
- Trình duyệt tải được nhật ký nền. SHA256 tệp tải xuống: `f7604f6a207fc8b384d276511a20f441540456d17415a1be662e56026f106561`.
- Khung điện thoại 390×844 có chiều rộng client 375 px, chiều rộng cuộn toàn trang 375 px: không có tràn ngang ở tính năng mới. Bảng có khung nhìn 311 px và nội dung 540 px, cuộn ngang trong khung bảng riêng.
- ZIP cập nhật đã được chuyển vào `/home/huiyitea/shop-dev-backup-20261007-1910`, ngoài thư mục website. URL ZIP cũ trả về 403 với yêu cầu không có session; không tải được gói tài liệu.
- Không có session: trang và tải xuống đều trả về 302 đến đăng nhập quản trị, thân phản hồi rỗng. Truy cập trực tiếp `lib/developer/19.9.1.php`, `lib/developer/manifest.php` và `lib/developer_docs.php` trả về 404, thân phản hồi rỗng.
- CSS 6,091 bytes và JavaScript 167 bytes trả về 200 và khớp từng byte với nguồn. VERSION là 19.10.0, trang chủ trả về 200.
- Nút in được JavaScript làm hiện; chưa tạo tệp PDF hoặc kiểm chứng bản in trên máy in.

Đợt triển khai này không thay đổi cấu trúc cơ sở dữ liệu, không tạo đơn thử nghiệm và không thay đổi website chính thức **order.huiyitea.vn**. Tài liệu nền 19.9.1 được giữ nguyên.

## Giới hạn và nghiệm thu tiếp theo

Hai bộ kiểm tra tự động xác nhận logic tài liệu và phản hồi của trang mới trong môi trường riêng. Phần đăng nhập mô phỏng không xác nhận việc đăng nhập thật, truy vấn của khung quản trị gốc, tự nâng cấp cơ sở dữ liệu hoặc hoạt động của các trang nghiệp vụ khác. Kiểm tra HTTP không chạy JavaScript trong trình duyệt; phần quan sát trên shop ở trên mới xác nhận giao diện thực tế, tải tệp và khung điện thoại.

Xuất PDF hoặc in bằng máy in vẫn cần nghiệm thu riêng. Chưa thực hiện kiểm tra hồi quy toàn bộ quy trình đặt hàng, thanh toán và phát thưởng của các phiên bản trước trong đợt cập nhật tài liệu này. **order.huiyitea.vn** chưa được cập nhật.

## Lặp lại kiểm tra

```text
php tests/developer_page_test.php
python tests/developer_page_http_test.py --php <đường dẫn PHP CLI cục bộ>
```

Nếu dùng `--keep-site`, chỉ website mô phỏng riêng được giữ để xem giao diện; máy chủ kiểm tra vẫn dừng. Website đó và công cụ khởi tạo phiên kiểm tra không được đưa vào Git hoặc gói triển khai.

---

<a id="traditional-chinese"></a>

# 開發者頁驗證報告 — 19.10.0

紀錄日期：2026-10-07，UTC+7。文件基準：19.9.1 完整雙語開發日誌。

## 已完成結果

| 驗證組別 | 結果 | 範圍 |
| --- | ---: | --- |
| PHP 文件純函式 | 512 項斷言通過 | 版本清單、完整基準、雙語順序、Markdown 呈現與不安全內容處理 |
| 本機 HTTP 頁面 | 122 項斷言通過 | 實際頁面與文件於私有網站執行，登入及後台外框使用隔離模擬 |
| shop 部署後實際觀察 | 已完成下列檢查 | 真實管理者、文件閱讀／切換、語言、下載、存取保護及手機版面；PDF／印表機輸出尚未驗收 |

上述數量是兩組自動測試實際執行並通過的斷言數，不是線上網站的驗收次數。

## PHP 文件純函式驗證

以 PHP CLI 8.4.26 執行 `tests/developer_page_test.php`。測試直接載入 `lib/developer_docs.php`，不載入應用設定或連接資料庫。

- 固定版本清單可以選取 19.9.1 與 19.10.0，依語意版本排序，19.10.0 排在 19.9.1 前。
- 19.9.1 完整保留各語言 18 章及各語言兩張流程圖；越南文在繁體中文上方，文件資訊與 UTF-8 字元保留。
- 不存在的版本、檔名與超出固定清單的路徑遭拒絕，不輸出檔案內容或警告。
- 表格、清單、編號步驟、粗體及程式碼可閱讀；同名標題有不同章節識別碼，各文件前綴避免章節連結重複。
- 流程保留完整節點名稱及 Yes/No 分支標籤，顯示為可閱讀的流程步驟。
- 原生 HTML、script、事件屬性，以及 `javascript:`、`data:`、`file:` 或 `//` 開頭的連結不會成為可執行內容；安全 HTTPS 連結可以使用。
- 頁面具有越文、英文及中文介面標籤，標籤與文件皆為有效 UTF-8。

## 私有 HTTP 頁面驗證

執行 `tests/developer_page_http_test.py`，在私有測試目錄建立暫存網站，只綁定 `127.0.0.1`。`admin/developer.php`、文件函式、版本紀錄及頁面資源使用實際待部署檔案；bootstrap、登入與後台外框為最小隔離模擬。

- 訪客及一般會員導向後台登入頁，沒有管理者 session 時不能讀取或下載文件。
- 模擬管理者可閱讀預設新版 19.10.0，並選取完整 19.9.1 基準。介面為 vi/en/zh 時，文件仍保持越文在上、繁中在下。
- 頁面及下載回應皆有 `Cache-Control: private, no-store`，附件檔名包含版本，下載內容為雙語 Markdown。
- 偽造版本、越界路徑、PHP 檔名及陣列型版本參數回應 404，不洩漏設定或 PHP 錯誤。
- 直接請求 manifest 與版本文件 PHP 回應 404 且不輸出內容，即使持有模擬管理者 session 也遭拒絕。
- 頁面 HTML 沒有 PHP 警告或致命錯誤，不要求外部 CDN。

測試網站沒有真實配置、資料庫登入資訊或訂單資料；PHP 關閉 Email、cURL 及遠端 URL 串流。測試後已停止 HTTP 服務，沒有操作資料庫。

## shop 部署後實際觀察

新頁面已部署於 **shop.huiyitea.vn/admin/developer.php**。更新前的 VERSION 與 layout 備份於 `/home/huiyitea/shop-dev-backup-20261007-1910`。以下使用真實網站及管理者 session，與上述兩組本機測試分開記錄：

- 管理員透過新選單開啟頁面，可切換 19.10.0 與 19.9.1；vi/en/zh 的實際介面標籤為 **Nhà phát triển / Developer / 開發者**。
- 19.9.1 DOM 包含越文 18 章、繁中 18 章、20 個表格及四張流程圖，沒有 PHP 警告；越文內容在繁中上方。
- 目錄第二章跳轉後，章節位於視窗頂端下方約 155 px；繁中連結也將目標段落帶到約 155 px。
- 瀏覽器成功下載基準日誌，下載檔 SHA256：`f7604f6a207fc8b384d276511a20f441540456d17415a1be662e56026f106561`。
- 手機視窗 390×844，client 寬度 375 px，全頁 scrollWidth 為 375 px，新功能沒有水平溢出；表格容器可見寬度 311 px、內容寬度 540 px，在表格獨立容器內橫向捲動。
- 更新 ZIP 已移至網站目錄外的 `/home/huiyitea/shop-dev-backup-20261007-1910`；原 ZIP 網址以無 session 請求回應 403，無法下載文件套件。
- 無 session 的頁面及下載皆回應 302 至後台登入頁，回應本文為空；直接請求 `lib/developer/19.9.1.php`、`lib/developer/manifest.php` 與 `lib/developer_docs.php` 回應 404，本文為空。
- CSS 6,091 bytes、JavaScript 167 bytes 皆回應 200 且逐位元組符合來源；VERSION 為 19.10.0，首頁回應 200。
- JavaScript 已使列印按鈕顯示，但沒有實際產出 PDF 或驗收印表機輸出。

此次部署沒有資料庫結構變更、沒有建立測試訂單，也沒有修改正式 **order.huiyitea.vn**；19.9.1 文件基準保留原樣。

## 驗證界線與後續驗收

兩組自動測試確認私有環境的文件邏輯與新頁面回應。模擬登入不代表實際後台登入、原始外框查詢、自動資料庫升級或其他業務頁面已驗證。HTTP 測試沒有在真實瀏覽器執行 JavaScript；真實介面、下載及手機視窗的結果來自上方另列的 shop 觀察。

PDF 產出及印表機輸出仍待另行驗收。此次文件更新沒有重新回歸前版完整下單、付款與獎勵發放流程；**order.huiyitea.vn** 尚未更新。

## 重跑方式

```text
php tests/developer_page_test.php
python tests/developer_page_http_test.py --php <本機 PHP CLI 路徑>
```

使用 `--keep-site` 時只保留私有模擬網站供介面預覽，測試服務仍會停止。該網站與測試 session 初始化工具不得放入 Git 或部署包。
