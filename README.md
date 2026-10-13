# Hồng Trà Tuổi Thơ 點餐系統 v19.15.1

v19.15.1: thêm công cụ SQL HTTPS cho kỹ sư shop, cố định localhost/user/database riêng và dùng Adminer 6.1.2 chính thức. Xem [UPDATE-19.15.1.md](UPDATE-19.15.1.md), [ENGINEER-ENVIRONMENT-TEST-REPORT.md](ENGINEER-ENVIRONMENT-TEST-REPORT.md) và hướng dẫn bàn giao. Quyền database và triển khai phải được xác nhận riêng.

v19.15.1：新增 shop 工程師 HTTPS 資料庫管理入口，固定專用伺服器／帳號／資料庫，使用官方 Adminer 6.1.2。詳見 [UPDATE-19.15.1.md](UPDATE-19.15.1.md)、[ENGINEER-ENVIRONMENT-TEST-REPORT.md](ENGINEER-ENVIRONMENT-TEST-REPORT.md) 及交接文件；資料庫授權與上線另行確認。

v19.15.0: lịch sử thanh toán không bị xóa, xử lý báo sai/thực thu/hoàn tiền thủ công, thời hạn kết toán nhóm riêng, phiên bản trạng thái đơn, lọc và xuất đối soát, quyền nhiều đơn khách trong session, bản nháp và cập nhật thanh toán trên điện thoại. Gói đầy đủ gồm 19.14.5. Xem [UPDATE-19.15.0.md](UPDATE-19.15.0.md) và [PAYMENT-FLOW-TEST-REPORT.md](PAYMENT-FLOW-TEST-REPORT.md). Đích triển khai chỉ shop với database riêng; trạng thái thực tế theo báo cáo giao hàng.

v19.15.0：保留付款歷程、處理誤報／實收／人工退款、團購獨立付款期限、訂單狀態版本、核帳篩選與匯出、訪客同一 session 多筆付款、結帳草稿及手機付款更新。完整包包含 19.14.5。詳見 [UPDATE-19.15.0.md](UPDATE-19.15.0.md) 及 [PAYMENT-FLOW-TEST-REPORT.md](PAYMENT-FLOW-TEST-REPORT.md)。本次部署只限獨立資料庫的 shop，實際狀態依交付報告。

v19.14.5: bảng không bẻ chữ/số, L/XL hai dòng, cuộn ngang nội bộ và mobile trở về menu cùng tab, giữ role. Xem [UPDATE-19.14.5.md](UPDATE-19.14.5.md), chưa deploy.

v19.14.5：表格不逐字折行、L／XL 兩行，表內橫滑、手機同分頁返回主目錄，保留角色權限。詳見 [UPDATE-19.14.5.md](UPDATE-19.14.5.md)，尚未部署。

v19.14.4: grid tỷ lệ hai/ba phần và 2:1, công cụ developer bằng nhau, history mobile thu gọn, print không sidebar. Xem [UPDATE-19.14.4.md](UPDATE-19.14.4.md), chưa deploy.

v19.14.4：後台雙／三等分與 2:1 網格，文件工具等寬、手機歷史可收合，列印去側欄。詳見 [UPDATE-19.14.4.md](UPDATE-19.14.4.md)，尚未部署。

v19.14.3: thống nhất spacing/card/form/button/table phía quản trị, scope riêng không đổi frontend, role hay thanh toán. Xem [UPDATE-19.14.3.md](UPDATE-19.14.3.md), chưa deploy shop/order.

v19.14.3：後台共用間距／卡片／表單／按鈕／表格規範，不改前台、角色及付款。詳見 [UPDATE-19.14.3.md](UPDATE-19.14.3.md)，尚未部署 shop／order。

v19.14.2: hàng số lượng/nút thêm trong form tùy chọn trên điện thoại không còn sticky phủ topping. Giữ safe-area và giỏ hàng. Xem [UPDATE-19.14.2.md](UPDATE-19.14.2.md); chưa triển khai shop/order.

v19.14.2：手機自訂表單底部改為正常流，不再蓋住最後配料；保留安全區及購物車。詳見 [UPDATE-19.14.2.md](UPDATE-19.14.2.md)，尚未部署 shop／order。

v19.14.1: sửa bánh răng Thiết lập, chỉ hiển thị ngôn ngữ đã chọn và ẩn hồ sơ/điểm/hóa đơn iPOS phía khách. Giữ dữ liệu và quản trị iPOS; chỉ tài liệu phát triển giữ toàn bộ Việt trước/Trung sau. Chưa triển khai shop/order. Xem [UPDATE-19.14.1.md](UPDATE-19.14.1.md).

v19.14.1：修復齒輪、介面只顯示選定語言，會員前台不輸出 iPOS 會員／點數／消費資料，保留後台及快取。只有開發文件越文在上／繁中在下。尚未部署 shop／order。詳見 [UPDATE-19.14.1.md](UPDATE-19.14.1.md)。

v19.14.0: chủ sở hữu quản lý toàn bộ tài khoản, quản trị viên có quyền khác giống nhau, nhân viên chỉ xử lý đơn. Menu nhóm ô biểu tượng Việt/Trung, bảo vệ session và chủ sở hữu cuối. Đã kiểm thử cục bộ; chưa triển khai shop/order. Xem [UPDATE-19.14.0.md](UPDATE-19.14.0.md).

v19.14.0：擁有者管理全部帳號，管理員其餘權限相同，操作員只處理訂單。後台改為雙語分組圖示卡片，保護 session 與最後一位擁有者。本機驗證通過，尚未部署 shop／order。詳見 [UPDATE-19.14.0.md](UPDATE-19.14.0.md)。

v19.13.0: mỗi 10 ly trong cùng một đơn được 1 lượt, 20 ly được 2 lượt; cấp sau hoàn tất. Đồng bộ giỏ, nhóm, quản trị, trò chơi và sơ đồ; giữ số lượt của đơn đã xác nhận. Chưa triển khai shop/order. Xem [UPDATE-19.13.0.md](UPDATE-19.13.0.md).

v19.13.0：單筆每滿 10 杯 1 次，20 杯 2 次，完成訂單後發放。已同步購物車、團購、後台、遊戲及流程圖，保留已確認舊單的次數。尚未部署 shop／order。詳見 [UPDATE-19.13.0.md](UPDATE-19.13.0.md)。

v19.12.0 thêm mục Sơ đồ thao tác: 14 quy trình khách hàng, thanh toán, nhóm và quản trị trong mỗi ngôn ngữ; giữ tiếng Việt trước tiếng Trung phồn thể, tải nhật ký và lịch sử. Gói đầy đủ bao gồm 19.11.0; chưa triển khai shop/order. Xem [UPDATE-19.12.0.md](UPDATE-19.12.0.md).

v19.12.0 在開發者頁新增操作流程圖入口，每語言 14 張客戶、訂餐付款、團購及管理流程圖。越文在上、繁中在下，保留日誌下載與歷史紀錄。完整更新包包含 19.11.0；尚未部署 shop／order。詳見 [UPDATE-19.12.0.md](UPDATE-19.12.0.md)。

v19.11.0 bổ sung tài khoản/QR chi nhánh, trưởng nhóm thu hộ và xác nhận cửa hàng nhận tiền riêng trên quản trị máy tính/điện thoại. Thành viên trả trưởng nhóm, trưởng nhóm trả tổng tiền cửa hàng sau khi gửi đơn. Giữ chuyển khoản khi chưa có tài khoản và nhóm cũ không bị yêu cầu trả tổng tiền lần nữa. Đã kiểm thử cục bộ; chưa triển khai shop/order. Xem [UPDATE-19.11.0.md](UPDATE-19.11.0.md).

v19.11.0 新增門市帳戶／QR、團主代收與桌面／手機後台獨立核款。團員付團主，送單後團主支付店家全團款；沒有帳戶仍保留轉帳，舊團不重複收全團款。本機測試通過，尚未部署 shop／order。詳見 [UPDATE-19.11.0.md](UPDATE-19.11.0.md)。

v19.10.0 bổ sung **Nhà phát triển** trong menu quản trị: đọc tài liệu nền 19.9.1 và các bản ghi cập nhật, tiếng Việt ở trên và tiếng Trung phồn thể ở dưới; có mục lục, tải nhật ký và in. Mỗi lần cập nhật tiếp theo bổ sung bản ghi riêng, giữ lịch sử cũ. Đã triển khai trên shop và kiểm tra đọc tài liệu, tải nhật ký, bảo vệ truy cập và giao diện điện thoại; xuất PDF/in bằng máy in chưa nghiệm thu. Xem [UPDATE-19.10.0.md](UPDATE-19.10.0.md) và [báo cáo kiểm chứng](DEVELOPER-PAGE-TEST-REPORT.md).

v19.10.0 在管理後台選單新增 **開發者**：查閱完整 19.9.1 基準與版本更新，越文在上、繁中在下，提供章節目錄、日誌下載與列印。後續每次更新新增獨立紀錄並保留歷史。已部署 shop 並確認文件閱讀、下載、存取保護與手機版面；PDF／印表機輸出尚未驗收。詳見 [UPDATE-19.10.0.md](UPDATE-19.10.0.md) 與 [驗證報告](DEVELOPER-PAGE-TEST-REPORT.md)。

[Nhật ký phát triển đầy đủ – Tiếng Việt](DEVELOPMENT-LOG.md#tieng-viet) ghi chi tiết các chức năng, quy trình thao tác, phiên bản, trạng thái triển khai, kiểm chứng và công việc bảo trì đến ngày 2026-10-07.

[完整開發日誌－繁體中文](DEVELOPMENT-LOG.md#traditional-chinese) 詳列截至 2026-10-07 的功能、操作流程、版本、部署狀態、驗證與維護待辦。

v19.9.1 補上訂單完成頁的遊戲入口與實際發放狀態更新。店家完成合資格訂單、發放一次機會後，完成頁自動進入小遊戲；團購團主也可在送出頁取得自己的入口。資格與獎勵發放規則不變。詳見 [UPDATE-19.9.1.md](UPDATE-19.9.1.md)。

2026-10-07：使用者已確認 order 與 shop 使用獨立資料庫。空白資料庫全新安裝使用 `drink-order-cpanel-v19.9.0-shop-install.zip`，步驟見 [INSTALL-SHOP.md](INSTALL-SHOP.md)。安裝器生成越南時區設定，拒絕非空白資料庫與已有連線設定的網站重新安裝。保留原品牌圖示與上傳圖片。同日cPanel確認 `huiyitea_shop` 已有商品與訂單；經使用者批准，補上 `huiyitea_shop` 帳號對同名資料庫的 ALL PRIVILEGES，已驗證首頁與後台登入頁回應200。這個現有網站應使用更新包，無須重新安裝。

v19.9.0 在後台「Mini Game」加入三種主題、卡背與六組配對圖案上傳，以及可新增、停用的獎項清單。設定獎品、機率、使用條件與有效天數；啟用獎項的機率合計須為100%。新設定用於下一次開局，進行中的遊戲與已發優惠券保留原條件。詳見 [UPDATE-19.9.0.md](UPDATE-19.9.0.md) 與 [GAME-ADMIN-TEST-REPORT.md](GAME-ADMIN-TEST-REPORT.md)。

v19.8.1 將首頁推薦與分類商品卡片的 L、XL 價格改為上下兩行，杯型與金額靠右，金額右邊緣對齊。同步更新樣式快取。詳見 [UPDATE-19.8.1.md](UPDATE-19.8.1.md)。

v19.8.0 將附件 Ký Ức 10 Ly 翻牌遊戲接到點餐資格：選買10抽獎、單筆滿10杯並完成訂單後可玩1次。完成6組配對，隨機獲得指定加料、折5,000đ或指定紅茶，優惠券7天內用於下一單。後台「Mini Game」指定贈送商品與杯型；未設定前保留機會並暫停開局。詳見 [UPDATE-19.8.0.md](UPDATE-19.8.0.md) 與 [MINIGAME-TEST-REPORT.md](MINIGAME-TEST-REPORT.md)。

v19.7.0 新增後台門市管理：登記名稱、地址、電話及 Google 地圖位置，啟用或停用門市。一般與團購訂單成立後，依送餐地址建議直線距離最近的啟用門市；管理人員可以採用建議或手動改選，並保留派送變更紀錄。Google 地址定位金鑰在後台另行設定，定位失敗不影響下單。詳見 [UPDATE-19.7.0.md](UPDATE-19.7.0.md) 與 [DISPATCH-TEST-REPORT.md](DISPATCH-TEST-REPORT.md)。

v19.6.0 將買4送1、買10抽獎與獎項序號改為每筆訂單擇一使用，團購截止後固定優惠選擇。登入會員的抽獎機會與獎項券保存於平台帳號，可跨裝置查看與使用；會員頁新增獎項券入口。詳見 [UPDATE-19.6.0.md](UPDATE-19.6.0.md) 與 [PROMOTION-TEST-REPORT.md](PROMOTION-TEST-REPORT.md)。

v19.5.1 移除會員登入頁的帳號說明，將註冊區改為單一「註冊」按鈕，點擊後前往獨立註冊頁。註冊錯誤回到註冊頁，成功後自動登入並回到會員頁；三語同步調整。詳見 [UPDATE-19.5.1.md](UPDATE-19.5.1.md)。

v19.5.0 將團員付款改為「確認付款 → 待團主確認 → 已付款」。團長確認所有應付金額後才能結單；結單前可移除未付款團員，並重新計算優惠與應付金額。金額有變動時，團員需重新回報，再由團長確認；團長自己的付款可直接重新確認。未變動的已付款狀態保留。詳見 [UPDATE-19.5.0.md](UPDATE-19.5.0.md) 與 [GROUP-APPROVAL-TEST-REPORT.md](GROUP-APPROVAL-TEST-REPORT.md)。

v19.4.0 新增訂餐平台獨立會員、店員電話確認、iPOS 會員資訊與門市消費單向匯入、會員平台訂單及結帳資料帶入，並放大前台文字。平台帳號與平台消費不回寫 iPOS。會員功能可先使用；外部匯入預設關閉，須設定確認過的 HUB 授權與門市來源規則。詳見 [UPDATE-19.4.0.md](UPDATE-19.4.0.md) 與 [MEMBER-TEST-REPORT.md](MEMBER-TEST-REPORT.md)。

v19.3.2 移除團購頁關於團員可見資訊與公開分享連結的說明段落。客戶前台的團購、付款、優惠券、結帳及內建遊戲說明改為簡短的操作提醒，中文、越文與英文同步調整；結帳用語統一為「統一結帳／分開結帳」。本次只調整說明與畫面標籤，不修改付款、折扣、選購狀態或資料庫規則。

團購支援統一／分開結帳、團員付款方式與團長確認付款，團員卡片預設摺疊。團長可只開團、不訂飲料。

v19.3.1 將建立團購頁的步驟改為「01．建立團購單」「02．分享購物車」「03．統一結帳/分開結帳」。移除「我已選好」操作，團員購物車有商品即顯示已選好，清空後回到選購狀態；編輯品項與數量不需重新確認。

只有團長能選優惠券。買 4 送 1 以整團最低飲品基本價格計算，不含配料；折扣只扣團長自己的訂購與墊付款，最多扣到團長應付金額為零。團長訂購與墊付款兩者皆無時不套用優惠券。

未啟用團主代收時，分開結帳的團員可直接轉帳給店家、支付現金或選團長墊付。自行付款的團員按「確認付款」後，由團長核對並確認為已付款。統一結帳及團長墊付的款項，由團長確認其應付金額；沒有應付金額的人不需確認付款。所有款項確認後才能結單。截止後固定品項、付款方式與金額；移除未付款團員會依截止時的品項與優惠規則重算。團購頁、結帳頁、接單後台與通知信皆顯示付款明細。

## 更新與測試環境

檔名含 `-update.zip` 的 cPanel ZIP 為既有網站更新包，不是空白網站安裝器。更新時保留 `config.local.php`、`config.php`、`config.member.local.php`、`config.dispatch.local.php`、`uploads/`、根目錄 `.htaccess`、品牌圖示；既有 `minigame/` 先備份，再套用本版整合遊戲；不要刪除網站資料夾或重跑安裝。空白資料庫則依 [INSTALL-SHOP.md](INSTALL-SHOP.md)使用 shop 專用安裝包。

正式站為 `order.huiyitea.vn`；修改版本先在 `shop.huiyitea.vn` 測試。使用者於2026-10-07確認兩站的資料庫已分開，原先共用資料庫的部署限制已解除。19.10.0 開發者文件頁已部署至 shop 並完成本版閱讀、下載、權限及手機版面觀察，沒有資料庫結構變更或測試訂單；正式 order 站尚未更新。詳見 [DEVELOPER-PAGE-TEST-REPORT.md](DEVELOPER-PAGE-TEST-REPORT.md)。

前版付款確認更新及檢查結果見 [UPDATE-19.5.0.md](UPDATE-19.5.0.md) 與 [GROUP-APPROVAL-TEST-REPORT.md](GROUP-APPROVAL-TEST-REPORT.md)。會員更新、外部設定與前版檢查結果見 [UPDATE-19.4.0.md](UPDATE-19.4.0.md) 與 [MEMBER-TEST-REPORT.md](MEMBER-TEST-REPORT.md)。v19.3.2 的文字更新紀錄保留於 [CUSTOMER-COPY-UPDATE.txt](CUSTOMER-COPY-UPDATE.txt)。更新包依原路徑合併覆蓋至隔離後的 shop 測試站，保留既有設定與上傳資源；本機版本已更新為 19.12.0；本次尚未部署線上網站，19.10.0 的驗收是歷史紀錄。v19.3.1 的選購狀態更新與檢查紀錄見 [UPDATE-19.3.1.md](UPDATE-19.3.1.md) 與 [GROUP-READINESS-TEST-REPORT.md](GROUP-READINESS-TEST-REPORT.md)。付款規則與前版驗證保留於 [UPDATE-19.3.0.md](UPDATE-19.3.0.md) 與 [GROUP-PAYMENTS-TEST-REPORT.md](GROUP-PAYMENTS-TEST-REPORT.md)。開發測試入口位於 GitHub 程式庫的 `tests/README.md`。

其他 GROUP-ORDER／GROUP-QR／NOTIFICATION 文件與 UPDATE-19.2.x.md 為歷史版本紀錄。SHA256SUMS.txt 對應本次更新包，不含線上資料庫設定、使用者圖片或本機測試工具。
