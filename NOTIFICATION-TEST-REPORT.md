# 接單通知程式檢查與測試報告

修復版本：主系統 v19.1／簡易接單 v1.1。
檢查來源：本次提供的 drink-order-cpanel-v19.zip 與 huiyitea-mobile-admin-addon-v1.0.zip。
本報告針對安裝包程式，不代表已登入正式站、查閱正式伺服器日誌，或完成使用者實機驗收。

## 從原始碼確認的問題

| 位置 | 原實作 | 本次修復 |
| --- | --- | --- |
| mobile-admin/app.js，推播按鈕處理 | 訂閱儲存 fetch 後沒有驗證 HTTP 狀態或 JSON 成功結果，就顯示已啟用 | 共用驗證流程，API 寫入成功且再次確認伺服器訂閱才顯示已連接 |
| assets/js/app.js，完整版推播初始化 | 依手機本地 getSubscription 顯示狀態，不確認伺服器是否仍有對應紀錄 | 每次開啟／返回核對裝置訂閱、伺服器紀錄與金鑰 |
| 完整版啟用後測試 | reg.showNotification 為手機本地通知，不經過伺服器 → 推播服務 | 新增實際伺服器推播測試與十五秒延遲測試 |
| 兩版 requestPermission | 等待註冊等非同步操作後才要求通知權限 | 在按鈕點擊當下立即提出權限請求，然後才等待後續流程 |
| Service Worker 選擇 | 完整版一般查找註冊，簡易版沒有嚴格等待指定 worker 啟用 | 完整版 /admin/、簡易版 /mobile-admin/ 指定不同 scope 與 worker，驗證 active state／scriptURL |
| lib/v186.php | 推播 TTL 120 秒；無持久重試佇列；缺少端到端到達回報 | 一小時有效期、持久佇列、受限重試、HTTP 原因碼與裝置回報 |
| 舊前景輪詢 | 以目前最大訂單 ID 當起點；重開／背景返回可能略過既有待接單 | API 返回待接訂單快照，重連與回前景補查；不靠背景 setInterval 維持通知 |
| 簡易版鈴聲 | 每 1.6 秒重啟音效，會切斷較長自訂鈴聲 | Audio 原檔循環＋單一計時器；切換狀態／手動停止時清理 |
| 金鑰、訂閱 | 初始化金鑰未序列化；未嚴格驗證配對／公鑰座標長度；訂閱失效只停用 | 保留既有金鑰、鎖定初始化、P-256 驗證；404／410 後由手機修復重新訂閱 |
| 下單通知的錯誤界線 | 已提交訂單後的通知工作仍置於原交易錯誤處理範圍 | 已提交訂單不因通知失敗變成下單失敗；推播先排入佇列，Email 函式本身不變 |

原來的空 payload Web Push 並不因為「沒有內容」就必然違反規範。新版使用標準加密 payload，是為了傳遞正確訂單連結、通知標籤及回報能力；未把空 payload 單獨當成實機故障的證據。
原生手機系統是否封鎖通知、伺服器出站連線是否成功等，不能只由安裝包推斷。

## 實作範圍與資料保護

新增／替換的核心為 lib/push_crypto.php、lib/push_service.php、lib/push_api.php、lib/push_ui.php，以及兩個 scoped worker 和共用通知 JavaScript。
新增 push_delivery_jobs；原 push_subscriptions 擴充擁有者、應用範圍、完整 endpoint 雜湊、續訂能力及回報時間。
舊資料保留，但每台裝置必須重新啟用，才納入新版發送。

每筆訂單／每個訂閱有唯一工作鍵；以 SQL 條件更新取得工作鎖。逾時工作可回收，最多四次嘗試，傳輸重試有有效期。
已被接單或取消的訂單不補送新單通知。時間比較由資料庫 UNIX_TIMESTAMP 取得，避免 PHP 與 MySQL 時區不同而誤判有效期。
訂閱 API 需要管理員登入與 CSRF，限制 HTTPS 推播服務網址，不跟隨任意 HTTP 重新導向，TLS 憑證驗證保持啟用。
續訂及到達回報用受限隨機能力碼；未登入的回報端點不能讀取或修改訂單。
鎖屏推播不含客戶姓名、電話、配送地址及發票資料。診斷記錄不輸出私鑰、auth secret、完整 endpoint 或完整 JWT。
登出／登入逾時與停用推播是不同狀態；交還或更換人員使用的裝置前，請先點「停用此手機推播」。

## 已完成測試

### 語法

- 57 個 PHP 檔案：PHP 8.4 CLI 語法檢查通過。
- 7 個 JavaScript 檔案：Node 語法檢查通過。
- JSON manifests 與版本檔檢查通過。

### 加密與傳輸契約

- PHP 產生的三組 RFC 8291 payload，由獨立 Python cryptography 實作完成 ECDH／HKDF／AES-GCM 解密，驗證 Unicode、一般 JSON 與 2,850-byte 內容。
- 獨立驗證 ES256 簽章、DER／JOSE 轉換。
- 十一種 endpoint 允許／拒絕案例、三種錯誤金鑰驗證。
- 十一種模擬 HTTP／cURL 結果（201、202、400、401、403、404、410、408、429、503、逾時）驗證接受、重試與失敗分類。
- TLS 驗證、禁止跳轉、加密內容、TTL、原金鑰保留、VAPID audience／有效時間檢查。

### Service Worker 與介面

- Node 模擬 worker 事件：兩種 scope 的通知顯示、訂單連結、到達回報、跨來源惡意連結拒絕、舊式空 payload 及錯誤 JSON fallback。
- Chromium 離線 DOM 整合測試：完整版與簡易版，各以 320／390／768／1280px 測試，共八組。
- 檢查失敗的伺服器儲存不會顯示成功、權限請求時機、修復／停用流程、伺服器測試動作、接受不等於送達的文字、重開待接訂單提醒與鈴聲、無水平溢出及 JavaScript 例外。
- 介面測試的 PushManager、Notification、HTTP 與 AudioContext 是測試替身，不是真實 APNs／FCM 或實機聲音驗收。

### 原系統保留

- 24 個重要主系統業務／樣式／Email 檔案與原 v19 逐位元相同，含 lib/helpers.php、lib/v179.php、Mini Game、購物車及主要前台頁面。
- 簡易版 index.php、order.php、style.css、logout.php 四檔與 v1.0 逐位元相同。
- 下單資料驗證、金額／優惠券計算與寫入資料庫至 commit 之前的交易程式相同；只調整 commit 後的通知流程與錯誤隔離。

## 尚未完成的正式環境驗證

測試容器沒有可用的真實 MySQL／MariaDB 服務及 PHP cURL 擴充。傳輸回應以替身驗證；沒有實際連到 FCM、APNs、Mozilla 推播服務。
容器 Chromium 的網頁導航受管理政策限制，因此沒有繞過政策；採離線 DOM 與 worker 事件測試，不宣稱原生 Service Worker 註冊或 APNs 端到端測試已通過。

正式主機仍要確認：MySQL 遷移、網站 PHP 與 CLI 擴充、出站 HTTPS、VAPID、Cron、iPhone／Android 安裝及通知權限、各手機的鎖屏送達。
請依 NOTIFICATION-UPGRADE.md 的「十五秒後伺服器測試」及真正新訂單測試完成驗收。

## 結論

已修正可從程式確認的缺陷，並補足診斷與重試；不能以本地測試推論每一支手機已恢復常駐通知。
正確驗收是：伺服器訂閱已驗證 → 推播服務接受 → 手機通知／回報 → 點開正確訂單 → 接單後前景鈴聲停止。
