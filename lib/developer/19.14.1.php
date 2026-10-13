<?php
if (!defined('HUIYI_DEVELOPER_DOCS')) { http_response_code(404); exit; }
return ['version'=>'19.14.1','markdown'=><<<'HUIYI_RELEASE_19141'
<a id="tieng-viet"></a>

# 19.14.1 — Biểu tượng bánh răng, giao diện một ngôn ngữ và ẩn iPOS phía khách

Ngày: 2026-10-08, UTC+7. Bản sửa tiếp tục 19.14.0, giữ ba vai trò và thanh toán/nhóm/mỗi 10 ly.

## Thay đổi và quy trình chịu ảnh hưởng

1. **Thiết lập:** thay đường SVG bánh răng cũ bằng đường kín 8 răng đối xứng quanh (12,12), cùng lỗ giữa. Toàn bộ đường và nét nằm trong viewBox 24×24, không cắt một góc. Không tải icon/CDN hoặc font ngoài.
2. **Ngôn ngữ giao diện:** nhãn nhóm, ô chức năng, thương hiệu phụ, nút đóng/mở và skip-link chỉ lấy tiếng Việt, English hoặc tiếng Trung phồn thể đã chọn ở góc phải. Không có nhãn phụ bằng ngôn ngữ thứ hai. Sửa nhãn trụ sở tiếng Trung thành「總部」. Giữ tên người, tên thương hiệu và mã dữ liệu nguyên dạng; các lựa chọn ngôn ngữ vẫn dùng tên ngôn ngữ để chọn.
3. **Trang thành viên:** không truy vấn/đưa hồ sơ iPOS, điểm, cấp, sinh nhật, hóa đơn chi nhánh, trạng thái đồng bộ, thời gian đồng bộ và nút cập nhật chi nhánh vào HTML khách hàng. Không chỉ dùng CSS để giấu. Tin nhắn đồng bộ từ form cũ cũng không hiển thị.
4. Giữ hồ sơ tài khoản website, điện thoại, sửa tên/địa chỉ, đăng nhập/đăng xuất, cơ hội/quà trò chơi, lịch sử đơn website và liên kết thanh toán. Hồ sơ website dùng bề rộng bình thường, không để cột iPOS trống.
5. **Không xóa dữ liệu iPOS hoặc tắt tích hợp:** dữ liệu cache, API phía máy chủ, cấu hình quản trị và cơ chế thử đồng bộ sau đăng nhập như cũ. Bản này chỉ ẩn nội dung khỏi phía khách, không xóa hoặc chuyển quyền dữ liệu.
6. Chỉ **tài liệu phát triển** tiếp tục toàn bộ tiếng Việt ở trên, toàn bộ tiếng Trung phồn thể ở dưới. Các snapshot 19.9.1–19.14.0 không đổi; thêm bản này và manifest. Mã schema tài khoản vẫn là 19.14.0, không có migration DB mới.

## Kiểm chứng cục bộ

Kiểm thử bằng DB và website riêng trên loopback, app chặn API/email ngoài, dữ liệu thành viên và iPOS đều giả. Các nhóm cuối cùng bao gồm:

- SVG kín, 8 răng cùng kích thước, tâm đúng và bounds tính cả nét không bị cắt; Chrome chụp icon thực tế.
- Ba ngôn ngữ menu có đúng nhãn được chọn, không có small phụ song ngữ; giao diện desktop/mobile và no-JS.
- Thành viên đã xác nhận, có hồ sơ/điểm/hóa đơn iPOS thật trong fixture: HTML cả ba ngôn ngữ không có thẻ, control hoặc giá trị iPOS, vẫn có hồ sơ website/quà/đơn.
- Tin nhắn sync cũ không hiện; sửa hồ sơ, CSRF và logout giữ đúng. Trước/sau xem và sửa trang, cache iPOS không đổi; quản trị iPOS vẫn truy cập được.
- Regression ba vai trò, hủy session, chủ sở hữu cuối/đồng thời, chuyển khoản, nhóm, quà và mỗi 10 ly; tài liệu mới đọc/tải đúng nội dung và không mở cho guest/operator.

Số kiểm tra, SHA256 và ảnh cuối cùng trong báo cáo giao kèm. Chưa nghiệm thu iPOS thật hoặc push trên điện thoại thật; không gửi yêu cầu đến ngân hàng/iPOS thật để kiểm thử. Không triển khai shop/order hoặc commit/push trong lần sửa này.

## Cập nhật

Gói đầy đủ 19.14.1 bao gồm 19.14.0 và các chức năng trước, không cần áp dụng ZIP cũ theo từng bản. Trước hết sao lưu và cập nhật **shop.huiyitea.vn**, xác nhận DB shop độc lập và `APP_BASE_URL=https://shop.huiyitea.vn`; không áp dụng vào order. Hợp nhất theo đường dẫn, giữ config/htaccess/uploads/icons, không cài lại hoặc xóa DB. Nếu shop chưa nhận 19.14.0, migration tài khoản của 19.14.0 vẫn thực hiện: kiểm tra ID nhỏ nhất được giữ làm chủ sở hữu. Không thêm migration mới trong bản vá.

Xác nhận ngôn ngữ, icon, thành viên, quản trị iPOS và role trên shop trước rollout order. Di chuyển ZIP khỏi webroot, không để công khai. Giữ giới hạn role khi thiết kế rollback; không chép auth cũ làm operator có quyền đầy đủ.

---

<a id="traditional-chinese"></a>

# 19.14.1 — 完整齒輪、單語介面與前台隱藏 iPOS

日期：2026-10-08，UTC+7。延續 19.14.0，保留三角色、付款／團購與每十杯規則。

## 修改與受影響流程

1. **系統設定：**將原齒輪 SVG 改為以 (12,12) 為中心的封閉、等尺寸八齒輪廓與中央孔；路徑含筆畫均在 24×24 viewBox 內，避免缺角／裁切，不新增圖示 CDN 或字型依賴。
2. **介面語言：**群組、功能卡片、品牌副標、側欄開關及跳至內容按鈕只顯示右上角選定的繁中、越文或英文，不再有第二語言副標。繁中總部標籤改為「總部」。姓名、品牌名稱及資料代碼保留原文，語言選擇器仍用各語言名稱供選擇。
3. **會員前台：**不讀取／輸出 iPOS 會員資料、點數、等級、生日、門市消費紀錄、同步狀態／時間及更新門市資料按鈕；不是僅用 CSS 遮蔽。舊表單送入的同步 flash 也不再顯示。
4. 保留本站帳號資料、電話、姓名／地址編輯、登入／登出、抽獎及優惠券、本站訂單與付款入口；會員資料使用正常寬度，不留隱藏 iPOS 的空白欄位。
5. **不刪除 iPOS 資料、不停用串接：**後台設定、快取、伺服器 API 及登入後既有重試同步機制不變。本版只改前台呈現，不清空資料或變更資料權限。
6. 只有**開發文件**保留完整越文在上、完整繁中在下。19.9.1–19.14.0 已發布快照完全保留，新增本版及 manifest。帳號 schema marker 仍為 19.14.0，本版沒有新增 DB migration。

## 本機驗證

使用 loopback 專用資料庫與網站副本，應用程式封鎖外部 API／外寄，會員及 iPOS 資料均為虛構 fixture。最終測試包含：

- SVG 封閉、八齒等尺寸、中心與筆畫範圍完整；Chrome 擷取實際圖示。
- 三語選單只顯示對應語言，沒有雙語 small 副標；桌面、手機及無 JS 導覽。
- 已驗證電話且確有 iPOS 會員／點數／消費快取的測試會員，三語 HTML 均不含 iPOS 區塊、按鈕或資料，本站會員／優惠／訂單保留。
- 舊同步提示不顯示，編輯、CSRF 與登出維持；查看及編輯前後 iPOS 快取一致，後台 iPOS 設定可用。
- 三角色、登入撤銷、最後擁有者／併發、轉帳、團購、獎項、每十杯迴歸；本版文件閱讀／下載內容一致，guest／操作員仍不能下載。

實際數量、SHA256 與最終預覽見交付報告。未驗收真實 iPOS 或手機 push，沒有對真實 iPOS／銀行送出測試請求；本次沒有部署 shop／order 或建立 commit／push。

## 更新

19.14.1 是完整累積更新包，包含 19.14.0 與先前功能，不需逐包套用。先備份並更新 **shop.huiyitea.vn**，確認獨立 shop DB 及 `APP_BASE_URL=https://shop.huiyitea.vn`，不得套用 order。按相對路徑合併，保留 config／htaccess／uploads／icons，不重新安裝或清空 DB。若 shop 尚未升級 19.14.0，仍會執行該版帳號 migration，須核對最小 ID 原始擁有者；本版沒有額外 migration。

在 shop 核對三語、圖示、會員前台、後台 iPOS 與角色後，再安排 order。ZIP 移出 webroot，不公開下載。回復方案須保留角色限制，不能覆蓋舊驗證程式讓操作員取得完整權限。
HUIYI_RELEASE_19141
];
