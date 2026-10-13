# 19.13.0 — Kiểm chứng mỗi 10 ly một lượt / 每滿十杯抽獎驗證

## Tiếng Việt

Ngày: 2026-10-08, UTC+7. Kết quả dưới đây chỉ là kiểm thử cục bộ với dữ liệu giả lập trên MariaDB/PHP tại loopback. Không đọc cấu hình riêng của website, không tạo đơn trên shop/order, không gửi email hoặc yêu cầu dịch vụ kinh doanh bên ngoài. Dịch vụ thử đã dừng sau khi hoàn tất.

| Bộ kiểm tra | Kết quả |
| --- | --- |
| Ưu đãi trực tiếp, gồm các ca mỗi 10 ly mới | 236 đạt |
| HTTP giỏ, gửi đơn, máy tính/điện thoại, nhóm và lối vào game | 135 đạt |
| Biên quyền sở hữu, báo giá đã chốt và trạng thái hoạt động | 56 đạt |
| Hai tiến trình đồng thời: cấp lượt, nhận ví, dùng thưởng | 23 đạt |
| Tương thích dữ liệu và báo giá lịch sử | 41 đạt |
| Mini Game: điều kiện, lưu bàn, nhận thưởng, đồng thời và khôi phục lỗi | 106 đạt |
| Tài liệu, phiên bản, an toàn nội dung và 28 sơ đồ | 3.170 đạt |
| HTTP riêng tư của trang tài liệu và tải nhật ký | 158 đạt |

Tổng: 597 khẳng định nghiệp vụ và 3.328 khẳng định tài liệu. Kiểm tra cú pháp PHP và tính toàn vẹn ZIP được ghi riêng khi đóng gói; không coi các khẳng định này là nghiệm thu website thật.

Các tình huống đã kiểm chứng:

- 9 ly = 0; 10/19 ly = 1; 20/29 ly = 2; 30 ly = 3. Hai đơn 9 ly không cộng với nhau; topping mua riêng không tính.
- Giỏ và báo giá nhóm mới lưu số lượt theo mỗi 10 ly. Nhóm có nhiều dòng / nhiều người được tính tổng trong cùng đơn. Xóa người tham gia làm nhóm 25 ly xuống 19 ly: 2 lượt còn 1; 30 xuống 20: 3 còn 2.
- Đơn 20 ly qua quản trị máy tính nhận 2 lượt; 30 ly qua quản trị điện thoại nhận 3. Lặp hoàn tất không cộng thêm. Hai tiến trình hoàn tất cùng đơn 20 ly chỉ cộng tổng cộng 2 lượt.
- POST tự điền 99 lượt bị bỏ qua; số lượt do máy chủ quyết định. Ưu đãi khác không cộng lượt. Chưa hoàn tất không cấp; lỗi giao dịch không để lại khoản cộng một phần.
- Nhóm 20 ly gửi thật qua endpoint thử lưu 2 lượt và cấp cho trưởng nhóm. Quyền ví và tài khoản giữ nguyên.
- Trạng thái chờ và sẵn sàng hiển thị số lượt đúng ở ba ngôn ngữ. Thành công tự chuyển tới game sau cấp lượt; polling không tự cấp thêm.
- Đơn cũ 20 ly đã nhận 1 lượt không truy cấp. Đơn / nhóm đã chốt 1 lượt vẫn giữ 1; nhóm cũ mở lại và chốt 30 ly nhận báo giá 3. Số lượt sai hoặc vượt số ly hợp lệ không được cấp.
- Toàn bộ bản ghi 19.9.1–19.12.0 và gói 19.12.0 được giữ nguyên khi tạo gói mới. Bản mới giữ 14 sơ đồ mỗi ngôn ngữ và tải đúng nội dung Markdown.

Mã kiểm tra: `tests/promotions_integration.php` cùng `tests/per_ten_draw.php`, `tests/minigame_integration.php`, `tests/developer_page_test.php`, `tests/developer_page_http_test.py`. Bộ môi trường thử chỉ dùng database có tên được bảo vệ và localhost; không đưa initializer, session hoặc cấu hình thử vào gói.

Chưa thực hiện: triển khai/kiểm thử trên shop, cập nhật order, thanh toán hoặc thông báo thật. Không lặp lại nghiệm thu giao diện desktop/mobile bằng trình duyệt của 19.12.0; bộ hiển thị không đổi, nội dung mới đã kiểm qua renderer và HTTP. Ưu tiên nghiệm thu shop bằng database riêng trước khi bố trí order.

## 繁體中文

日期：2026-10-08，UTC+7。以下僅為本機 MariaDB／PHP 隔離環境的合成資料測試；未讀取網站私有設定、未在 shop／order 建單、未發送郵件或外部業務請求。測試服務已於完成後停止。

| 測試範圍 | 結果 |
| --- | --- |
| 優惠直接測試，含新增每十杯情境 | 236 項通過 |
| 購物車、下單、桌面／手機後台、團購與遊戲入口 HTTP | 135 項通過 |
| 歸屬、已截止報價及活動狀態邊界 | 56 項通過 |
| 雙程序同時發放、錢包認領及獎勵使用 | 23 項通過 |
| 歷史資料與報價相容性 | 41 項通過 |
| 小遊戲資格、遊戲保存、領獎、同時操作與故障回復 | 106 項通過 |
| 文件、版本、內容安全及 28 張流程圖 | 3,170 項通過 |
| 文件頁與日誌下載隔離 HTTP | 158 項通過 |

合計 597 項業務及 3,328 項文件檢查。PHP 語法與 ZIP 完整性於打包時另行記錄；上述結果不代表實站驗收。

已核對情境：

- 9 杯 0 次、10／19 杯 1 次、20／29 杯 2 次、30 杯 3 次；兩張各 9 杯訂單不合併，單買配料不計入。
- 新購物車與團購報價按每滿十杯保存次數，同單多品項／多團員合計。移除團員後 25 杯降為 19 杯，2 次降為 1；30 杯降為 20 杯，3 次降為 2。
- 桌面後台完成 20 杯訂單發 2 次，手機後台完成 30 杯發 3 次；重複完成不加發。兩個程序同時完成一張 20 杯訂單，總共只入帳 2 次。
- POST 偽填 99 次無效，依伺服器計算；其他優惠不疊加，未完成不發放，交易失敗不留下部分入帳。
- 20 杯團購經測試站端點送單保存 2 次並發給團主；維持錢包與會員歸屬。
- 中越英等待／可玩訊息顯示正確次數，發放後完成頁自動進入遊戲，查詢狀態不額外發放。
- 舊 20 杯已發 1 次不補發；已成立或已截止且保存 1 次者維持 1 次。舊團購重新開放並截止 30 杯，報價改為 3 次。錯誤或超過合格杯數的次數不發放。
- 打包檢查保留 19.9.1–19.12.0 所有文件快照與 19.12.0 更新包；新版仍有每語言 14 張流程圖，下載內容與 Markdown 一致。

測試程式：`tests/promotions_integration.php` 搭配 `tests/per_ten_draw.php`、`tests/minigame_integration.php`、`tests/developer_page_test.php`、`tests/developer_page_http_test.py`。環境限受保護命名的測試資料庫及 localhost；更新包不包含測試初始化頁、session 或設定。

未完成：shop 部署與實站驗收、order 更新、真實付款與通知。這次未重做 19.12.0 的桌面／手機瀏覽器視覺驗收；圖表顯示程式未改，新內容已經過渲染器與 HTTP 檢查。後续仍先使用 shop 獨立資料庫验收，再安排 order。
