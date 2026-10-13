<a id="tieng-viet"></a>

# Kiểm chứng quy trình thanh toán — 19.15.0

Ngày ghi nhận: 2026-10-12, Asia/Saigon. Báo cáo dành cho mã nguồn 19.15.0; giữ nguyên các báo cáo và bản chụp phiên bản đã phát hành.

## Môi trường và giới hạn

Các lượt kiểm chứng dùng dữ liệu tự tạo, tài khoản giả lập, database dùng một lần trên loopback và trang thử riêng ở máy cục bộ. Bộ nhóm mới tạo database `huiyi_test_group_approval_19150_group_<mã_ngẫu_nhiên>` mới cho mỗi lần chạy, không cài lại schema vào database đang có. Bộ hồi quy nhóm cũ cũng dùng database thử mới.

Không dùng dữ liệu khách thật hoặc cấu hình riêng của website thật. Không gọi ngân hàng, iPOS, bản đồ, email, push hay dịch vụ bên ngoài. Kiểm chứng trình duyệt dùng phản hồi giả lập được phục vụ hoặc chặn cục bộ. HTTP thực tế ở đây là các route PHP của ứng dụng trên loopback, không chứng minh shop đã triển khai.

## Kết quả đã thực sự chạy

| Bộ kiểm chứng | Số kiểm tra đạt | Bằng chứng/phạm vi |
| --- | ---: | --- |
| Tính toán thanh toán nhóm thuần | 2.832 | `tests/group_payments_unit.php`; không database hoặc mạng. |
| Sổ thanh toán nhóm mới, database thật cục bộ | 105 | `tests/group_payment_audit_integration.php` và worker độc lập; PDO MySQL dùng một lần. |
| Hồi quy nhóm cũ, chế độ trực tiếp | 74 | `tests/group_approval_integration.php --direct`; database thử mới. |
| Backend quy trình thanh toán | 67 | `tests/payment_workflow_integration.php`; môi trường thử độc lập. |
| Checkout khách hàng thuần | 46 | `tests/customer_checkout_unit.php`; session giả lập, không database hoặc mạng. |
| JavaScript phía khách | 23 | `tests/transfer_frontend.js`; trạng thái giao diện và gửi lại. |
| Trình duyệt khách hàng headless | 126 | `tests/payment_ux_browser.js`; ba ngôn ngữ, kích thước màn hình, phản hồi giả lập. |
| Renderer/giao diện nhân viên | 72 | `tests/payment_staff_mobile_browser.js` và fixture; render không thay thế kiểm tra quyền endpoint. |
| Trang tài liệu nhà phát triển | 3.182 | `tests/developer_page_test.php`; tài liệu, phiên bản và render an toàn. |
| HTTP trang tài liệu nhà phát triển | 179 | `tests/developer_page_http_test.py`; site loopback và xác thực thử. |
| HTTP thực tế của ứng dụng, tổng cuối | 85 | Đã đạt 74 kiểm tra nền tảng và hoàn tất các tình huống khôi phục bổ sung; tổng 85 kiểm tra đều đạt. |

Các số là số assertion từng bộ, không phải tỷ lệ bao phủ hoặc số tình huống hoàn toàn độc lập. Không cộng lượt chạy lại để tăng kết quả. File PHP thay đổi trong phần nhóm đã qua kiểm tra cú pháp; kiểm tra khoảng trắng của diff cũng đạt. Bộ nhóm mới và hồi quy trực tiếp được chạy lại sau sửa thanh toán chênh lệch và ghi nhận hoàn tiền nhầm.

## Kiểm chứng trọng tâm của nhóm

- Nâng cấp giữ số tiền/thời điểm xác nhận cũ; báo chưa xác nhận không trở thành tiền đã nhận. Lặp nâng cấp không tạo sự kiện trùng.
- Báo thanh toán đang chờ ngăn xóa trực tiếp thành viên. Từ chối báo sai, ghi khoản đã hoàn hoặc sửa ghi nhận đều lưu lý do và lịch sử; xóa sau xử lý vẫn giữ chứng cứ.
- Tách hạn chọn món và thanh toán. Nhóm đã báo/xác nhận tiền giữ khả năng kết toán sau hạn; gia hạn cần quyền trưởng nhóm, phiên bản, chữ ký và lý do.
- Ưu đãi làm khoản trưởng nhóm giảm 125.000 xuống 120.000: giữ chứng cứ 125.000, hiện phần phải hoàn 5.000 và chỉ cân bằng sau khi ghi khoản hoàn này.
- Khoản 30.000, hoàn 10.000, còn lại 20.000: giao diện/nút/xác nhận/chỉ số gửi lên chỉ yêu cầu thêm 10.000. Gửi toàn bộ 30.000 bị từ chối. Sau xác nhận thêm, tổng thu 40.000, hoàn 10.000, còn lại 30.000; không ghi nhận lại 20.000 cũ.
- Mỗi báo/xác nhận kiểm tra `revision`, `quote_signature` và `payment_amount` đúng phần còn thiếu hiện tại. Báo và sự kiện xác nhận chỉ lưu phần tiền mới.
- Đã nhận 20.000 nhưng ghi nhầm hoàn 5.000: sửa hoàn về 0 giữ tổng thu 20.000 và một lần xác nhận thu. `correct_refund` giữ điều chỉnh âm cùng dữ liệu trước/sau, không tạo khoản thu giả 25.000.
- Báo khoản trả thêm đang chờ phải được xác nhận/từ chối trước khi hoàn/sửa khoản cũ. Từ chối báo mới không xóa tiền đã xác nhận trước đó.
- Hoàn không vượt tiền đã xác nhận chưa hoàn; chưa nhận tiền không ghi thành hoàn. Hai tiến trình cùng hoàn một khoản chỉ có một thao tác và sự kiện thành công.
- Đã kiểm tra quyền trưởng nhóm, CSRF, phiên bản/số tiền/chữ ký sai, nhập sai, nội dung ba ngôn ngữ và không lộ mã thiết bị.

## Ý nghĩa số tiền và lịch sử

Trưởng nhóm xác nhận khoản đã kiểm tra hoặc phần đóng góp đã chuẩn bị. Trong chế độ trưởng nhóm thu, đó không đồng nghĩa cửa hàng đã nhận tiền; cửa hàng đối soát thực nhận riêng. Báo thanh toán của khách/nhóm không thay thế bằng chứng ngân hàng ghi có.

Sổ sự kiện giữ báo, xác nhận, thay đổi khoản phải trả, từ chối, hoàn, sửa sai và loại thành viên. Trang nhóm hiển thị 100 sự kiện gần nhất; sự kiện cũ vẫn lưu trong database. Đơn đã gửi giữ bản chụp lịch sử, trưởng nhóm không sửa lại. Hoàn của cửa hàng sau nhận đơn thuộc quy trình tiền cửa hàng. Thao tác hoàn/sửa chỉ ghi kết quả đã được người có quyền kiểm tra, không chuyển khoản hoặc hoàn qua ngân hàng.

## Triển khai và việc còn lại

Trạng thái tại thời điểm tạo gói: **chưa triển khai shop**. Bằng chứng triển khai/nghiệm thu thực tế sau đó được ghi riêng trong báo cáo bàn giao ở `outputs/`, không sửa lại bản ghi lúc tạo gói này.

Chỉ ghi triển khai thành công sau kiểm tra phiên bản, website/database riêng của shop và route liên quan. Kiểm chứng cục bộ hoặc đăng nhập cPanel không chứng minh giải nén cập nhật, migration hoặc nghiệm thu shop đã xong. Trạng thái nghiệm thu phải dựa trên kết quả thực tế, không suy đoán.

Chưa chứng minh ngân hàng nhận tiền thật, ứng dụng ngân hàng quét QR thành công, khoản hoàn thật đến người nhận, dịch vụ bên ngoài hoạt động hoặc order.huiyitea.vn đã cập nhật. Không có webhook ngân hàng, chuyển tiền tự động hoặc hoàn tự động trong phạm vi kiểm chứng.

<a id="traditional-chinese"></a>

# 付款流程驗證報告 — 19.15.0

紀錄日期：2026-10-12，Asia/Saigon。本報告對應 19.15.0 程式；已發布版本的報告與快照保持原樣。

## 環境與界限

驗證使用自行建立的測試資料、模擬帳戶、loopback 一次性資料庫及本機獨立測試頁面。新團購測試每次建立 `huiyi_test_group_approval_19150_group_<隨機碼>` 新資料庫，不對既有資料庫重跑安裝；既有團購回歸也使用另一個全新測試資料庫。

未使用真實顧客資料或網站私有設定，未呼叫銀行、iPOS、地圖、Email、推播或其他外部服務。瀏覽器測試在本機提供或攔截模擬回應。本報告的真實 HTTP 指 loopback 上的應用程式 PHP 路由，不代表 shop 已完成部署。

## 已實際執行的結果

| 驗證組別 | 通過檢查數 | 證據／範圍 |
| --- | ---: | --- |
| 團購純付款計算 | 2,832 | `tests/group_payments_unit.php`；無資料庫或網路。 |
| 新團購事件簿與真實本機資料庫 | 105 | `tests/group_payment_audit_integration.php` 與獨立 worker；一次性 PDO MySQL。 |
| 既有團購直接資料庫回歸 | 74 | `tests/group_approval_integration.php --direct`；全新測試資料庫。 |
| 付款流程後端 | 67 | `tests/payment_workflow_integration.php`；隔離測試環境。 |
| 客戶結帳純資料規則 | 46 | `tests/customer_checkout_unit.php`；模擬 session，無資料庫或網路。 |
| 客戶端 JavaScript | 23 | `tests/transfer_frontend.js`；介面狀態與重送。 |
| 客戶無頭瀏覽器 | 126 | `tests/payment_ux_browser.js`；三語、不同尺寸及模擬回應。 |
| 店員 renderer／介面 | 72 | `tests/payment_staff_mobile_browser.js` 與 fixture；render 不代替路由權限檢查。 |
| 開發者文件頁 | 3,182 | `tests/developer_page_test.php`；文件、版本與安全呈現。 |
| 開發者文件頁 HTTP | 179 | `tests/developer_page_http_test.py`；loopback 測試站與模擬登入。 |
| 應用程式真實 HTTP 最終總數 | 85 | 74 項基線及追加恢復情境均已完成，最終 85 項全部通過。 |

以上是各測試組的 assertion 數，不是覆蓋率，也不代表情境全都互不重疊；不累加重跑次數。團購變更的 PHP 檔案已通過語法及差異空白檢查。差額付款與誤記退款修正完成後，新團購資料庫測試及既有直接回歸均已重跑。

## 團購重點驗證

- 升級保留舊確認金額／時間，未確認報款不轉成已收款；重跑升級不產生重複事件。
- 待處理報款團員不能直接刪除。拒絕誤報、記錄實際已退回款或更正，都保留原因與歷程；處理後移除仍保留付款證據。
- 選購與付款期限分開，已有報款／核款的團購到期後仍能結清；延長需要團主權限、版本、簽章及原因。
- 優惠重算使團主應付從 125,000 變成 120,000：保留原 125,000 證據，顯示應退 5,000，記錄退回差額後才恢復平衡。
- 原付款 30,000、退款 10,000、保留淨款 20,000：畫面／按鈕／確認／送出數字只要求新增 10,000。誤送完整 30,000 被拒絕。核款後累計收款 40,000、退款 10,000、淨款 30,000，不重複核入舊 20,000。
- 每次回報／確認檢查 `revision`、`quote_signature` 與精確對應當下差額的 `payment_amount`；回報與確認事件只保存本次新增款。
- 已收 20,000 卻誤記退款 5,000：更正退款為零後仍只有原 20,000 收款與一筆核款。`correct_refund` 保存負向調整及前後金額，不造出 25,000 假收款。
- 新增報款待核時，須先核對／拒絕，再退款／更正舊款；拒絕新報款不清除已確認的舊淨款。
- 退款不超過尚未退回的核對款，未收款不誤記退款；兩個獨立程序並行退同一款，只有一個動作與事件成功。
- 已驗證團主權限、CSRF、錯誤版本／金額／簽章、錯誤輸入、三語內容與裝置密鑰不外露。

## 金額與歷程的意義

團主確認表示已核對款項或備妥自己的出資。團主收款模式的資金不代表店家已實收，店家須另行對帳；顧客／團員報款不代替銀行入帳證據。

事件簿保存報款、核款、應付變更、拒絕、退款、更正與移除。頁面顯示最近 100 筆，較舊事件仍留在資料庫。已送單保留歷史快照，團主不能改寫；店家接單後退款由店家金流處理。退款與更正只記錄有權人員核對後的結果，網站不執行銀行轉帳或退款。

## 部署與未完成事項

更新包產製時的狀態：**shop 待部署**。後續實際部署／驗收證據另記於 `outputs/` 交付報告，不重寫本報告的產包時點紀錄。

實際檢查版本、shop 獨立網站／資料庫及相關路由後才記錄部署成功。本機通過或登入 cPanel 不等同更新解壓、資料庫升級或 shop 驗收完成。後續驗收狀態均依實際完成結果記錄，不推測。

尚未證實真實銀行入帳、銀行 App 成功掃描 QR、實際退款到達收款人、外部服務正常或 order.huiyitea.vn 已更新。驗證範圍沒有銀行 webhook、自動轉帳或自動退款。
