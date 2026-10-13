<?php
if (!defined('HUIYI_DEVELOPER_DOCS')) { http_response_code(404); exit; }
return ['version'=>'19.14.3','markdown'=><<<'HUIYI_RELEASE_19143'
<a id="tieng-viet"></a>

# 19.14.3 — Thống nhất khoảng cách và thành phần giao diện quản trị

Ngày: 2026-10-08, UTC+7. Tiếp tục đầy đủ 19.14.2, giữ sửa form điện thoại, menu một ngôn ngữ và vai trò.

## Chức năng và quy trình

Thêm `assets/css/admin-ui.css` dùng chung, scope `.admin-shell`/`.admin-mobile`, chỉ `@media screen`, không áp dụng frontend hoặc in tài liệu.

- Thang khoảng cách 4/8/12/16/24/32px. Vùng nội dung dùng gap 24px desktop, 16px mobile; các section cùng cạnh trái, panel narrow rộng tối đa 960px. Desktop rộng padding 32px, thường 24px, mobile 16px.
- Card/panel radius 16px, padding 24px desktop/20px mobile/16px rất hẹp, border/shadow nhất quán. Card tài khoản/cửa hàng/ngân hàng, stats, audit cùng nhịp.
- Input/select 44px, radius 10px, font 14px; textarea tối thiểu 104px, file input vẫn tự co; khoảng label/control 8px, form 16px. Checkbox/radio/hidden/range/color không đổi thành input full-width. Focus rõ, trạng thái disabled giữ nguyên.
- Nút chuẩn 44px, nút nhỏ trong bảng 36px desktop/40px mobile, cùng radius/font/padding. Primary màu đỏ thương hiệu, danger/status giữ ngữ nghĩa. Toolbar tự xuống dòng.
- Bảng desktop dùng header nhạt, padding cell 14×16px. Mobile giữ order card có data-label; bảng không có data-label cuộn ngang bên trong wrapper, không làm cả trang tràn.
- Sidebar giữ tile/icon và ngôn ngữ đang chọn; tăng chữ chức năng 13px, gap 8px và tile 88px tối thiểu. Top toolbar/role/language dùng kích thước đồng bộ.
- App nhận đơn riêng nạp cùng CSS bằng class `.admin-mobile`: nhịp card/form/nút rõ, không đổi route/POST, thông báo, âm thanh hoặc action nhận đơn.

Không sửa quyền, CSRF, schema DB, logic tài khoản/thanh toán/nhóm/giá/quà hay form POST. Frontend giữ `app.css?v=19.14.2`; backend đổi cache CSS sang 19.14.3. Tài liệu giữ Việt trước/Trung phồn thể sau; tám snapshot trước không đổi, thêm bản này/manifest.

## Kiểm chứng và trạng thái

QA dùng website/DB riêng trên loopback; email/API ngoài bị chặn, dữ liệu giả. Kiểm tra cùng script các route quản trị: order, sản phẩm/topping, category/profile, settings, store, member/iPOS, payment, game, accounts, developer; desktop/tablet/mobile 1440/768/390/320, ba ngôn ngữ với trường dài, bảng cuộn, form account mở, nút/input/gap. Kiểm tra app nhận đơn, role/operator/no-JS, tài liệu tải/print và chức năng form; regression core tài khoản/owner đồng thời, thanh toán/nhóm, member ẩn iPOS và mỗi 10 ly. Frontend smoke kiểm tra không nạp CSS backend và hàng tùy chọn vẫn không sticky.

Số kiểm tra thực tế/ảnh/SHA256 ghi trong báo cáo giao kèm. Không tuyên bố đã nghiệm thu thiết bị iPhone thật hoặc push nền thật. Chưa deploy shop/order hoặc commit/push.

## Cập nhật

Gói đầy đủ 19.14.3 gồm các bản trước. Sao lưu và cập nhật **shop.huiyitea.vn** trước; xác nhận DB shop riêng và `APP_BASE_URL=https://shop.huiyitea.vn`. Hợp nhất đường dẫn, giữ config/htaccess/uploads/icons, không cài lại/xóa DB. Nếu từ trước 19.14.0, vẫn nghiệm thu migration owner của bản đó; bản này không thêm migration. Reload backend để nhận CSS mới, giữ giỏ hàng. Shop nghiệm thu rồi bố trí order; ZIP chuyển ngoài webroot.

---

<a id="traditional-chinese"></a>

# 19.14.3 — 統一後台間距與元件樣式

日期：2026-10-08，UTC+7。完整延續 19.14.2，保留手機飲料防重疊、單語選單及角色權限。

## 功能與受影響流程

新增共用 `assets/css/admin-ui.css`，限 `.admin-shell`／`.admin-mobile`、`@media screen`，不套用前台或文件列印。

- 間距規範 4／8／12／16／24／32px：主區塊桌面 gap 24px、手機 16px；各區塊左緣對齊，窄表單最大 960px，主容器寬螢幕 padding 32px、一般 24px、手機 16px。
- 卡片／panel 圓角 16px，桌面留白 24px、手機 20px、極窄 16px，統一邊框及輕陰影；帳號／門市／收款卡、統計與 audit 留白同步。
- Input／select 高度 44px、圓角 10px、文字 14px；textarea 至少 104px，檔案欄位保留自適應。標籤／欄位 gap 8px、表單 16px，checkbox／radio／hidden／range／color 不套滿寬輸入樣式，保留 disabled 並強化 focus。
- 標準按鈕 44px；表格小按鈕桌面 36px、手機 40px，圓角／字級／留白一致。主要操作品牌紅色，危險與狀態色保留語意，工具列可換行。
- 桌面表格淺色表頭，儲存格 14×16px。手機保留有 data-label 的訂單卡片；沒有 data-label 的資料表在局部容器橫向捲動，不撐大整頁。
- 側欄保留圖示卡片及選定語言，功能字級 13px、gap 8px、卡片至少 88px；頂部工具列、角色／語言控制一致。
- 專用手機接單頁以 `.admin-mobile` 載入同份 CSS，統一卡片／表單／按鈕間距，不改接單路由、POST、推播、鈴聲或操作。

沒有修改權限、CSRF、DB schema、帳號／付款／團購／價格／抽獎或 form POST。前台仍為 `app.css?v=19.14.2`，後台 CSS 快取更新至 19.14.3。文件完整越文在上／繁中在下，八個舊快照保留，新增本版及 manifest。

## 驗證與狀態

使用 loopback 專用網站／DB，封鎖外部 API／外寄，資料均為虛構。同一測試腳本檢查訂單、商品／配料、分類／冰糖、設定、門市、會員／iPOS、收款、遊戲、帳號與開發文件；1440／768／390／320、三語與長欄位、局部捲動、開啟帳號表單、按鈕／欄位／間距。檢查專用接單、角色／操作員／無 JS、文件下載／列印及表單功能；重跑帳號／擁有者併發、付款／團購、會員隱藏 iPOS、每十杯。前台冒煙檢查不載入後台 CSS、自訂底部仍不黏貼。

實際數量、截圖及 SHA256 見交付報告，沒有宣稱 iPhone 實機／背景 push 已驗收。尚未部署 shop／order 或 commit／push。

## 更新

19.14.3 為完整累積包。先備份並更新 **shop.huiyitea.vn**，確認獨立 shop DB 與 `APP_BASE_URL=https://shop.huiyitea.vn`，相對路徑合併、保留 config／htaccess／uploads／icons，不重裝／清空 DB。若尚未更新 19.14.0，仍須核對該版擁有者 migration；本版沒有額外 migration。重新載入後台取得新 CSS，不清除購物車；shop 驗收後才安排 order，ZIP 移出 webroot。
HUIYI_RELEASE_19143
];
