<a id="tieng-viet"></a>

# Kiểm chứng môi trường kỹ sư shop — 19.15.1

Ngày ghi nhận: 2026-10-13, Asia/Saigon. Mã ứng dụng nền 19.15.0 giữ nguyên; thay đổi là công cụ SQL riêng và bản ghi phát hành. Trạng thái khi tạo gói: tài khoản do chủ tài khoản đã tạo, quyền database/công cụ SQL công khai chưa được áp dụng; bằng chứng sau đó ghi ở báo cáo bàn giao.

Đã xác nhận qua cPanel: FTP `huiyi.it@shop.huiyitea.vn` có home `/home/huiyitea/shop.huiyitea.vn`; MySQL user `huiyitea_shop_huiyi_it` tồn tại. FTP hostname `host142.vietnix.vn`, cổng21: bắt tay TLSv1.3 và chứng chỉ hợp lệ, không thực hiện đăng nhập FTP. Shop trả VERSION19.15.0 và PHP8.3.28 trước cập nhật. SQL metadata chỉ đọc cho biết database `huiyitea_shop`, MariaDB11.4.9, TLS có sẵn nhưng không bắt buộc toàn server. Không có IP cố định của kỹ sư: không thêm host từ xa hoặc wildcard.

| Kiểm chứng đã chạy | Assertion đạt | Phạm vi |
| --- | ---: | --- |
| `tests/engineer_db_guards.php` | 28 | HTTPS thật của origin, từ chối giả header proxy, đúng server/user/database, kiểu dữ liệu sai, cookie mã hóa thiếu/rỗng/0 và yêu cầu ngoài phạm vi. |
| `tests/engineer_db_vendor.php` | 10 | Include vendor đã ghim thật, tương thích API PHP, CSRF hooks, encrypt/decrypt và vòng session sang yêu cầu tiếp theo; chỉ mật khẩu giả tạo. |
| `tests/engineer_db_http.mjs` | 20 | Route loopback, form/CSRF, session và key Secure/HttpOnly, không cache, phạm vi và cookie sai. TLS của origin được mô phỏng bằng router riêng, không chứng minh Apache/TLS thật. |
| `tests/developer_page_test.php` | 3.498 | Tài liệu đầy đủ hai ngôn ngữ, 32 sơ đồ, manifest/snapshot, đọc an toàn và giữ lịch sử. |
| `tests/developer_page_http_test.py` | 191 | Render/download phiên bản mới, xác thực giả cục bộ, từ chối người không có quyền và đường dẫn sai; không cấu hình/DB thật. |

Vendor là Adminer6.1.2 MySQL-only không sửa, 434.129byte; SHA256 `30d600b5d8750600b7cf7d8e4652f27d3bcd23ca6c9e1e6399a9b4a274fd7c0e`, đã khớp official release asset digest. Nguồn và Apache2.0 license nằm trong `engineer-db/vendor/NOTICE.md` và file license. Wrapper kiểm digest trước khi include, không đọc config ứng dụng hoặc điền sẵn mật khẩu. Adminer lưu mật khẩu được mã hóa tạm trong session; không tuyên bố rằng đăng nhập hoàn toàn không giữ credential. Permanent login và nhập file SQL theo đường dẫn server bị tắt.

Quyền chuẩn bị trong cPanel: SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX trên **huiyitea_shop** cho user mới. Chưa nhấn Make Changes khi ghi báo cáo. Quyền database là giới hạn SQL thực tế; không phải trình phân tích SQL sandbox. FTP/SQL account riêng cũng không tạo cách ly Unix/PHP vì hai website dùng chung tài khoản cPanel, và user ứng dụng cũ có quyền order. Chỉ bàn giao cho kỹ sư tin cậy theo phạm vi shop.

Nghiệm thu sau xác nhận: grant chỉ shop, URL HTTPS có form/CSRF và cookie đúng, vendor/source403/404, user mới tự nhập mật khẩu rồi mở trang kế tiếp, metadata quyền chỉ shop, VERSION19.15.1 và nhật ký có xác thực. Đăng nhập/chứng minh này chưa được tính đạt trong gói. Không có thao tác ngân hàng, test order, reset, schema mới hoặc thay đổi order; các bài test dữ liệu/checkout19.15.0 không chạy lại vì luồng đó không đổi.

---

<a id="traditional-chinese"></a>

# shop 工程師環境驗證 — 19.15.1

紀錄日期：2026-10-13，Asia/Saigon。19.15.0 核心程式保留，變更限獨立 SQL 工具與版本文件。更新包產製時：帳號已由擁有者建立，資料庫授權及公開 SQL 入口尚未套用；後續部署證據另列交付報告。

已透過 cPanel 核對：FTP `huiyi.it@shop.huiyitea.vn` 的 home 是 `/home/huiyitea/shop.huiyitea.vn`，資料庫帳號 `huiyitea_shop_huiyi_it` 存在。`host142.vietnix.vn` 的21埠通過 TLSv1.3 握手及憑證檢查，未實測 FTP 密碼登入。更新前 shop 的 VERSION19.15.0、實際 PHP8.3.28。唯讀 SQL 顯示 database為 `huiyitea_shop`、MariaDB11.4.9、支援 TLS 但未全伺服器強制。工程師無固定 IP，本次未新增遠端 host 或萬用 IP。

| 已執行檢查 | 通過 assertions | 範圍 |
| --- | ---: | --- |
| `tests/engineer_db_guards.php` | 28 | 來源 HTTPS、拒絕偽造代理標頭、固定伺服器／帳號／資料庫、錯誤型別、缺少／空／0 的加密 cookie 與越界請求。 |
| `tests/engineer_db_vendor.php` | 10 | 實際 include 固定 vendor、PHP API 相容、CSRF hooks、加解密及下一個請求的 session 往返；只使用合成密碼。 |
| `tests/engineer_db_http.mjs` | 20 | 本機 route、登入表單／CSRF、Secure／HttpOnly session及key、禁止快取、範圍與錯誤 cookie。來源 TLS 由私有 router 模擬，不代表真正 Apache／TLS 驗收。 |
| `tests/developer_page_test.php` | 3,498 | 完整雙語、32張圖、manifest／快照、安全閱讀與歷史保留。 |
| `tests/developer_page_http_test.py` | 191 | 最新版顯示與下載、本機合成驗證、拒絕未授權與非法路徑；無真實設定／資料庫。 |

Vendor 為未修改的 Adminer6.1.2 MySQL-only，434,129bytes；SHA256 `30d600b5d8750600b7cf7d8e4652f27d3bcd23ca6c9e1e6399a9b4a274fd7c0e` 已比對官方 release digest。來源與 Apache2.0 授權納入 `engineer-db/vendor/NOTICE.md` 及授權檔。入口先核對雜湊，不讀應用設定、不預填密碼。Adminer 會在 session 暫存加密密碼，不宣稱登入完全不保存認證資料。已停用永久登入及依伺服器路徑匯入 SQL。

已準備 cPanel 權限：新 user 對 **huiyitea_shop** 的 SELECT、INSERT、UPDATE、DELETE、CREATE、ALTER、INDEX。本報告記錄時尚未按 Make Changes。真正 SQL 範圍依資料庫授權，不是 SQL 文字 sandbox。獨立 FTP／SQL 帳號也不等於 Unix／PHP 隔離，因兩站共用 cPanel，且舊應用 user 已有 order 授權；此環境供約定只操作 shop 的可信工程師使用。

確認後驗收：只限 shop 的授權、HTTPS 表單／CSRF與cookie、vendor／source403或404、新帳號本人登入並開啟下一頁、授權 metadata 只有 shop、VERSION19.15.1及需登入的開發日誌。本包未將這些尚未完成的實測標成通過。未執行銀行交易、遠端測試訂單、重置、新 schema 或修改 order；19.15.0 訂單／付款資料測試因流程未变更未重跑。
