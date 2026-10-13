<?php
if (!defined('HUIYI_DEVELOPER_DOCS')) { http_response_code(404); exit; }
return ['version'=>'19.14.5','markdown'=><<<'HUIYI_RELEASE_19145'
<a id="tieng-viet"></a>

# 19.14.5 — Bảng một/hai dòng và trở về menu từ Nhận đơn

2026-10-08, UTC+7. Tiếp tục 19.14.4, giữ lưới tỷ lệ và toàn bộ chức năng cũ.

## Thay đổi và quy trình

- Thêm `admin-tables.css` chỉ trong quản trị, sau grid. Table thường không `data-label` dùng intrinsic width/auto table layout và nowrap. Không bẻ từng ký tự tên, phân loại, điện thoại, ngày, tiền hoặc header. Text chính một dòng, ghi chú một dòng nữa; giá L/XL hiển thị chính xác hai dòng riêng. Không cắt chữ/ellipsis, không ẩn cột hoặc số tiền.
- Màn hình hẹp cuộn ngang bên trong `.table-wrap`, không tràn trang; giữ điều khiển bên phải truy cập được. JS thêm hướng dẫn vi/en/zh khi có overflow, tabindex/role/label cho bàn phím. Không JS vẫn cuộn và đọc đầy đủ. Bảng order list `data-label` giữ card mobile, bảng tài liệu không bị thay đổi. Với dữ liệu tùy ý có nhiều dòng HTML thì không ép cắt bỏ thông tin; bản này sửa việc tự wrap trong cột thường.
- `admin/products.php`: giá size L và XL hai span, cùng số tiền cũ; tên và L/XL note tối đa hai dòng. Không đổi giá lưu DB hoặc frontend price helper.
- Mọi trang Nhận đơn qua `ma_header` có link **Menu quản trị** đầu toolbar, full-row. Link cố định cùng site, cùng tab `admin/index.php#admin-sidebar`, session dùng lại, không logout. Admin shell mở sidebar trên hash đến, focus mục đầu, đóng Escape/overlay vẫn hoạt động.
- Menu theo role cũ: owner đầy đủ và account, admin không account, operator chỉ order/mobile. Không cấp thêm quyền. Toolbar còn lại hai phần đều mobile, ba phần wide; install/push/ring giữ ID/action và đăng ký hiện có. Không đổi service worker, không cache trang đăng nhập.
- Cache CSS/JS mới 19.14.5; giữ file/CSS/frontend 19.14.4 và sửa custom drink 19.14.2. Không schema migration hoặc thay dữ liệu thật.

## Kiểm chứng thực tế và trạng thái

Bộ test kiểm một/hai dòng bằng Range rect và nowrap/word-break, số tiền/điện thoại/tên dài đầy đủ, scroll ngang và thao tác bên phải; vi/en/zh, 320/390/768/1440. Link menu kiểm cùng tab, session, index và order detail, menu theo ba role, Escape/no-JS. Fixture chỉ dùng DB `huiyi_test_*` loopback riêng. Chạy lại các test grid/spacing/auth/owner concurrency/payment/group/member/per-ten. Kết quả thật, số lượng và hạn chế ghi báo cáo đi kèm; không nhận screenshot giả làm nghiệm thu iPhone.

Đã đọc VERSION công khai trước sửa 16:50 UTC+7: shop **19.14.4**, order **19.2.3**. Chưa deploy **19.14.5**, chưa commit/push. Chưa xác minh Safari/iOS PWA thực máy; cần nghiệm thu shop và thiết bị thật. Không thay tài khoản thật, đặt đơn/thanh toán thật hoặc gửi thông báo ngoài.

## Cập nhật

Gói cumulative trên 19.14.4. Backup shop trước, giữ config.local.php/htaccess/uploads/icons và DB shop riêng, `APP_BASE_URL=https://shop.huiyitea.vn`. Không chạy setup/xóa DB. Reload để lấy stylesheet và shell JS 19.14.5; không xóa cart/session. Kiểm cuộn bảng, giá L/XL, menu từ mobile/detail và giới hạn role trên shop trước order. ZIP đưa ra ngoài webroot. Giữ mười snapshot cũ nguyên vẹn.

---

<a id="traditional-chinese"></a>

# 19.14.5 — 表格一至兩行與手機接單返回主目錄

2026-10-08，UTC+7，完整延續 19.14.4 的比例網格與先前功能。

## 變更與流程

- 新增後台專用 `admin-tables.css`，在 grid 後載入。無 `data-label` 的一般表格用內容所需寬度／auto layout 與 nowrap，名稱、分類、電話、日期、金額、標題不再逐字折行。主文字一行，附註再一行，L／XL 各一行。**不截字、加省略號、藏欄或隱藏金額**。
- 窄畫面在 `.table-wrap` 內左右滑動，不讓整頁溢出；右端操作仍可使用。JS 依實際溢出加入三語滑動提示與鍵盤 tabindex／role／label，無 JS 也可捲動讀完整資料。訂單列表 `data-label` 保留手機卡片，文件表格不受影響。若既有任意資料 HTML 自帶多段附註，不強行裁掉，本版修正一般表格欄位自動換行。
- 商品列表價格以 L／XL 兩個 span 顯示，數字沿用原本價格；名稱與 L／XL 附註一至兩行，不改 DB 售價或前台價格 helper。
- 所有手機接單共用頁首在工具列最前加入整列 **後台主目錄**。固定同站、同分頁 `admin/index.php#admin-sidebar`，沿用 session，不登出；到達時開啟側欄並 focus 第一項，Escape／遮罩關閉仍有效。
- 主目錄保留既有角色：擁有者完整功能與帳號、管理員不含帳號、操作員只有訂單／手機接單，不新增權限。其餘工具手機二等分、寬畫面三等分，安裝／推播／鈴聲 ID 與動作保留，不改 service worker 或快取管理頁。
- 新 CSS／JS 快取 19.14.5，保留 19.14.4 比例與前台、19.14.2 自訂飲料修正。無 schema migration、無真實資料變更。

## 實際驗證與狀態

以 Range rect／nowrap／word-break 檢查一至兩行、完整金額／電話／長名稱、橫滑與右端操作；三語、320／390／768／1440。驗證主目錄同分頁、session、接單首頁／訂單明細、三角色選單、Escape／無 JS。fixture 僅使用獨立 loopback `huiyi_test_*` DB；重跑比例／間距／auth／擁有者併發／付款／團購／會員／每十杯。實際結果、數量與限制見隨附報告，不把截圖當 iPhone 實機驗收。

修改前 16:50 UTC+7 公開 VERSION：shop **19.14.4**、order **19.2.3**。尚未部署 **19.14.5**、未 commit／push；尚未驗證 Safari／iOS PWA 實機，仍須 shop 與實機驗收。不改真實帳號、下單／付款或外部通知。

## 更新

完整累積包延續 19.14.4，先備份 shop，保留 config.local.php／htaccess／uploads／icons，核對獨立 shop DB、`APP_BASE_URL=https://shop.huiyitea.vn`，不 setup／清空 DB。重新載入取得 19.14.5 CSS／shell JS，不清除 cart／session。在 shop 驗收表格滑動、L／XL、手機首頁／明細返回與權限後再安排 order，ZIP 移出 webroot。十份舊快照保留。
HUIYI_RELEASE_19145
];
