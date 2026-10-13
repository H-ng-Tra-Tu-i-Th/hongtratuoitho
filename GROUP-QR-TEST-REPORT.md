# 團購 QR Code v19.2.2｜本機檢查報告

## 結果

- PHP 語法檢查：62 個檔案通過（本機 PHP 8.4.23）。
- JavaScript 語法檢查：10 個檔案通過（本機 Node 22.16）。
- 功能與版面：262 項自動斷言通過。

## 測試內容

使用實際 `hyg_render_share()` PHP 輸出與發布版 CSS／JavaScript，於本機無頭 Chromium 中檢查。
團購狀態 API 使用模擬回應，未使用正式 MySQL；權限／到期邏輯以隔離 PHP 測試資料執行。
瀏覽器的系統分享及剪貼簿 API 使用可控模擬檢查降級分支，未實際傳送至 Zalo／LINE／其他 App。
本環境禁止瀏覽器網路瀏覽，版面以相同檔案的內嵌本機測試頁載入。

涵蓋：
- 團主才會取得分享介面；一般團員不取得邀請 QR。
- 揪團中可見；截止、取消、送出、逾時與到期邊界不提供 QR。
- 無效邀請碼不繪製；團購名稱正確 HTML 跳脫。
- 越／英／繁中與深紅色主題；320、360、390、430、768、1280px 共 24 組版面。
- 無水平超出、按鈕觸控高度、QR 白色邊界。
- 不只檢查圖片存在：使用獨立 ZBar 解碼下載原圖與每個螢幕尺寸的 QR 截圖，均吻合完整邀請 URL。
- 12 組額外 URL：不同隨機邀請碼、較長子目錄、URL 編碼路徑，編碼後獨立解碼一致。
- 實際瀏覽器 PNG 下載取得正確檔名，下載檔解碼一致。
- 支援檔案分享時包含 QR；不支援則分享連結；無系統分享則複製；取消分享不誤寫剪貼簿。
- 被禁止的剪貼簿與 QR 生成錯誤有簡短替代操作，連結仍可複製。
- 另一分頁更換邀請後，模擬同步更新輸入框、分享、QR 及下載；編輯品項時亦更新分享。
- 截止隱藏 QR，重開恢復目前 QR；本機倒數到期亦隱藏圖片及下載。
- 返回頁面快取時先核對目前分享；未授權狀態時隱藏邀請。
- 生成 QR 無外部 QR 請求。

## 未變動的部分

檔案逐位元比較確認 `lib/group_order.php`、`lib/group_schema.sql`、`actions/submit_order.php`、
`lib/helpers.php`、`lib/v179.php`、`lib/push_service.php`、`bin/process-push-queue.php`、
`admin/sw.js`、`mobile-admin/sw.js` 與 v19.2.1 相同。
四小時遷移標記保留 19.2.1，不會因資源版本升至 19.2.2 而再次縮短既有團購期限。

## 尚未驗證

未登入正式 cPanel、未執行正式資料庫遷移、未在實際 Android／iPhone 相機掃碼或操作系统分享，
未發送正式訂單信或 Web Push。這些均須部署後以測試團購驗收。
不以本機通過宣稱正式手機與郵件／通知已送達。

## 新增／更動檔案

- `README.md`
- `UPDATE-19.2.2.md`
- `VERSION`
- `assets/css/group-order.css`
- `assets/js/app.js`
- `assets/js/group-order.js`
- `assets/js/group-qr.js`
- `assets/vendor/QR-CODE-APACHE-LICENSE.txt`
- `assets/vendor/QR-CODE-MIT-LICENSE.txt`
- `assets/vendor/QR-CODE-NOTICE.txt`
- `assets/vendor/huiyi-qrcode.js`
- `group/index.php`
- `group/state.php`
- `lib/group_i18n.php`
- `lib/group_views.php`
- `lib/layout.php`
- `service-worker.js`
