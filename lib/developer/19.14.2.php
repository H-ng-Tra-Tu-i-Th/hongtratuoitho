<?php
if (!defined('HUIYI_DEVELOPER_DOCS')) { http_response_code(404); exit; }
return ['version'=>'19.14.2','markdown'=><<<'HUIYI_RELEASE_19142'
<a id="tieng-viet"></a>

# 19.14.2 — Sửa nút tùy chọn đồ uống trên điện thoại bị chồng lên topping

Ngày: 2026-10-08, UTC+7. Tiếp tục đầy đủ 19.14.1; giữ menu một ngôn ngữ, bánh răng, ẩn iPOS phía khách và ba vai trò.

## Chức năng và quy trình

- Nguyên nhân: hàng số lượng/nút thêm dùng `position:sticky` với `bottom` và nền blur; lúc cuộn đến topping cuối, hàng này có thể phủ lên ô topping, đặc biệt danh sách lẻ.
- Với màn hình ≤640px, hàng trong form `#customize` của detail-card dùng **normal flow** sau toàn bộ tùy chọn, khoảng cách trên 24px; bỏ inset/z-index/blur/nền phủ. Nút không nổi trên topping nữa. Vẫn giữ padding `env(safe-area-inset-bottom)` cho điện thoại có home indicator.
- Áp dụng form đồ uống và form topping riêng dùng cùng cấu trúc, kể cả thêm/sửa trong nhóm; không đổi số lượng, size L/XL, đá/đường, giá/topping, CSRF hoặc PHP đặt hàng. Desktop giữ bố cục cũ. Không sửa bảng DB.
- Đổi tham số CSS frontend sang 19.14.2 để không tiếp tục dùng CSS 19.9.1 đã cache. Không cần xóa dữ liệu website hoặc giỏ hàng.

## Kiểm chứng và trạng thái

Website và DB riêng trên loopback, API/email bên ngoài bị chặn. Fixture đồ uống có 2 topping gợi ý + 13 topping khác để kiểm tra ô cuối đứng một mình. Kiểm tra Chrome với CSS trước/sau ở cùng trang: đo rect giao nhau, normal flow và khoảng cách; 320×568, 360×740, 390×844, 430×932, 640×640, 844×390 và 1440×900, ba ngôn ngữ, nhiều vị trí cuộn, focus số lượng, giỏ rỗng/không rỗng, form nhóm, topping riêng và không JS. Kiểm tra submit thật trong DB giả: XL + topping cuối + 2 ly có đơn giá 62.000 VND, không gọi ngân hàng hoặc tạo đơn thật.

Kết quả thực tế/ảnh và SHA256 ở báo cáo giao kèm. Bộ kiểm tra trước (vai trò, session/owner đồng thời, thanh toán, nhóm, member ẩn iPOS, mỗi 10 ly, tài liệu) chạy lại. Không tuyên bố Chrome emulation là nghiệm thu Safari iOS trên điện thoại thật; cần xác nhận thực tế trên shop.

Chưa triển khai shop/order hoặc commit/push trong lần sửa này. Thêm snapshot/manifest 19.14.2; bảy snapshot đã phát hành giữ nguyên. Tài liệu toàn bộ Việt trước/Trung phồn thể sau, UI chỉ ngôn ngữ đã chọn.

## Cập nhật

Gói đầy đủ 19.14.2 gồm các bản trước, không cần apply các ZIP cũ riêng. Sao lưu, cập nhật **shop.huiyitea.vn** trước, xác nhận DB shop riêng và `APP_BASE_URL=https://shop.huiyitea.vn`. Hợp nhất đường dẫn, giữ config/htaccess/uploads/icons; không cài lại hay xóa DB. Nếu shop chưa có 19.14.0, vẫn phải kiểm tra migration tài khoản/owner của bản đó. Bản vá này không thêm migration. Đóng/mở lại tab hoặc reload sau upload để lấy CSS mới; giữ giỏ hàng. Shop nghiệm thu mới bố trí order. ZIP phải chuyển ra ngoài webroot.

---

<a id="traditional-chinese"></a>

# 19.14.2 — 修正手機自訂飲料底部配料與按鈕重疊

日期：2026-10-08，UTC+7。完整延續 19.14.1，保留單語選單、完整齒輪、前台隱藏 iPOS 及三角色。

## 功能與受影響流程

- 原因：數量／加入購物車列以 `position:sticky` 與 `bottom` 定位，加上背景 blur，捲至最後配料時可能蓋住選項，尤其是奇數配料的最後單列。
- ≤640px 時，detail-card 的 `#customize` 底部列改為**正常排版**，排在所有選項後，上方間隔 24px，移除 inset／z-index／blur／背景遮罩，不再浮在配料上。保留 `env(safe-area-inset-bottom)` 的手機安全距離。
- 同一結構的自訂飲料、單獨配料及團購新增／編輯表單適用；不改數量、L／XL、冰糖、配料價格、CSRF 或送單 PHP。桌面維持原版面，沒有資料庫變更。
- 前台 CSS 快取參數改為 19.14.2，避免繼續使用快取的 19.9.1 樣式，不必清空網站資料或購物車。

## 驗證與狀態

使用 loopback 專用網站／資料庫，應用程式封鎖外部 API／外寄；虛構飲料含兩項推薦及十三項其他配料，重現最後一個獨立卡片。在相同 Chrome 頁面比較修正前／後 CSS：測量區塊交集、正常流與間距；320×568、360×740、390×844、430×932、640×640、844×390、1440×900，三語、多個捲動位置、數量 focus、空／非空購物車、團購表單、單獨配料及無 JS。專用 DB 實際送出 XL＋最後配料＋兩杯，單價應為 62,000 VND；不付款或建立真實訂單。

實際結果／截圖與 SHA256 見交付報告。帳號、session／最後擁有者併發、付款、團購、會員隱藏 iPOS、每十杯及文件原有測試重跑。Chrome 手機尺寸模擬不等於 iOS Safari 實機驗收，須在 shop 實際確認。

本次尚未部署 shop／order、未 commit／push。新增 19.14.2 snapshot／manifest，七個已發布快照保持原樣。只有文件完整越文在上／繁中在下，介面只顯示選定語言。

## 更新

19.14.2 為完整累積更新包，不需逐包套用。先備份並更新 **shop.huiyitea.vn**，確認獨立 shop DB 及 `APP_BASE_URL=https://shop.huiyitea.vn`；相對路徑合併、保留 config／htaccess／uploads／icons，不重裝或清空 DB。若尚未套用 19.14.0，仍須核對該版帳號／擁有者 migration，本版沒有額外 migration。上傳後重新載入／開啟分頁以取得新 CSS，不必清除購物車；shop 驗收後再安排 order，ZIP 移出 webroot。
HUIYI_RELEASE_19142
];
