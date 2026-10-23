<?php
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/order_workflow.php';
require_once __DIR__ . '/../lib/admin_datatable.php';
require_admin();

header('Cache-Control: private, no-store');

// -------------------------------------------------------------
// FILTER & SEARCH PARAMETERS
// -------------------------------------------------------------
$search = trim((string) ($_GET['q'] ?? ''));
$filterType = trim((string) ($_GET['type'] ?? 'all'));       // all | personal | group
$filterMethod = trim((string) ($_GET['method'] ?? 'all'));   // all | transfer | cash
$filterStatus = trim((string) ($_GET['status'] ?? 'all'));   // all | received | cancelled
$filterStore = trim((string) ($_GET['store'] ?? 'all'));     // all | hq | {store_id}
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$sort = trim((string) ($_GET['sort'] ?? 'date_desc'));

// Load active dispatch stores for filter dropdown
$allStores = hyg_all("SELECT id, name, phone FROM dispatch_stores WHERE enabled = 1 ORDER BY name ASC");
$storeFilterOptions = [
    'all' => adm_t('filter_store_all'),
    'hq' => adm_t('store_hq'),
];
foreach ($allStores as $s) {
    $storeFilterOptions[(string)$s['id']] = 'Shop ' . $s['name'] . ($s['phone'] ? ' (' . $s['phone'] . ')' : '');
}

// Timezone offset from client machine (in minutes, negative for GMT+). Defaults to Vietnam (UTC+7 = -420)
$tzOffset = isset($_GET['tz_offset']) && is_numeric($_GET['tz_offset']) ? (int)$_GET['tz_offset'] : -420;
$sign = $tzOffset <= 0 ? '+' : '-';
$absMinutes = abs($tzOffset);
$hours = str_pad((string)intdiv($absMinutes, 60), 2, '0', STR_PAD_LEFT);
$mins = str_pad((string)($absMinutes % 60), 2, '0', STR_PAD_LEFT);
$tzString = $sign . $hours . ':' . $mins;

try {
    $clientTz = new DateTimeZone($tzString);
} catch (Throwable) {
    $clientTz = new DateTimeZone('+07:00');
}

// Helper date converter to UTC for database queries
$dateFromUtc = '';
$dateToUtc = '';
if ($dateFrom !== '') {
    try {
        $dt = new DateTimeImmutable($dateFrom . ' 00:00:00', $clientTz);
        $dateFromUtc = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    } catch (Throwable) {
    }
}
if ($dateTo !== '') {
    try {
        $dt = new DateTimeImmutable($dateTo . ' 23:59:59', $clientTz);
        $dateToUtc = $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    } catch (Throwable) {
    }
}

// -------------------------------------------------------------
// BASE UNION QUERY: (Transfer Orders + Cash Orders + Group Payments)
// Notification ready logic: Only include confirmed/ready orders
// -------------------------------------------------------------
$baseUnionSql = "
SELECT 
    e.id AS raw_id,
    (CASE WHEN o.group_room_id IS NOT NULL AND o.group_room_id > 0 THEN 'group' ELSE 'personal' END) COLLATE utf8mb4_unicode_ci AS tx_type,
    'transfer' COLLATE utf8mb4_unicode_ci AS payment_method,
    (CASE WHEN e.event_key LIKE 'sepay:%' THEN 'sepay' ELSE 'transfer' END) COLLATE utf8mb4_unicode_ci AS source_channel,
    (CASE WHEN o.status = 'cancelled' OR e.event_type IN ('cancelled', 'refund') THEN 'cancelled' ELSE 'received' END) COLLATE utf8mb4_unicode_ci AS unified_status,
    COALESCE(o.dispatch_store_id, 0) AS store_id,
    COALESCE(NULLIF(o.dispatch_store_name, ''), 'Trụ sở') COLLATE utf8mb4_unicode_ci AS store_name,
    e.id AS event_id,
    e.order_id AS target_id,
    o.order_no COLLATE utf8mb4_unicode_ci AS code,
    COALESCE(o.customer_name, 'Khách vãng lai') COLLATE utf8mb4_unicode_ci AS party_name,
    COALESCE(o.phone, '') COLLATE utf8mb4_unicode_ci AS party_phone,
    e.event_key COLLATE utf8mb4_unicode_ci AS event_key,
    e.event_type COLLATE utf8mb4_unicode_ci AS event_type,
    e.amount_delta AS amount,
    e.refund_delta AS refund_delta,
    e.account_code COLLATE utf8mb4_unicode_ci AS account_code,
    e.recipient_json COLLATE utf8mb4_unicode_ci AS recipient_json,
    e.transaction_reference COLLATE utf8mb4_unicode_ci AS transaction_reference,
    e.reason COLLATE utf8mb4_unicode_ci AS reason,
    e.created_at,
    o.status COLLATE utf8mb4_unicode_ci AS target_status,
    o.total_amount AS target_total,
    e.before_json COLLATE utf8mb4_unicode_ci AS before_json,
    e.after_json COLLATE utf8mb4_unicode_ci AS after_json
FROM transfer_payment_events e
JOIN orders o ON o.id = e.order_id
LEFT JOIN transfer_order_receipts r ON r.order_id = o.id
WHERE (r.status IN ('confirmed', 'overpaid') OR r.received_amount >= r.amount)
  AND (e.amount_delta > 0 OR e.event_type IN ('receive', 'confirmed', 'refund'))

UNION ALL

SELECT 
    o.id AS raw_id,
    (CASE WHEN o.group_room_id IS NOT NULL AND o.group_room_id > 0 THEN 'group' ELSE 'personal' END) COLLATE utf8mb4_unicode_ci AS tx_type,
    'cash' COLLATE utf8mb4_unicode_ci AS payment_method,
    'cash' COLLATE utf8mb4_unicode_ci AS source_channel,
    (CASE WHEN o.status = 'cancelled' THEN 'cancelled' ELSE 'received' END) COLLATE utf8mb4_unicode_ci AS unified_status,
    COALESCE(o.dispatch_store_id, 0) AS store_id,
    COALESCE(NULLIF(o.dispatch_store_name, ''), 'Trụ sở') COLLATE utf8mb4_unicode_ci AS store_name,
    o.id AS event_id,
    o.id AS target_id,
    o.order_no COLLATE utf8mb4_unicode_ci AS code,
    COALESCE(o.customer_name, 'Khách vãng lai') COLLATE utf8mb4_unicode_ci AS party_name,
    COALESCE(o.phone, '') COLLATE utf8mb4_unicode_ci AS party_phone,
    CONCAT('cash:', o.id) COLLATE utf8mb4_unicode_ci AS event_key,
    (CASE WHEN o.status = 'cancelled' THEN 'cancelled' ELSE 'receive' END) COLLATE utf8mb4_unicode_ci AS event_type,
    o.total_amount AS amount,
    (CASE WHEN o.status = 'cancelled' THEN o.total_amount ELSE 0.00 END) AS refund_delta,
    'cash' COLLATE utf8mb4_unicode_ci AS account_code,
    '{}' COLLATE utf8mb4_unicode_ci AS recipient_json,
    'CASH' COLLATE utf8mb4_unicode_ci AS transaction_reference,
    CONCAT('Thanh toán tiền mặt - Đơn ', o.status) COLLATE utf8mb4_unicode_ci AS reason,
    o.created_at,
    o.status COLLATE utf8mb4_unicode_ci AS target_status,
    o.total_amount AS target_total,
    '{}' COLLATE utf8mb4_unicode_ci AS before_json,
    '{}' COLLATE utf8mb4_unicode_ci AS after_json
FROM orders o
WHERE o.payment_method = 'cash'

UNION ALL

SELECT 
    g.id AS raw_id,
    'group' COLLATE utf8mb4_unicode_ci AS tx_type,
    (CASE WHEN m.payment_method = 'cash' THEN 'cash' ELSE 'transfer' END) COLLATE utf8mb4_unicode_ci AS payment_method,
    (CASE WHEN (g.seed_key LIKE 'sepay:%' OR g.reason LIKE '%SePay%') THEN 'sepay' 
          WHEN m.payment_method = 'cash' THEN 'cash' 
          ELSE 'transfer' END) COLLATE utf8mb4_unicode_ci AS source_channel,
    (CASE WHEN g.event_type IN ('cancelled', 'refund', 'invalidated') OR m.payment_status = 'cancelled' THEN 'cancelled' ELSE 'received' END) COLLATE utf8mb4_unicode_ci AS unified_status,
    COALESCE(ro.dispatch_store_id, 0) AS store_id,
    COALESCE(NULLIF(ro.dispatch_store_name, ''), 'Trụ sở') COLLATE utf8mb4_unicode_ci AS store_name,
    g.id AS event_id,
    g.room_id AS target_id,
    COALESCE(g.reference, CONCAT('ROOM#', g.room_id)) COLLATE utf8mb4_unicode_ci AS code,
    COALESCE(g.member_name, 'Thành viên') COLLATE utf8mb4_unicode_ci AS party_name,
    CONCAT('Phòng: ', COALESCE(r.title, 'Đơn nhóm')) COLLATE utf8mb4_unicode_ci AS party_phone,
    COALESCE(g.seed_key, CONCAT('grp:', g.id)) COLLATE utf8mb4_unicode_ci AS event_key,
    g.event_type COLLATE utf8mb4_unicode_ci AS event_type,
    g.amount,
    0.00 AS refund_delta,
    'group_vault' COLLATE utf8mb4_unicode_ci AS account_code,
    '{}' COLLATE utf8mb4_unicode_ci AS recipient_json,
    g.reference COLLATE utf8mb4_unicode_ci AS transaction_reference,
    g.reason COLLATE utf8mb4_unicode_ci AS reason,
    g.created_at,
    r.status COLLATE utf8mb4_unicode_ci AS target_status,
    g.amount AS target_total,
    g.snapshot_json COLLATE utf8mb4_unicode_ci AS before_json,
    g.snapshot_json COLLATE utf8mb4_unicode_ci AS after_json
FROM group_payment_events g
LEFT JOIN group_rooms r ON r.id = g.room_id
LEFT JOIN group_members m ON m.id = g.member_id
LEFT JOIN orders ro ON ro.id = r.order_id
WHERE g.amount > 0 
  AND g.event_type IN ('confirmed', 'member_paid', 'paid')
  AND (m.payment_status IS NULL OR m.payment_status = 'paid')
";

// -------------------------------------------------------------
// DYNAMIC FILTER CLAUSES (Applied directly to unified table)
// -------------------------------------------------------------
$where = ['1=1'];
$params = [];

if ($filterType !== 'all' && in_array($filterType, ['personal', 'group'], true)) {
    $where[] = "u.tx_type = ?";
    $params[] = $filterType;
}

if ($filterMethod !== 'all' && in_array($filterMethod, ['transfer', 'cash'], true)) {
    $where[] = "u.payment_method = ?";
    $params[] = $filterMethod;
}

if ($filterStatus !== 'all' && in_array($filterStatus, ['received', 'cancelled'], true)) {
    $where[] = "u.unified_status = ?";
    $params[] = $filterStatus;
}

if ($filterStore === 'hq') {
    $where[] = "u.store_id = 0";
} elseif ($filterStore !== 'all' && is_numeric($filterStore)) {
    $where[] = "u.store_id = ?";
    $params[] = (int) $filterStore;
}

if ($dateFromUtc !== '') {
    $where[] = "u.created_at >= ?";
    $params[] = $dateFromUtc;
}
if ($dateToUtc !== '') {
    $where[] = "u.created_at <= ?";
    $params[] = $dateToUtc;
}

if ($search !== '') {
    $where[] = "(u.code LIKE ? OR u.party_name LIKE ? OR u.party_phone LIKE ? OR u.transaction_reference LIKE ? OR u.reason LIKE ? OR u.event_key LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}

$finalUnionSql = "SELECT * FROM ($baseUnionSql) AS u WHERE " . implode(' AND ', $where);

// Sorting
$orderByClause = match ($sort) {
    'date_asc' => 'ORDER BY created_at ASC, raw_id ASC',
    'amount_desc' => 'ORDER BY amount DESC, created_at DESC',
    'amount_asc' => 'ORDER BY amount ASC, created_at DESC',
    default => 'ORDER BY created_at DESC, raw_id DESC',
};

// -------------------------------------------------------------
// KPI STATS CARDS CALCULATION
// -------------------------------------------------------------
$statsSql = "
SELECT 
    COALESCE(SUM(CASE WHEN u.unified_status = 'received' THEN u.amount ELSE 0 END), 0) AS total_received,
    COUNT(CASE WHEN u.unified_status = 'received' THEN 1 END) AS count_received,
    COALESCE(SUM(CASE WHEN u.unified_status = 'received' AND u.payment_method = 'transfer' THEN u.amount ELSE 0 END), 0) AS transfer_received,
    COUNT(CASE WHEN u.unified_status = 'received' AND u.payment_method = 'transfer' THEN 1 END) AS count_transfer,
    COALESCE(SUM(CASE WHEN u.unified_status = 'received' AND u.payment_method = 'cash' THEN u.amount ELSE 0 END), 0) AS cash_received,
    COUNT(CASE WHEN u.unified_status = 'received' AND u.payment_method = 'cash' THEN 1 END) AS count_cash,
    COUNT(CASE WHEN u.unified_status = 'cancelled' THEN 1 END) AS count_cancelled,
    COALESCE(SUM(CASE WHEN u.unified_status = 'cancelled' THEN u.amount ELSE 0 END), 0) AS total_cancelled
FROM ($baseUnionSql) AS u
";
$stats = hyg_one($statsSql) ?: [];

$metricTotalReceived = (float) ($stats['total_received'] ?? 0);
$metricCountReceived = (int) ($stats['count_received'] ?? 0);
$metricTransferReceived = (float) ($stats['transfer_received'] ?? 0);
$metricCountTransfer = (int) ($stats['count_transfer'] ?? 0);
$metricCashReceived = (float) ($stats['cash_received'] ?? 0);
$metricCountCash = (int) ($stats['count_cash'] ?? 0);
$metricCountCancelled = (int) ($stats['count_cancelled'] ?? 0);
$metricTotalCancelled = (float) ($stats['total_cancelled'] ?? 0);

// Helper function to format time using client timezone
if (!function_exists('sepay_format_vn_time')) {
    function sepay_format_vn_time(?string $utcTime, ?DateTimeZone $tz = null): string
    {
        if (!$utcTime) return '-';
        try {
            $targetTz = $tz ?: new DateTimeZone('+07:00');
            return (new DateTimeImmutable($utcTime, new DateTimeZone('UTC')))
                ->setTimezone($targetTz)
                ->format('d/m/Y H:i:s');
        } catch (Throwable) {
            return $utcTime;
        }
    }
}

// -------------------------------------------------------------
// INSTANTIATE REUSABLE AdminDataTable
// -------------------------------------------------------------
$dataTable = new AdminDataTable([
    'id' => 'sepay_tx_table',
    'title' => adm_t('transactions_title'),
    'hide_title' => true,
    'subtitle' => adm_t('transactions_subtitle'),
    'default_per_page' => 10,
    'metrics' => [
        [
            'label' => adm_t('metric_total_received'),
            'value' => money($metricTotalReceived) . 'đ',
            'sub' => number_format($metricCountReceived) . ' ' . adm_t('metric_completed_tx'),
            'highlight' => 'green',
            'color' => 'income',
            'icon' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>'
        ],
        [
            'label' => adm_t('metric_transfer_received'),
            'value' => money($metricTransferReceived) . 'đ',
            'sub' => number_format($metricCountTransfer) . ' ' . adm_t('metric_transfer_tx'),
            'color' => 'sepay',
            'icon' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>'
        ],
        [
            'label' => adm_t('metric_cash_received'),
            'value' => money($metricCashReceived) . 'đ',
            'sub' => number_format($metricCountCash) . ' ' . adm_t('metric_cash_tx'),
            'color' => 'today',
            'icon' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="8"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>'
        ],
        [
            'label' => adm_t('metric_cancelled'),
            'value' => number_format($metricCountCancelled) . ' ' . adm_t('metric_cancelled_tx'),
            'sub' => money($metricTotalCancelled) . 'đ ' . adm_t('metric_cancelled_tx'),
            'color' => 'neutral',
            'icon' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
        ]
    ],
    'query' => [
        'base_sql' => $finalUnionSql,
        'params' => $params,
        'order_by' => $orderByClause
    ],
    'filters' => [
        'search' => [
            'name' => 'q',
            'label' => adm_t('search'),
            'placeholder' => adm_t('filter_search_placeholder')
        ],
        'items' => [
            [
                'name' => 'type',
                'label' => adm_t('filter_tx_type'),
                'type' => 'select',
                'options' => [
                    'all' => adm_t('filter_tx_type_all'),
                    'personal' => adm_t('filter_tx_type_personal'),
                    'group' => adm_t('filter_tx_type_group')
                ]
            ],
            [
                'name' => 'method',
                'label' => adm_t('filter_channel'),
                'type' => 'select',
                'options' => [
                    'all' => adm_t('filter_channel_all'),
                    'transfer' => adm_t('filter_channel_transfer'),
                    'cash' => adm_t('filter_channel_cash')
                ]
            ],
            [
                'name' => 'status',
                'label' => adm_t('filter_status'),
                'type' => 'select',
                'options' => [
                    'all' => adm_t('filter_status_all'),
                    'received' => adm_t('filter_status_received'),
                    'cancelled' => adm_t('filter_status_cancelled')
                ]
            ],
            [
                'name' => 'store',
                'label' => adm_t('filter_store'),
                'type' => 'select',
                'options' => $storeFilterOptions
            ]
        ],
        'date_range' => [
            'from' => 'date_from',
            'to' => 'date_to',
            'from_label' => adm_t('date_from'),
            'to_label' => adm_t('date_to')
        ]
    ],
    'sort' => [
        'label' => adm_t('sort_by'),
        'default' => 'date_desc',
        'options' => [
            'date_desc' => adm_t('sort_time_desc'),
            'date_asc' => adm_t('sort_time_asc'),
            'amount_desc' => adm_t('sort_amount_desc'),
            'amount_asc' => adm_t('sort_amount_asc')
        ]
    ],
    'columns' => [
        [
            'key' => 'created_at',
            'label' => adm_t('th_time'),
            'width' => '140px',
            'render' => function ($tx) use ($clientTz) {
                return '<div style="display:flex;flex-direction:column;gap:2px;">
                    <strong class="sp-local-time" data-utc="' . e($tx['created_at']) . '">' . e(sepay_format_vn_time($tx['created_at'], $clientTz)) . '</strong>
                    <small class="muted">#ID-' . e($tx['event_id']) . '</small>
                </div>';
            },
            'export_render' => function ($tx) use ($clientTz) {
                return sepay_format_vn_time($tx['created_at'], $clientTz);
            }
        ],
        [
            'key' => 'tx_type',
            'label' => adm_t('th_type'),
            'width' => '120px',
            'render' => function ($tx) {
                if ($tx['tx_type'] === 'personal') {
                    return '<span class="sp-type-badge personal">' . e(adm_t('badge_personal')) . '</span>';
                }
                return '<span class="sp-type-badge group">' . e(adm_t('badge_group')) . '</span>';
            },
            'export_render' => function ($tx) {
                return $tx['tx_type'] === 'personal' ? adm_t('badge_personal') : adm_t('badge_group');
            }
        ],
        [
            'key' => 'payment_method',
            'label' => adm_t('th_channel'),
            'width' => '140px',
            'render' => function ($tx) {
                if ($tx['payment_method'] === 'transfer') {
                    return '<span class="sp-badge transfer-badge">' . e(adm_t('badge_transfer')) . '</span>';
                }
                return '<span class="sp-badge cash-badge">' . e(adm_t('badge_cash')) . '</span>';
            },
            'export_render' => function ($tx) {
                return $tx['payment_method'] === 'cash' ? adm_t('badge_cash') : adm_t('badge_transfer');
            }
        ],
        [
            'key' => 'store_name',
            'label' => adm_t('th_store'),
            'width' => '130px',
            'render' => function ($tx) {
                if ((int)$tx['store_id'] === 0) {
                    return '<span class="sp-store-badge hq">' . e(adm_t('store_hq')) . '</span>';
                }
                return '<span class="sp-store-badge branch">' . e($tx['store_name']) . '</span>';
            },
            'export_render' => function ($tx) {
                return $tx['store_name'];
            }
        ],
        [
            'key' => 'code',
            'label' => adm_t('th_order_code'),
            'width' => '180px',
            'render' => function ($tx) {
                if ($tx['tx_type'] === 'personal') {
                    return '<div style="display:flex;flex-direction:column;gap:2px;">
                        <a href="order.php?id=' . (int)$tx['target_id'] . '" target="_blank" style="color:#0284c7;font-weight:650;text-decoration:none;">
                            <strong>' . e($tx['code']) . '</strong>
                        </a>
                        <span class="sp-sub-tag order">#' . (int)$tx['target_id'] . '</span>
                    </div>';
                }
                return '<div style="display:flex;flex-direction:column;gap:2px;">
                    <strong>' . e($tx['code']) . '</strong>
                    <span class="sp-sub-tag group">' . e($tx['party_phone']) . '</span>
                </div>';
            },
            'export_render' => function ($tx) {
                return $tx['code'] . ($tx['tx_type'] === 'personal' ? ' (#' . (int)$tx['target_id'] . ')' : '');
            }
        ],
        [
            'key' => 'party_name',
            'label' => adm_t('th_customer'),
            'render' => function ($tx) {
                $phone = ($tx['tx_type'] === 'personal' && $tx['party_phone']) ? '<small class="muted">' . e($tx['party_phone']) . '</small>' : '';
                return '<div style="display:flex;flex-direction:column;gap:2px;">
                    <strong>' . e($tx['party_name']) . '</strong>
                    ' . $phone . '
                </div>';
            },
            'export_render' => function ($tx) {
                return $tx['party_name'] . ($tx['party_phone'] ? ' (' . $tx['party_phone'] . ')' : '');
            }
        ],
        [
            'key' => 'amount',
            'label' => adm_t('th_amount'),
            'align' => 'right',
            'width' => '130px',
            'render' => function ($tx) {
                $isCancelled = ($tx['unified_status'] === 'cancelled');
                $amt = (float) $tx['amount'];
                if ($isCancelled) {
                    return '<strong style="color:#dc2626;font-variant-numeric:tabular-nums;">' . e(money($amt)) . 'đ</strong>';
                }
                return '<strong style="color:#16a34a;font-variant-numeric:tabular-nums;">+' . e(money($amt)) . 'đ</strong>';
            },
            'export_render' => function ($tx) {
                return (float) $tx['amount'];
            }
        ],
        [
            'key' => 'unified_status',
            'label' => adm_t('filter_status'),
            'width' => '110px',
            'render' => function ($tx) {
                if ($tx['unified_status'] === 'received') {
                    return '<span class="sp-status-badge status-success">' . e(adm_t('badge_received')) . '</span>';
                }
                return '<span class="sp-status-badge status-warning">' . e(adm_t('badge_cancelled')) . '</span>';
            },
            'export_render' => function ($tx) {
                return $tx['unified_status'] === 'received' ? adm_t('badge_received') : adm_t('badge_cancelled');
            }
        ],
        [
            'key' => 'transaction_reference',
            'label' => adm_t('th_reference'),
            'width' => '150px',
            'render' => function ($tx) {
                if (!$tx['transaction_reference'] || $tx['transaction_reference'] === 'CASH') {
                    return '<span class="muted">-</span>';
                }
                return '<div style="display:flex;align-items:center;gap:6px;">
                    <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:0.8rem;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' . e($tx['transaction_reference']) . '">' . e($tx['transaction_reference']) . '</code>
                    <button type="button" class="sp-btn-copy" onclick="copyText(\'' . e($tx['transaction_reference']) . '\', this)" title="' . e(adm_t('copy_code')) . '">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    </button>
                </div>';
            },
            'export_render' => function ($tx) {
                return (!$tx['transaction_reference'] || $tx['transaction_reference'] === 'CASH') ? '-' : $tx['transaction_reference'];
            }
        ]
    ],
    'detail_modal' => [
        'enabled' => true,
        'title' => adm_t('transactions'),
        'button_label' => adm_t('btn_view_detail'),
        'render_js' => 'renderSepayDetailModal'
    ],
    'export' => [
        'enabled' => true,
        'label' => adm_t('btn_export_csv'),
        'filename' => 'giao-dich-thanh-toan'
    ]
]);

$dataTable->handleRequest();

admin_header($dataTable->getTitle());
?>

<!-- RENDER REUSABLE DATATABLE COMPONENT -->
<?php $dataTable->render(); ?>

<!-- ============================================================== -->
<!-- COMPONENT EXTENSION STYLES & MODAL HANDLER                     -->
<!-- ============================================================== -->
<style>
    /* Type Badges */
    .sp-type-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.775rem;
        font-weight: 650;
        white-space: nowrap;
    }

    .sp-type-badge.personal {
        background: #e0f2fe;
        color: #0369a1;
        border: 1px solid #bae6fd;
    }

    .sp-type-badge.group {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fde68a;
    }

    /* Payment Method Badges - strictly 2 colors */
    .sp-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.75rem;
        font-weight: 650;
        padding: 3px 8px;
        border-radius: 6px;
        white-space: nowrap;
    }

    .transfer-badge {
        background: #eff6ff;
        color: #1d4ed8;
        border: 1px solid #bfdbfe;
    }

    .cash-badge {
        background: #fff7ed;
        color: #c2410c;
        border: 1px solid #ffedd5;
    }

    /* Store Badges */
    .sp-store-badge {
        display: inline-block;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 2px 7px;
        border-radius: 5px;
        white-space: nowrap;
    }

    .sp-store-badge.hq {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .sp-store-badge.branch {
        background: #f0fdf4;
        color: #15803d;
        border: 1px solid #dcfce7;
    }

    /* Order / Group Sub tags */
    .sp-sub-tag {
        font-size: 0.725rem;
        color: #64748b;
    }

    .sp-sub-tag.order {
        color: #0284c7;
        font-weight: 550;
    }

    .sp-sub-tag.group {
        color: #d97706;
        font-weight: 550;
    }

    /* Status Badges */
    .sp-status-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .sp-status-badge.status-success {
        background: #dcfce7;
        color: #15803d;
    }

    .sp-status-badge.status-warning {
        background: #fee2e2;
        color: #991b1b;
    }

    /* Copy button */
    .sp-btn-copy {
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        cursor: pointer;
        padding: 3px 6px;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s ease;
    }

    .sp-btn-copy:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    .sp-btn-copy.copied {
        background: #10b981;
        border-color: #10b981;
        color: #ffffff;
    }

    /* Detail modal styles */
    .sp-detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .sp-detail-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px 16px;
    }

    .sp-detail-card-title {
        font-size: 0.775rem;
        text-transform: uppercase;
        font-weight: 700;
        color: #64748b;
        margin-bottom: 10px;
        letter-spacing: 0.04em;
    }

    .sp-detail-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px 0;
        border-bottom: 1px solid #edf2f7;
        font-size: 0.875rem;
    }

    .sp-detail-item:last-child {
        border-bottom: none;
    }

    .sp-detail-label {
        color: #64748b;
    }

    .sp-detail-val {
        font-weight: 600;
        color: #1e293b;
        text-align: right;
    }

    .sp-full-card {
        grid-column: 1 / -1;
    }
</style>

<script>
    window.__ADM_I18N__ = <?= json_encode([
                                'modal_title' => adm_t('modal_tx_detail', ['id' => '']),
                                'modal_time_prefix' => adm_t('modal_time_label', ['time' => '']),
                                'sec_payment' => adm_t('section_payment_info'),
                                'sec_party' => adm_t('section_party_account'),
                                'sec_audit_note' => adm_t('section_audit_note'),
                                'lbl_amount' => adm_t('label_amount'),
                                'lbl_tx_type' => adm_t('label_tx_type'),
                                'lbl_channel' => adm_t('label_channel'),
                                'lbl_status' => adm_t('label_status'),
                                'lbl_ref' => adm_t('label_reference'),
                                'lbl_customer' => adm_t('label_customer'),
                                'lbl_phone' => adm_t('label_phone'),
                                'lbl_room' => adm_t('label_room'),
                                'lbl_store' => adm_t('label_store'),
                                'lbl_bank' => adm_t('label_bank_received'),
                                'lbl_acc_num' => adm_t('label_account_number'),
                                'lbl_acc_name' => adm_t('label_account_name'),
                                'lbl_cash_direct' => adm_t('label_cash_collection'),
                                'lbl_no_note' => adm_t('label_no_note'),
                                'badge_personal' => adm_t('badge_personal'),
                                'badge_group' => adm_t('badge_group'),
                                'badge_transfer' => adm_t('badge_transfer'),
                                'badge_cash' => adm_t('badge_cash'),
                                'badge_received' => adm_t('badge_received'),
                                'badge_cancelled' => adm_t('badge_cancelled'),
                                'store_hq' => adm_t('store_hq'),
                                'btn_open_order' => adm_t('btn_open_order', ['id' => '{id}']),
                                'copied' => adm_t('copied'),
                            ], JSON_UNESCAPED_UNICODE) ?>;

    function renderSepayDetailModal(tx, ctx) {
        const i18n = window.__ADM_I18N__ || {};
        ctx.titleEl.innerText = (i18n.modal_title || 'Chi tiết Giao dịch #') + tx.event_id;
        ctx.subEl.innerText = (i18n.modal_time_prefix || 'Thời gian: ') + formatLocalTime(tx.created_at);
        try {
            if (tx.created_at) {
                const iso = tx.created_at.includes('T') ? (tx.created_at.endsWith('Z') ? tx.created_at : tx.created_at + 'Z') : (tx.created_at.replace(' ', 'T') + 'Z');
                const d = new Date(iso);
                if (!isNaN(d.getTime())) {
                    ctx.subEl.setAttribute('title', d.toString());
                }
            }
        } catch (e) {}

        let recipient = {};
        try {
            recipient = JSON.parse(tx.recipient_json || '{}');
        } catch (e) {}

        const isCancelled = (tx.unified_status === 'cancelled');
        const amountVal = parseFloat(tx.amount || 0);
        const formattedAmount = (isCancelled ? '' : '+') + new Intl.NumberFormat('vi-VN').format(amountVal) + 'đ';

        let channelBadge = (tx.payment_method === 'transfer') ?
            '<span class="sp-badge transfer-badge">' + (i18n.badge_transfer || 'Chuyển khoản') + '</span>' :
            '<span class="sp-badge cash-badge">' + (i18n.badge_cash || 'Tiền mặt') + '</span>';

        let typeBadge = tx.tx_type === 'personal' ?
            '<span class="sp-type-badge personal">' + (i18n.badge_personal || 'Cá nhân') + '</span>' :
            '<span class="sp-type-badge group">' + (i18n.badge_group || 'Nhóm') + '</span>';

        let statusBadge = tx.unified_status === 'received' ?
            '<span class="sp-status-badge status-success">' + (i18n.badge_received || 'Đã nhận') + '</span>' :
            '<span class="sp-status-badge status-warning">' + (i18n.badge_cancelled || 'Đã hủy') + '</span>';

        let storeDisplayName = parseInt(tx.store_id) === 0 ? (i18n.store_hq || 'Trụ sở') : (tx.store_name || (i18n.store_hq || 'Trụ sở'));

        let html = `
        <div class="sp-detail-grid">
            <div class="sp-detail-card">
                <div class="sp-detail-card-title">${escapeHtml(i18n.sec_payment || 'Thông tin Thanh toán')}</div>
                <div class="sp-detail-item">
                    <span class="sp-detail-label">${escapeHtml(i18n.lbl_amount || 'Số tiền:')}</span>
                    <span class="sp-detail-val" style="color:${isCancelled ? '#dc2626' : '#16a34a'};font-size:1.05rem;">${escapeHtml(formattedAmount)}</span>
                </div>
                <div class="sp-detail-item">
                    <span class="sp-detail-label">${escapeHtml(i18n.lbl_tx_type || 'Loại giao dịch:')}</span>
                    <span class="sp-detail-val">${typeBadge}</span>
                </div>
                <div class="sp-detail-item">
                    <span class="sp-detail-label">${escapeHtml(i18n.lbl_channel || 'Kênh thanh toán:')}</span>
                    <span class="sp-detail-val">${channelBadge}</span>
                </div>
                <div class="sp-detail-item">
                    <span class="sp-detail-label">${escapeHtml(i18n.lbl_status || 'Trạng thái:')}</span>
                    <span class="sp-detail-val">${statusBadge}</span>
                </div>
                <div class="sp-detail-item">
                    <span class="sp-detail-label">${escapeHtml(i18n.lbl_ref || 'Mã tham chiếu:')}</span>
                    <span class="sp-detail-val"><code>${escapeHtml(tx.transaction_reference || 'N/A')}</code></span>
                </div>
            </div>

            <div class="sp-detail-card">
                <div class="sp-detail-card-title">${escapeHtml(i18n.sec_party || 'Đối tượng & Tài khoản')}</div>
                <div class="sp-detail-item">
                    <span class="sp-detail-label">${escapeHtml(i18n.lbl_customer || 'Khách / Thành viên:')}</span>
                    <span class="sp-detail-val">${escapeHtml(tx.party_name)}</span>
                </div>
                <div class="sp-detail-item">
                    <span class="sp-detail-label">${escapeHtml(tx.tx_type === 'personal' ? (i18n.lbl_phone || 'Số điện thoại:') : (i18n.lbl_room || 'Phòng nhóm:'))}</span>
                    <span class="sp-detail-val">${escapeHtml(tx.party_phone || 'N/A')}</span>
                </div>
                <div class="sp-detail-item">
                    <span class="sp-detail-label">${escapeHtml(i18n.lbl_store || 'Shop / Cửa hàng:')}</span>
                    <span class="sp-detail-val"><span class="sp-store-badge ${parseInt(tx.store_id) === 0 ? 'hq' : 'branch'}">${escapeHtml(storeDisplayName)}</span></span>
                </div>
                ${tx.payment_method === 'transfer' ? `
                    <div class="sp-detail-item">
                        <span class="sp-detail-label">${escapeHtml(i18n.lbl_bank || 'Ngân hàng nhận:')}</span>
                        <span class="sp-detail-val">${escapeHtml(recipient.bank_name || '-')}</span>
                    </div>
                    <div class="sp-detail-item">
                        <span class="sp-detail-label">${escapeHtml(i18n.lbl_acc_num || 'STK nhận:')}</span>
                        <span class="sp-detail-val"><code>${escapeHtml(recipient.account_number || '-')}</code></span>
                    </div>
                    <div class="sp-detail-item">
                        <span class="sp-detail-label">${escapeHtml(i18n.lbl_acc_name || 'Chủ tài khoản:')}</span>
                        <span class="sp-detail-val">${escapeHtml(recipient.account_name || '-')}</span>
                    </div>
                ` : `
                    <div class="sp-detail-item">
                        <span class="sp-detail-label">${escapeHtml(i18n.lbl_channel || 'Hình thức:')}</span>
                        <span class="sp-detail-val">${escapeHtml(i18n.lbl_cash_direct || 'Thu tiền mặt trực tiếp')}</span>
                    </div>
                `}
            </div>

            <div class="sp-detail-card sp-full-card">
                <div class="sp-detail-card-title">${escapeHtml(i18n.sec_audit_note || 'Nội dung đối soát / Ghi chú')}</div>
                <p style="margin:0;font-size:0.9rem;color:#1e293b;background:#ffffff;padding:10px;border-radius:6px;border:1px solid #e2e8f0;">
                    ${escapeHtml(tx.reason || (i18n.lbl_no_note || 'Không có ghi chú đối soát.'))}
                </p>
            </div>
        </div>`;
        ctx.bodyEl.innerHTML = html;

        if (tx.tx_type === 'personal' && tx.target_id) {
            let openOrderText = (i18n.btn_open_order || 'Mở chi tiết đơn hàng #{id} →').replace('{id}', parseInt(tx.target_id));
            ctx.actionsEl.innerHTML = `<a href="order.php?id=${parseInt(tx.target_id)}" target="_blank" class="btn primary">${escapeHtml(openOrderText)}</a>`;
        } else {
            ctx.actionsEl.innerHTML = '';
        }
    }

    function copyText(text, btnEl) {
        if (!navigator.clipboard) {
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        } else {
            navigator.clipboard.writeText(text);
        }

        if (btnEl) {
            const original = btnEl.innerHTML;
            const copiedText = (window.__ADM_I18N__ && window.__ADM_I18N__.copied) || 'Đã chép!';
            btnEl.classList.add('copied');
            btnEl.innerText = copiedText;
            setTimeout(() => {
                btnEl.classList.remove('copied');
                btnEl.innerHTML = original;
            }, 1500);
        }
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    function formatLocalTime(utcStr) {
        if (!utcStr) return '-';
        try {
            const iso = utcStr.includes('T') ? (utcStr.endsWith('Z') ? utcStr : utcStr + 'Z') : (utcStr.replace(' ', 'T') + 'Z');
            const d = new Date(iso);
            if (isNaN(d.getTime())) return utcStr;
            const pad = (n) => String(n).padStart(2, '0');
            const day = pad(d.getDate());
            const month = pad(d.getMonth() + 1);
            const year = d.getFullYear();
            const hours = pad(d.getHours());
            const minutes = pad(d.getMinutes());
            const seconds = pad(d.getSeconds());
            return `${day}/${month}/${year} ${hours}:${minutes}:${seconds}`;
        } catch (e) {
            return utcStr;
        }
    }

    function applyClientLocalTimes() {
        document.querySelectorAll('.sp-local-time').forEach(function(el) {
            const utc = el.getAttribute('data-utc');
            if (utc) {
                el.innerText = formatLocalTime(utc);
                try {
                    const iso = utc.includes('T') ? (utc.endsWith('Z') ? utc : utc + 'Z') : (utc.replace(' ', 'T') + 'Z');
                    const d = new Date(iso);
                    if (!isNaN(d.getTime())) {
                        el.setAttribute('title', d.toString());
                    }
                } catch (e) {}
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyClientLocalTimes);
    } else {
        applyClientLocalTimes();
    }
</script>

<?php admin_footer(); ?>