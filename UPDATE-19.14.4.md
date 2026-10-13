<a id="tieng-viet"></a>

# 19.14.4 — Lưới tỷ lệ quản trị 1/2, 1/3 và 2/3

2026-10-08, UTC+7. Tiếp tục 19.14.3, không bỏ sửa điện thoại/ẩn iPOS/vai trò.

## Chức năng, quy trình bị ảnh hưởng

Thêm `admin-grid.css` scope quản trị, media screen. Container 12 cột làm chuẩn, các nhóm dùng hai phần bằng nhau, ba phần bằng nhau hoặc 2:1/1:2; không lấy chiều dài chữ để định chiều rộng. Các grid minmax(0,1fr), gap 16/24px, card cùng hàng stretch bằng chiều cao. Dùng container query theo vùng nội dung thật: ≥640px hai cột form/cards và ba cột ngôn ngữ; ≥960px ba cột store/profile/bank, bốn thống kê và vùng 2/3+1/3. Hẹp hơn chuyển một cột; thống kê/shortcut ngắn giữ hai nửa. Browser cũ có fallback một cột hoặc media rộng, không bắt buộc ép ba cột trên điện thoại.

- Order/detail/table/accounts/products/topping/category/profile/store/payment/member/game/developer dùng các nhóm tỷ lệ; panel narrow không còn cạnh phải tùy ý 720/960px.
- Store: danh sách full width, dưới form 2/3 và Maps 1/3 khi đủ rộng, một cột khi hẹp.
- Edit product/topping: scalar hai nửa, tên/mô tả ba thứ tiếng ba phần, giá/topping gợi ý/action full-row; sắp lại category/image mà không đổi tên field/POST. Checkbox/hidden/input reactive giữ nguyên.
- Settings: tên ba phần bằng nhau, theme/payment hai nửa, banner/coupon hai nửa, push/game hai nửa, email/ring/save full-row; sắp lại game vào cặp push bằng thứ tự DOM thật, không CSS reorder. Giữ field/action/CSRF.
- Account: card hai nửa, công cụ search/create 2/3+1/3; mở create vẫn full-row. Profile/store/bank ba phần khi rộng.
- Developer: intro 2/3+1/3, tải/in hai nút bằng nhau; summary/shortcut hai nửa. History/document 1/3+2/3 khi rộng; mobile xếp một cột. History và TOC details đóng mặc định trong layout xếp dọc bằng JS, có thể mở, giữ lựa chọn người dùng khi resize. Không JS vẫn mở và đọc được. Các phiên bản trong history là grid đều; không xóa snapshot.
- Print/PDF: ẩn sidebar/topbar, bỏ lề dành cho sidebar, chỉ nội dung tài liệu. Flow SVG và bản văn bản/in giữ nguyên.
- App nhận đơn: desktop card hai phần, mobile một phần; status hai hoặc bốn phần đều.

Không thay role, auth, schema, giá/thanh toán/nhóm/sync/quà hoặc dữ liệu thật. Frontend không tải CSS mới; sửa 19.14.2 vẫn giữ. Admin grid/JS developer cache 19.14.4. Tài liệu Việt đầy đủ trước, Trung phồn thể đầy đủ sau; 9 snapshot cũ nguyên vẹn.

## Kiểm chứng và trạng thái

Kiểm tra cùng script các route quản trị, vi/en/zh, màn hình rộng/tablet/390/320, ratio width/top/height và grid track count; xem desktop/mobile document, store 2:1, language 1/3, settings 1/2, tài khoản/filter/details, hidden input, native form, no-JS, print và flow cũ. Regression quyền/owner đồng thời, thanh toán/nhóm, member ẩn iPOS, mỗi 10 ly trong DB/site riêng trên loopback, app chặn API/email ngoài. Số đạt thực tế và giới hạn ghi trong báo cáo đi kèm; không gán kết quả cũ thành kiểm thử mới.

Trước sửa, đã đọc VERSION công khai lúc 16:10 UTC+7: shop 19.14.3, order 19.2.3. Ảnh người dùng hiển thị 19.14.1 là ảnh phiên bản cũ, không coi đó là trạng thái hiện tại. Không có phiên đăng nhập sản xuất được kiểm chứng; chưa deploy 19.14.4, chưa commit/push; cần shop/iPhone thật nghiệm thu.

## Cập nhật

Gói đầy đủ chứa các bản trước. Sao lưu rồi cập nhật shop trước, xác nhận DB shop riêng, `APP_BASE_URL=https://shop.huiyitea.vn`, giữ config/htaccess/uploads/icons, không setup hoặc xóa DB. Nếu từ trước 19.14.0 vẫn xác minh owner migration đó; bản này không thêm migration. Reload sau cập nhật để lấy CSS/JS mới, không xóa giỏ. Shop nghiệm thu rồi mới order; ZIP ngoài webroot.

---

<a id="traditional-chinese"></a>

# 19.14.4 — 後台 1/2、1/3、2/3 比例網格

2026-10-08，UTC+7，完整延續 19.14.3，保留手機修正、前台隱藏 iPOS 與角色功能。

## 功能與受影響流程

新增僅供後台的 `admin-grid.css`、media screen。以 12 欄為基準，區塊採等寬兩份、三份或 2:1／1:2，不再由文字長度決定寬度；grid 使用 minmax(0,1fr)、gap 16／24px，同列卡片等高。依實際內容容器寬度而非只看螢幕：≥640px 表單／卡片雙欄、三語欄位三等分；≥960px 門市／規格／收款三欄、統計四等分及 2/3＋1/3。更窄轉單欄，統計／短捷徑保留等寬雙欄。舊瀏覽器有單欄或寬螢幕 fallback，不強迫手機塞三欄。

- 訂單／明細／表格／帳號／商品／配料／分類／規格／門市／收款／會員／遊戲／文件沿用比例群組，窄 panel 不再任意停在 720／960px 造成右緣不齊。
- 門市：清單整列，下方編輯 2/3、Maps 1/3，窄螢幕單欄。
- 商品／配料：一般欄位雙欄、三語名稱／描述三等分，價格／推薦／操作跨整列；重新排列分類／圖片，不改欄位名稱或 POST，checkbox／hidden／動態價格不變。
- 設定：店名三等分，主題／收款兩半、banner／優惠兩半、push／遊戲兩半，信件／鈴聲／儲存整列。遊戲移到 push 相鄰位置，使用真實 DOM 順序、不以 CSS reorder 改變鍵盤視覺順序；保留欄位／action／CSRF。
- 帳號：卡片二等分，搜尋／新增 2/3＋1/3，展開新增跨整列；規格／門市／收款寬螢幕三等分。
- 文件：說明 2/3＋工具 1/3，下載／列印等寬；摘要／捷徑兩半，歷史／文件 1/3＋2/3，手機轉單欄。縱排時 JS 預設收起歷史與目錄，可手動展開、resize 保留選擇；無 JS 仍開啟可讀。歷史版本等寬格，不刪除快照。
- 列印／PDF 隱藏側欄／頂欄、去掉側欄預留邊距，只輸出文件；原流程 SVG 與文字備援保留。
- 專用接單頁：寬螢幕訂單二等分、手機一列，狀態二／四等分。

不更動角色、auth、schema、價格／付款／團購／同步／抽獎或真實資料。前台不載入新 CSS，19.14.2 修正保留。後台 grid／文件 JS 快取 19.14.4。文件完整越文在上、完整繁中在下，九個舊快照保留。

## 驗證與狀態

同一腳本檢查後台路由、三語、桌面／平板／390／320，量測 width／top／height／grid track；驗證桌面／手機文件、門市 2:1、三語 1/3、設定 1/2、帳號／搜尋／details、hidden input、原生表單、無 JS、列印與舊流程。使用 loopback 獨立 DB／網站、封鎖外部 API／外寄，重跑角色／擁有者併發、付款／團購、會員隱藏 iPOS、每十杯。實際數量與限制見隨附報告，不把歷史結果列為本輪新驗證。

修改前 16:10 UTC+7 唯讀查到公開 VERSION：shop 19.14.3、order 19.2.3。使用者截圖顯示 19.14.1 為舊版本影像，不當作目前狀態。未驗證真實管理者登入，尚未部署 19.14.4、未 commit／push；仍須 shop／iPhone 實機驗收。

## 更新

完整累積包包含先前功能。先備份與更新 shop，核對獨立 DB、`APP_BASE_URL=https://shop.huiyitea.vn`，保留 config／htaccess／uploads／icons，不執行 setup 或清空 DB。若尚未更新 19.14.0 仍核對該版擁有者 migration，本版無新增 migration。重新載入取得新 CSS／JS，不清除購物車；shop 驗收後再安排 order，ZIP 移出 webroot。
