# 接單通知修復：完整版 v19.1 ＋ 簡易版 v1.1

適用基底：drink-order-cpanel-v19.zip 與 huiyitea-mobile-admin-addon-v1.0.zip。
正式站：`https://order.huiyitea.vn`。這是兩個 PWA 的程式修復，不是 Android APK／iOS 原生 App。

## 先分清楚兩種通知

- 後台在前景：依原設定播放預設／上傳音效，可按秒、分或持續到接單；需要點擊啟用鈴聲。
- 後台在背景或鎖屏：由 Web Push 喚起 Service Worker 顯示手機系統通知，不需要頁面一直開啟。
- 鎖屏時不能把上傳的 MP3 當作原生鬧鐘持續播放；聲音、通知顯示及延遲仍受手機權限、專注模式、網路和省電機制影響。
- 「推播服務接受」不等於「手機已顯示」，「手機已處理通知」也不等於人員已讀或已接單。

## 安裝前

先備份網站檔案與資料庫。保留 `config.local.php`、全部 `uploads/`、既有資料庫及 VAPID 金鑰。
不要刪除任何網域，不要重新執行 setup.php，不要為修通知重建空白資料庫。
本次必須一起更新共用推播程式與兩個後台，不能只替換 mobile-admin/app.js。

修補包只包含更動／新增的程式與說明；完整包包含主系統及 mobile-admin/。兩者擇一使用，版本及修復相同。
若線上已有其他人修改過同名程式，先比較再合併，不要盲目覆蓋。

## 1. 部署到正確網站目錄

在 cPanel → Domains 確認 `order.huiyitea.vn` 的 Document Root。
你先前提供的目錄為 `/home/huiyitea/order.huiyitea.vn/`，仍以當下 cPanel 顯示為準。

把 ZIP 內容合併／覆蓋到該根目錄，裡面應直接有 `admin/`、`lib/`、`assets/`、`mobile-admin/` 等，不多包一層資料夾。
若 cPanel Extract 沒有覆蓋同名檔案，改用 File Manager 的逐檔替換或 FTP/SFTP 上傳已解壓檔案，確認同名檔案確實已換新；不要刪除整個網站資料夾。

根目錄 VERSION 應為 `19.1`，mobile-admin/VERSION 應為 `1.1`。
`admin/settings.php` 的推播區會標示 v19.1；兩個後台的「通知設定」視窗會顯示 Core v19.1。
這比只確認 ZIP 已上傳更可靠。套件內有 SHA256SUMS.txt 可供逐檔核對。

第一次開啟會擴充原 push_subscriptions，新增 push_delivery_jobs。資料庫帳號須具備原系統及本次遷移需要的 CREATE／ALTER／INDEX 等權限。
不修改訂單、商品、配料、優惠券、遊戲或 Email 模板資料。原有 VAPID 金鑰保留；舊訂閱會保留為 legacy，但需重新啟用後才列入新版發送。

## 2. 每台接單手機重新驗證訂閱（必要）

完整版：`https://order.huiyitea.vn/admin/`
簡易版：`https://order.huiyitea.vn/mobile-admin/`

從手機主畫面現有 App 圖示開啟，確認不是舊 shop 網域捷徑，登入原管理帳號。
按「通知設定」→「啟用／修復此手機推播」，在手機權限提示選允許。
成功後應看到「伺服器訂閱：已驗證儲存」以及正確服務範圍 `/admin/` 或 `/mobile-admin/`。
按「啟用鈴聲」測試前景聲音；這與系統通知權限是分開的。

已經在正確 order 網域安裝的 App，一般不必刪除重新安裝；關閉再開啟並驗證版本即可。
若是舊 shop 捷徑，改從 order 網址重新加入主畫面。不同網域的舊訂閱不會自動搬移。
同一支手機可保留兩個 App，但建議只啟用其中一個的推播，避免同一訂單收到兩次通知。
本次停止舊根目錄訂閱時，不會移除前台 Service Worker，也不會取消另一個後台的新版訂閱。

### iPhone／iPad

使用支援 Web Push 的 iOS／iPadOS（16.4 起）。Safari → 分享 → 加入主畫面；從主畫面圖示開啟，再按啟用／修復。
在設定 → 通知中允許這個 Web App 的通知、鎖定畫面與聲音；測試時確認專注／勿擾模式沒有擋住它。
不是只在 Safari 分頁中登入就完成主畫面 Web App 訂閱。

### Android

用支援 Web Push 的瀏覽器，例如 Chrome，打開上述網址並使用選單的安裝／加入主畫面。
允許 App／瀏覽器的通知，確認裝置沒有封鎖這個站點。一般切換 App 與作業系統的「強制停止」不同，後者不保證還能收推播。
不同廠牌的背景與省電限制不同，須做實際鎖屏測試。

## 3. 設定推播重試排程（建議必做）

首送仍在建立訂單後立即嘗試，不依賴手機定時檢查。
網路逾時、HTTP 408／429／5xx 等暫時錯誤，以及尚未完成的發送工作會留在佇列，由 cPanel Cron 補送。
沒有 Cron 時仍會嘗試首送，但排隊的重試不會憑空執行；不能當作完整故障重試已啟用。

cPanel → Cron Jobs，設每分鐘執行一次（若主機限制最低頻率較低，依主機允許值設定，重試也會較慢）：

```cron
* * * * * /opt/alt/php83/usr/bin/php /home/huiyitea/order.huiyitea.vn/bin/process-push-queue.php
```

以上 PHP 路徑是依你先前 PHP 8.3 環境提供的範例。先向主機商或在終端確認實際存在的 PHP CLI 路徑；CLI 必須有 OpenSSL、cURL、pdo_mysql，且能讀取目前 config.local.php。
先保留 Cron 輸出，確認無錯後再依需要關閉排程輸出郵件。不可把腳本改成不登入就能從網頁觸發。
「通知設定 → 重試排程最後執行」應更新；若沒有更新，請檢查 Cron 路徑、權限與輸出。

推播有效期為一小時，暫時性錯誤最多四次發送嘗試；404／410 停用失效訂閱，需手機重新修復。
訂單若已接單／取消／完成，不再補送逾時的新單提示。傳輸紀錄保留三十天，Cron 清理的只是傳輸紀錄，不刪訂單。

## 4. 做真正的端到端測試

先按「伺服器推播測試」，不是本地 showNotification 假測試。
再按「15 秒後測試（可先鎖屏）」，看到測試已開始後鎖定手機；延遲在伺服器執行，不靠背景分頁計時器。
若主機終止 PHP 請求或限制執行時間，仍需看實際紀錄。測試按鈕有二十秒間隔限制。

最後用另一台裝置下一筆真正測試訂單，驗證：
1. 手機鎖屏能收到系統通知；點擊開啟正確版本的訂單頁。
2. 若登入已過期，重新登入後回到那張訂單。
3. 開啟後台並啟用鈴聲，待接訂單能提醒；改成已接單後停止鈴聲。
4. 重開 App／恢復網路後會補查待接訂單，並保留訂單狀態。
5. 原本多收件人 HTML Email 仍正常。

## 診斷如何看

| 顯示 | 含義／下一步 |
| --- | --- |
| 伺服器訂閱未完成 | 手機資料未成功存入主機，點啟用／修復並查看錯誤；不要當作已連接 |
| push_schema_error | 確認共用檔案已覆蓋及資料庫遷移權限 |
| curl_unavailable／OpenSSL 不可用 | 在網站所用 PHP 啟用必要擴充套件，CLI 也要有 |
| curl_6／curl_7／curl_28 | 分別常見於 DNS／連線／逾時；由主機商查對外 HTTPS，不關閉 TLS 驗證 |
| HTTP 401／403 或 vapid_changed_resubscribe | 查看供應商原因碼，檢查主機時間、VAPID 配對；不要直接刪除金鑰 |
| HTTP 404／410 | 訂閱失效，從該手機點啟用／修復 |
| 推播服務已接受 | 只代表 FCM／APNs／Mozilla 等服務接受請求，尚未證明手機顯示 |
| 手機已處理通知 | Service Worker 的顯示呼叫成功後回報；不是人員已讀／接單 |
| 等待重試 | 確認 cPanel Cron 正常執行 |
| 主機接受但沒有手機回報 | 檢查通知權限、手機網路／系統限制；回報本身也可能因網路失敗 |

## 已驗證與尚待驗證

套件已做 PHP／JavaScript 語法、獨立解密與簽章、模擬傳輸回應、Service Worker 事件及手機寬度介面測試。
沒有登入你的正式主機，也沒有操作你的 iPhone／Android；正式主機的資料庫遷移、對外推播連線與實機鎖屏送達仍須以上測試確認。
詳見 NOTIFICATION-TEST-REPORT.md。

## 官方平台資料

- WebKit：Web Push for Web Apps on iOS and iPadOS — https://webkit.org/blog/13878/web-push-for-web-apps-on-ios-and-ipados/
- MDN Push API — https://developer.mozilla.org/en-US/docs/Web/API/Push_API
- MDN showNotification — https://developer.mozilla.org/en-US/docs/Web/API/ServiceWorkerRegistration/showNotification
- MDN Page Visibility — https://developer.mozilla.org/en-US/docs/Web/API/Page_Visibility_API
- RFC 8291（推播內容加密）／RFC 8292（VAPID）— https://www.rfc-editor.org/rfc/rfc8291 / https://www.rfc-editor.org/rfc/rfc8292
