<?php

/**
 * Admin Panel Multilingual (i18n) Service.
 * Provides unified, synchronized translations for Vietnamese (vi), Traditional Chinese (zh), and English (en).
 */

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

function admin_i18n_dictionary(): array
{
    return [
        'vi' => [
            // Module Title & Navigation
            'transactions' => 'Giao dịch & Thanh toán',
            'transactions_title' => 'Quản lý Giao dịch & Thanh toán',
            'transactions_subtitle' => 'Bảng điều khiển theo dõi, đối soát dòng tiền thanh toán cá nhân và nhóm qua chuyển khoản và tiền mặt.',
            'nav_transactions' => 'Giao dịch',

            // Metrics Cards
            'metric_total_received' => 'Tổng tiền đã nhận',
            'metric_completed_tx' => 'giao dịch hoàn tất',
            'metric_transfer_received' => 'Chuyển khoản (Đã nhận)',
            'metric_transfer_tx' => 'giao dịch chuyển khoản',
            'metric_cash_received' => 'Tiền mặt (Đã nhận)',
            'metric_cash_tx' => 'giao dịch tiền mặt',
            'metric_cancelled' => 'Đã hủy',
            'metric_cancelled_tx' => 'đã hủy',

            // Filter Labels & Options
            'search' => 'Tìm kiếm',
            'filter_search_placeholder' => 'Mã đơn (HYG...), Mã tham chiếu, Tên khách, SĐT...',
            'filter_tx_type' => 'Loại giao dịch',
            'filter_tx_type_all' => 'Tất cả loại',
            'filter_tx_type_personal' => 'Cá nhân',
            'filter_tx_type_group' => 'Nhóm',
            'filter_channel' => 'Kênh thanh toán',
            'filter_channel_all' => 'Tất cả kênh',
            'filter_channel_transfer' => 'Chuyển khoản',
            'filter_channel_cash' => 'Tiền mặt',
            'filter_status' => 'Trạng thái ghi nhận',
            'filter_status_all' => 'Tất cả trạng thái',
            'filter_status_received' => 'Đã nhận',
            'filter_status_cancelled' => 'Đã hủy',
            'filter_store' => 'Cửa hàng / Shop',
            'filter_store_all' => 'Tất cả cửa hàng / shop',
            'store_hq' => 'Trụ sở chính',
            'date_from' => 'Từ ngày',
            'date_to' => 'Đến ngày',
            'sort_by' => 'Sắp xếp theo',
            'sort_time_desc' => 'Thời gian: Mới nhất',
            'sort_time_asc' => 'Thời gian: Cũ nhất',
            'sort_amount_desc' => 'Số tiền: Cao nhất',
            'sort_amount_asc' => 'Số tiền: Thấp nhất',
            'per_page' => 'Hiển thị',
            'per_page_format' => '{n} / trang',
            'btn_filter' => 'Lọc dữ liệu',
            'btn_reset' => 'Đặt lại',

            // Table Headers & Badges
            'th_order_code' => 'Mã đơn / Mã GD',
            'th_type' => 'Phân loại',
            'th_channel' => 'Kênh thanh toán',
            'th_amount' => 'Số tiền',
            'th_customer' => 'Khách hàng / Nhóm',
            'th_store' => 'Cửa hàng / Shop',
            'th_time' => 'Thời gian',
            'th_reference' => 'Mã tham chiếu',
            'th_actions' => 'Thao tác',
            'badge_personal' => 'Cá nhân',
            'badge_group' => 'Nhóm',
            'badge_transfer' => 'Chuyển khoản',
            'badge_cash' => 'Tiền mặt',
            'badge_received' => 'Đã nhận',
            'badge_cancelled' => 'Đã hủy',
            'btn_view_detail' => 'Xem chi tiết',
            'no_data' => 'Không có dữ liệu phù hợp với bộ lọc.',
            'page_showing' => 'Hiển thị {from} - {to} trên tổng số {total} giao dịch',
            'page_prev' => 'Trang trước',
            'page_next' => 'Trang sau',

            // Modal & Detail
            'modal_tx_detail' => 'Chi tiết Giao dịch #{id}',
            'modal_time_label' => 'Thời gian: {time}',
            'section_payment_info' => 'Thông tin Thanh toán',
            'section_party_account' => 'Đối tượng & Tài khoản',
            'section_audit_note' => 'Nội dung đối soát / Ghi chú',
            'label_amount' => 'Số tiền:',
            'label_tx_type' => 'Loại giao dịch:',
            'label_channel' => 'Kênh thanh toán:',
            'label_status' => 'Trạng thái:',
            'label_reference' => 'Mã tham chiếu:',
            'label_customer' => 'Khách / Thành viên:',
            'label_phone' => 'Số điện thoại:',
            'label_room' => 'Phòng nhóm:',
            'label_store' => 'Shop / Cửa hàng:',
            'label_bank_received' => 'Ngân hàng nhận:',
            'label_account_number' => 'STK nhận:',
            'label_account_name' => 'Chủ tài khoản:',
            'label_cash_collection' => 'Thu tiền mặt trực tiếp',
            'label_no_note' => 'Không có ghi chú đối soát.',
            'btn_open_order' => 'Mở chi tiết đơn hàng #{id} →',
            'btn_export_csv' => 'Xuất Excel / CSV',
            'modal_close' => 'Đóng',
            'copied' => 'Đã chép!',
            'copy_code' => 'Copy mã',
        ],

        'zh' => [
            // Module Title & Navigation
            'transactions' => '交易與收款',
            'transactions_title' => '交易與收款管理',
            'transactions_subtitle' => '個人與團購訂單轉帳及現金收款對帳與金流追蹤儀表板。',
            'nav_transactions' => '交易',

            // Metrics Cards
            'metric_total_received' => '累計已收款',
            'metric_completed_tx' => '筆交易完成',
            'metric_transfer_received' => '轉帳收款（已確認）',
            'metric_transfer_tx' => '筆轉帳交易',
            'metric_cash_received' => '現金收款（已確認）',
            'metric_cash_tx' => '筆現金交易',
            'metric_cancelled' => '已取消',
            'metric_cancelled_tx' => '筆已取消',

            // Filter Labels & Options
            'search' => '搜尋',
            'filter_search_placeholder' => '單號（HYG...）、參考號、顧客姓名、電話...',
            'filter_tx_type' => '交易類型',
            'filter_tx_type_all' => '全部類型',
            'filter_tx_type_personal' => '個人訂單',
            'filter_tx_type_group' => '團購訂單',
            'filter_channel' => '付款管道',
            'filter_channel_all' => '全部管道',
            'filter_channel_transfer' => '銀行轉帳',
            'filter_channel_cash' => '現金',
            'filter_status' => '入帳狀態',
            'filter_status_all' => '全部狀態',
            'filter_status_received' => '已入帳',
            'filter_status_cancelled' => '已取消',
            'filter_store' => '門市 / 分店',
            'filter_store_all' => '全部門市 / 分店',
            'store_hq' => '總部門市',
            'date_from' => '開始日期',
            'date_to' => '結束日期',
            'sort_by' => '排序方式',
            'sort_time_desc' => '時間：由新到舊',
            'sort_time_asc' => '時間：由舊到新',
            'sort_amount_desc' => '金額：由高到低',
            'sort_amount_asc' => '金額：由低到高',
            'per_page' => '每頁筆數',
            'per_page_format' => '{n} 筆/頁',
            'btn_filter' => '篩選資料',
            'btn_reset' => '重設',

            // Table Headers & Badges
            'th_order_code' => '單號 / 交易流水',
            'th_type' => '類型',
            'th_channel' => '付款管道',
            'th_amount' => '金額',
            'th_customer' => '顧客 / 團購室',
            'th_store' => '承接門市',
            'th_time' => '時間',
            'th_reference' => '交易參考號',
            'th_actions' => '操作',
            'badge_personal' => '個人',
            'badge_group' => '團購',
            'badge_transfer' => '轉帳',
            'badge_cash' => '現金',
            'badge_received' => '已入帳',
            'badge_cancelled' => '已取消',
            'btn_view_detail' => '檢視詳情',
            'no_data' => '沒有符合篩選條件的交易紀錄。',
            'page_showing' => '顯示第 {from} 至 {to} 筆，共 {total} 筆紀錄',
            'page_prev' => '上一頁',
            'page_next' => '下一頁',

            // Modal & Detail
            'modal_tx_detail' => '交易詳情 #{id}',
            'modal_time_label' => '記錄時間：{time}',
            'section_payment_info' => '付款明細',
            'section_party_account' => '對象與帳戶',
            'section_audit_note' => '對帳備註 / 說明',
            'label_amount' => '金額：',
            'label_tx_type' => '交易類型：',
            'label_channel' => '付款管道：',
            'label_status' => '入帳狀態：',
            'label_reference' => '參考號碼：',
            'label_customer' => '顧客 / 成員：',
            'label_phone' => '聯絡電話：',
            'label_room' => '團購房間：',
            'label_store' => '承接門市：',
            'label_bank_received' => '收款銀行：',
            'label_account_number' => '收款帳號：',
            'label_account_name' => '戶名：',
            'label_cash_collection' => '現場收取現金',
            'label_no_note' => '無對帳備註。',
            'btn_open_order' => '開啟訂單明細 #{id} →',
            'btn_export_csv' => '匯出 Excel / CSV',
            'modal_close' => '關閉',
            'copied' => '已複製！',
            'copy_code' => '複製號碼',
        ],

        'en' => [
            // Module Title & Navigation
            'transactions' => 'Transactions & Payments',
            'transactions_title' => 'Transaction & Payment Management',
            'transactions_subtitle' => 'Dashboard for tracking and reconciling personal & group payments via bank transfer and cash.',
            'nav_transactions' => 'Transactions',

            // Metrics Cards
            'metric_total_received' => 'Total Received',
            'metric_completed_tx' => 'completed transactions',
            'metric_transfer_received' => 'Transfer (Received)',
            'metric_transfer_tx' => 'transfer transactions',
            'metric_cash_received' => 'Cash (Received)',
            'metric_cash_tx' => 'cash transactions',
            'metric_cancelled' => 'Cancelled',
            'metric_cancelled_tx' => 'cancelled transactions',

            // Filter Labels & Options
            'search' => 'Search',
            'filter_search_placeholder' => 'Order code (HYG...), Reference, Customer, Phone...',
            'filter_tx_type' => 'Transaction Type',
            'filter_tx_type_all' => 'All Types',
            'filter_tx_type_personal' => 'Personal',
            'filter_tx_type_group' => 'Group',
            'filter_channel' => 'Payment Channel',
            'filter_channel_all' => 'All Channels',
            'filter_channel_transfer' => 'Bank Transfer',
            'filter_channel_cash' => 'Cash',
            'filter_status' => 'Record Status',
            'filter_status_all' => 'All Statuses',
            'filter_status_received' => 'Received',
            'filter_status_cancelled' => 'Cancelled',
            'filter_store' => 'Store / Branch',
            'filter_store_all' => 'All Stores / Branches',
            'store_hq' => 'Headquarters',
            'date_from' => 'From date',
            'date_to' => 'To date',
            'sort_by' => 'Sort by',
            'sort_time_desc' => 'Time: Newest first',
            'sort_time_asc' => 'Time: Oldest first',
            'sort_amount_desc' => 'Amount: High to low',
            'sort_amount_asc' => 'Amount: Low to high',
            'per_page' => 'Per page',
            'per_page_format' => '{n} / page',
            'btn_filter' => 'Filter Data',
            'btn_reset' => 'Reset',

            // Table Headers & Badges
            'th_order_code' => 'Order / Tx Code',
            'th_type' => 'Type',
            'th_channel' => 'Channel',
            'th_amount' => 'Amount',
            'th_customer' => 'Customer / Group',
            'th_store' => 'Store / Branch',
            'th_time' => 'Time',
            'th_reference' => 'Reference Code',
            'th_actions' => 'Actions',
            'badge_personal' => 'Personal',
            'badge_group' => 'Group',
            'badge_transfer' => 'Transfer',
            'badge_cash' => 'Cash',
            'badge_received' => 'Received',
            'badge_cancelled' => 'Cancelled',
            'btn_view_detail' => 'View Details',
            'no_data' => 'No matching transactions found.',
            'page_showing' => 'Showing {from} to {to} of {total} records',
            'page_prev' => 'Previous',
            'page_next' => 'Next',

            // Modal & Detail
            'modal_tx_detail' => 'Transaction Details #{id}',
            'modal_time_label' => 'Time: {time}',
            'section_payment_info' => 'Payment Information',
            'section_party_account' => 'Customer & Account',
            'section_audit_note' => 'Reconciliation Notes',
            'label_amount' => 'Amount:',
            'label_tx_type' => 'Transaction type:',
            'label_channel' => 'Payment channel:',
            'label_status' => 'Status:',
            'label_reference' => 'Reference:',
            'label_customer' => 'Customer / Member:',
            'label_phone' => 'Phone:',
            'label_room' => 'Group room:',
            'label_store' => 'Store / Branch:',
            'label_bank_received' => 'Receiving bank:',
            'label_account_number' => 'Account number:',
            'label_account_name' => 'Account name:',
            'label_cash_collection' => 'Direct cash collection',
            'label_no_note' => 'No reconciliation notes.',
            'btn_open_order' => 'Open Order Details #{id} →',
            'btn_export_csv' => 'Export Excel / CSV',
            'modal_close' => 'Close',
            'copied' => 'Copied!',
            'copy_code' => 'Copy code',
        ],
    ];
}

/**
 * Translate an admin panel string key.
 */
function adm_t(string $key, array $values = [], ?string $lang = null): string
{
    $lang = $lang ?? (function_exists('current_lang') ? current_lang() : 'vi');
    $dict = admin_i18n_dictionary();
    $text = $dict[$lang][$key] ?? $dict['vi'][$key] ?? $key;

    foreach ($values as $name => $value) {
        $text = str_replace('{' . $name . '}', (string)$value, $text);
    }
    return $text;
}

/**
 * Alias for adm_t()
 */
function admin_t(string $key, array $values = [], ?string $lang = null): string
{
    return adm_t($key, $values, $lang);
}
