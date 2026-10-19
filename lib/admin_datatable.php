<?php

/**
 * AdminDataTable - Reusable Base Component for Admin Data Tables & Dashboards.
 * 
 * Provides out-of-the-box:
 * - Search across fields
 * - Custom dropdown / date range / radio filters
 * - Sort options & URL state preservation
 * - Dynamic pagination (10, 20, 50, 100 per page)
 * - Metric / KPI stat cards overview
 * - Responsive table with customizable column renderers
 * - Detail modal (with expandable/JSON audit views)
 * - Built-in CSV / Excel export
 */

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

class AdminDataTable
{
    protected string $id;
    protected string $title = '';
    protected string $subtitle = '';
    protected array $headerActions = [];
    protected array $metrics = [];
    protected array $filterConfig = [];
    protected array $sortConfig = [];
    protected array $columns = [];
    protected array $detailModalConfig = [];
    protected array $exportConfig = [];
    protected bool $hideTitle = false;

    // Query & Data
    protected $querySource;
    protected array $queryParams = [];
    protected array $records = [];
    protected int $totalRecords = 0;
    protected int $page = 1;
    protected int $perPage = 10;
    protected int $totalPages = 1;
    protected string $currentSort = '';
    protected string $searchQuery = '';
    protected array $activeFilters = [];

    public function __construct(array $config = [])
    {
        $this->id = $config['id'] ?? ('adt_' . bin2hex(random_bytes(4)));
        $this->title = $config['title'] ?? '';
        $this->subtitle = $config['subtitle'] ?? '';
        $this->hideTitle = !empty($config['hide_title']);
        $this->headerActions = $config['header_actions'] ?? [];
        $this->metrics = $config['metrics'] ?? [];
        $this->filterConfig = $config['filters'] ?? [];
        $this->sortConfig = $config['sort'] ?? [];
        $this->columns = $config['columns'] ?? [];
        $this->detailModalConfig = $config['detail_modal'] ?? ['enabled' => true];
        $this->exportConfig = $config['export'] ?? [];

        // Parse query params
        $this->page = max(1, (int) ($_GET['page'] ?? 1));
        $this->perPage = (int) ($_GET['per_page'] ?? ($config['default_per_page'] ?? 10));
        if (!in_array($this->perPage, [10, 20, 50, 100], true)) {
            $this->perPage = 10;
        }

        $defaultSort = $config['sort']['default'] ?? array_key_first($config['sort']['options'] ?? ['default' => '']);
        $this->currentSort = (string) ($_GET['sort'] ?? $defaultSort);
        $this->searchQuery = trim((string) ($_GET['q'] ?? ''));

        // Query source
        if (isset($config['query'])) {
            $this->querySource = $config['query'];
        }
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setQuerySource($source, array $params = []): self
    {
        $this->querySource = $source;
        $this->queryParams = $params;
        return $this;
    }

    public function setRecords(array $records, int $total): self
    {
        $this->records = $records;
        $this->totalRecords = $total;
        $this->totalPages = max(1, (int) ceil($total / $this->perPage));
        return $this;
    }

    public function handleRequest(): self
    {
        // 1. If query is a callable or array configuration
        if (is_callable($this->querySource)) {
            $result = call_user_func($this->querySource, [
                'page' => $this->page,
                'per_page' => $this->perPage,
                'search' => $this->searchQuery,
                'sort' => $this->currentSort,
                'filters' => $_GET,
                'export' => ($_GET['export'] ?? '') === 'csv'
            ]);
            if (is_array($result)) {
                $this->records = $result['records'] ?? [];
                $this->totalRecords = (int) ($result['total'] ?? count($this->records));
            }
        } elseif (is_array($this->querySource) && isset($this->querySource['base_sql'])) {
            $pdo = db();
            $baseSql = $this->querySource['base_sql'];
            $params = $this->querySource['params'] ?? [];
            $orderBy = $this->querySource['order_by'] ?? '';

            // Handle CSV Export
            if (($_GET['export'] ?? '') === 'csv') {
                $this->handleCsvExport("SELECT * FROM ($baseSql) AS _sub $orderBy", $params);
            }

            // Count query
            $countSql = "SELECT COUNT(*) FROM ($baseSql) AS _sub";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $this->totalRecords = (int) $countStmt->fetchColumn();

            $this->totalPages = max(1, (int) ceil($this->totalRecords / $this->perPage));
            if ($this->page > $this->totalPages) {
                $this->page = $this->totalPages;
            }
            $offset = ($this->page - 1) * $this->perPage;

            // List query
            $listSql = "SELECT * FROM ($baseSql) AS _sub $orderBy LIMIT {$this->perPage} OFFSET {$offset}";
            $listStmt = $pdo->prepare($listSql);
            $listStmt->execute($params);
            $this->records = $listStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->totalPages = max(1, (int) ceil($this->totalRecords / $this->perPage));
        return $this;
    }

    protected function handleCsvExport(string $fullSql, array $params): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        $filename = ($this->exportConfig['filename'] ?? 'export-data') . '-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate');
        header('Pragma: public');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM

        $stmt = db()->prepare($fullSql);
        $stmt->execute($params);

        // Header row
        $headers = [];
        foreach ($this->columns as $col) {
            if (!empty($col['export_ignore'])) continue;
            $headers[] = $col['label'] ?? $col['key'];
        }
        fputcsv($out, $headers);

        // Data rows
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $csvRow = [];
            foreach ($this->columns as $col) {
                if (!empty($col['export_ignore'])) continue;
                if (isset($col['export_render']) && is_callable($col['export_render'])) {
                    $val = call_user_func($col['export_render'], $row);
                } elseif (isset($col['render']) && is_callable($col['render'])) {
                    $raw = call_user_func($col['render'], $row, 0);
                    $val = trim(preg_replace('/\s+/', ' ', strip_tags((string)$raw)));
                } else {
                    $val = (string) ($row[$col['key']] ?? '');
                }
                $csvRow[] = $val;
            }
            fputcsv($out, $csvRow);
        }

        fclose($out);
        exit;
    }

    public function buildUrl(array $overrides = []): string
    {
        $params = $_GET;
        if (!array_key_exists('export', $overrides)) {
            unset($params['export']);
        }
        $params = array_merge($params, $overrides);
        return '?' . http_build_query($params);
    }

    protected function t(string $key, array $values = [], string $default = ''): string
    {
        if (function_exists('adm_t')) {
            $translated = adm_t($key, $values);
            if ($translated !== $key) {
                return $translated;
            }
        }
        $str = $default !== '' ? $default : $key;
        foreach ($values as $k => $v) {
            $str = str_replace('{' . $k . '}', (string)$v, $str);
        }
        return $str;
    }

    public function render(): void
    {
        echo '<div class="adt-container" id="' . e($this->id) . '">';
        $this->renderHeader();
        $this->renderMetrics();
        $this->renderFilters();
        $this->renderTable();
        $this->renderDetailModal();
        $this->renderStyles();
        $this->renderScripts();
        echo '</div>';
    }

    protected function renderHeader(): void
    {
        $showTitle = !empty($this->title) && empty($this->hideTitle);
        $hasSubtitle = !empty($this->subtitle);
        $hasActions = !empty($this->exportConfig['enabled']) || !empty($this->headerActions);

        if (!$showTitle && !$hasSubtitle && !$hasActions) {
            return;
        }
?>
        <div class="adt-page-header <?= !$showTitle ? 'no-title' : '' ?>">
            <div>
                <?php if ($showTitle): ?>
                    <h1 class="adt-title"><?= e($this->title) ?></h1>
                <?php endif; ?>
                <?php if ($hasSubtitle): ?>
                    <p class="adt-subtitle"><?= e($this->subtitle) ?></p>
                <?php endif; ?>
            </div>
            <div class="adt-header-actions">
                <?php if (!empty($this->exportConfig['enabled'])): ?>
                    <a href="<?= e($this->buildUrl(['export' => 'csv'])) ?>" class="btn secondary adt-btn-action">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3" />
                        </svg>
                        <?= e($this->exportConfig['label'] ?? $this->t('btn_export_csv', [], 'Xuất Excel / CSV')) ?>
                    </a>
                <?php endif; ?>
                <?php foreach ($this->headerActions as $action): ?>
                    <?= $action ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php
    }

    protected function renderMetrics(): void
    {
        if (empty($this->metrics)) return;
    ?>
        <div class="adt-metrics-grid">
            <?php foreach ($this->metrics as $m):
                $color = $m['color'] ?? 'primary';
            ?>
                <div class="adt-metric-card <?= e($color) ?>">
                    <?php if (!empty($m['icon'])): ?>
                        <div class="adt-metric-icon <?= e($color) ?>">
                            <?= $m['icon'] ?>
                        </div>
                    <?php endif; ?>
                    <div class="adt-metric-content">
                        <span class="adt-metric-label"><?= e($m['label'] ?? '') ?></span>
                        <strong class="adt-metric-val <?= !empty($m['highlight']) ? 'highlight-' . e($m['highlight']) : '' ?>">
                            <?= $m['value'] ?? '' ?>
                        </strong>
                        <?php if (!empty($m['sub'])): ?>
                            <span class="adt-metric-sub"><?= e($m['sub']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php
    }

    protected function renderFilters(): void
    {
        $hasFilters = !empty($this->filterConfig['items']) || !empty($this->filterConfig['search']) || !empty($this->sortConfig['options']);
        if (!$hasFilters) return;

        $searchConfig = $this->filterConfig['search'] ?? ['placeholder' => 'Tìm kiếm dữ liệu...', 'name' => 'q'];
        $items = $this->filterConfig['items'] ?? [];
    ?>
        <section class="adt-filter-panel">
            <form method="get" class="adt-filter-form">
                <input type="hidden" name="tz_offset" id="adt_tz_offset" value="<?= e($_GET['tz_offset'] ?? '') ?>">
                <div class="adt-filter-row">
                    <!-- Search Input -->
                    <?php if ($searchConfig): ?>
                        <div class="adt-form-group adt-flex-grow">
                            <label for="adt_q"><?= e($searchConfig['label'] ?? $this->t('search', [], 'Tìm kiếm')) ?></label>
                            <div class="adt-input-search-wrap">
                                <input type="text" id="adt_q" name="<?= e($searchConfig['name'] ?? 'q') ?>" value="<?= e($this->searchQuery) ?>" placeholder="<?= e($searchConfig['placeholder'] ?? $this->t('filter_search_placeholder', [], 'Tìm kiếm...')) ?>">
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Filter Dropdowns / Inputs -->
                    <?php foreach ($items as $f):
                        $val = $_GET[$f['name']] ?? ($f['default'] ?? 'all');
                    ?>
                        <?php if (($f['type'] ?? 'select') === 'select'): ?>
                            <div class="adt-form-group">
                                <label for="adt_f_<?= e($f['name']) ?>"><?= e($f['label']) ?></label>
                                <select id="adt_f_<?= e($f['name']) ?>" name="<?= e($f['name']) ?>">
                                    <?php foreach ($f['options'] as $optVal => $optLabel): ?>
                                        <option value="<?= e($optVal) ?>" <?= (string)$val === (string)$optVal ? 'selected' : '' ?>>
                                            <?= e($optLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Sub filter row: Date Range, Sort & Per Page -->
                <div class="adt-filter-row adt-filter-row-sub">
                    <?php if (!empty($this->filterConfig['date_range'])):
                        $dCfg = $this->filterConfig['date_range'];
                        $fromName = $dCfg['from'] ?? 'date_from';
                        $toName = $dCfg['to'] ?? 'date_to';
                    ?>
                        <div class="adt-form-group">
                            <label for="adt_date_from"><?= e($dCfg['from_label'] ?? $this->t('date_from', [], 'Từ ngày')) ?></label>
                            <input type="date" id="adt_date_from" name="<?= e($fromName) ?>" value="<?= e($_GET[$fromName] ?? '') ?>">
                        </div>
                        <div class="adt-form-group">
                            <label for="adt_date_to"><?= e($dCfg['to_label'] ?? $this->t('date_to', [], 'Đến ngày')) ?></label>
                            <input type="date" id="adt_date_to" name="<?= e($toName) ?>" value="<?= e($_GET[$toName] ?? '') ?>">
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($this->sortConfig['options'])): ?>
                        <div class="adt-form-group">
                            <label for="adt_sort"><?= e($this->sortConfig['label'] ?? $this->t('sort_by', [], 'Sắp xếp theo')) ?></label>
                            <select id="adt_sort" name="sort">
                                <?php foreach ($this->sortConfig['options'] as $sKey => $sLabel): ?>
                                    <option value="<?= e($sKey) ?>" <?= $this->currentSort === $sKey ? 'selected' : '' ?>>
                                        <?= e($sLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="adt-form-group">
                        <label for="adt_per_page"><?= e($this->t('per_page', [], 'Hiển thị')) ?></label>
                        <select id="adt_per_page" name="per_page">
                            <option value="10" <?= $this->perPage === 10 ? 'selected' : '' ?>><?= e($this->t('per_page_format', ['n' => 10], '10 / trang')) ?></option>
                            <option value="20" <?= $this->perPage === 20 ? 'selected' : '' ?>><?= e($this->t('per_page_format', ['n' => 20], '20 / trang')) ?></option>
                            <option value="50" <?= $this->perPage === 50 ? 'selected' : '' ?>><?= e($this->t('per_page_format', ['n' => 50], '50 / trang')) ?></option>
                            <option value="100" <?= $this->perPage === 100 ? 'selected' : '' ?>><?= e($this->t('per_page_format', ['n' => 100], '100 / trang')) ?></option>
                        </select>
                    </div>

                    <div class="adt-form-actions">
                        <button type="submit" class="btn primary"><?= e($this->t('btn_filter', [], 'Lọc dữ liệu')) ?></button>
                        <a href="<?= e(strtok($_SERVER['REQUEST_URI'] ?? '', '?')) ?>" class="btn secondary"><?= e($this->t('btn_reset', [], 'Đặt lại')) ?></a>
                    </div>
                </div>
            </form>
        </section>
    <?php
    }

    protected function renderTable(): void
    {
        $offset = ($this->page - 1) * $this->perPage;
    ?>
        <section class="adt-table-section">
            <div class="adt-table-header">
                <div class="adt-table-stats">
                    <?= $this->t('page_showing', ['from' => $offset + 1, 'to' => min($this->totalRecords, $offset + $this->perPage), 'total' => number_format($this->totalRecords)], 'Hiển thị <strong>' . ($offset + 1) . '</strong> - <strong>' . min($this->totalRecords, $offset + $this->perPage) . '</strong> trên <strong>' . number_format($this->totalRecords) . '</strong>') ?>
                    <?php if (!empty($_GET['q']) || !empty($_GET['date_from']) || !empty($_GET['date_to'])): ?>
                        <span class="adt-badge-filtered">(Đã lọc)</span>
                    <?php endif; ?>
                </div>
                <div class="adt-page-indicator">
                    <?= $this->page ?> / <?= $this->totalPages ?>
                </div>
            </div>

            <div class="table-wrap">
                <table class="adt-data-table">
                    <thead>
                        <tr>
                            <?php foreach ($this->columns as $col):
                                $style = '';
                                if (!empty($col['width'])) $style .= 'width:' . e($col['width']) . ';';
                                if (!empty($col['align'])) $style .= 'text-align:' . e($col['align']) . ';';
                            ?>
                                <th style="<?= $style ?>"><?= e($col['label'] ?? '') ?></th>
                            <?php endforeach; ?>
                            <?php if (!empty($this->detailModalConfig['enabled'])): ?>
                                <th style="text-align: center; width: 90px;"><?= e($this->t('th_actions', [], 'Thao tác')) ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($this->records)): ?>
                            <tr>
                                <td colspan="<?= count($this->columns) + (!empty($this->detailModalConfig['enabled']) ? 1 : 0) ?>" class="adt-empty-row">
                                    <div class="adt-empty-state">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <circle cx="12" cy="12" r="10" />
                                            <path d="m15 9-6 6M9 9l6 6" />
                                        </svg>
                                        <p><?= e($this->detailModalConfig['empty_message'] ?? $this->t('no_data', [], 'Không tìm thấy dữ liệu nào phù hợp với điều kiện tìm kiếm.')) ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($this->records as $index => $row): ?>
                                <tr class="adt-row" data-row-idx="<?= $index ?>">
                                    <?php foreach ($this->columns as $col):
                                        $style = '';
                                        if (!empty($col['align'])) $style .= 'text-align:' . e($col['align']) . ';';
                                        $cellClass = $col['class'] ?? '';
                                    ?>
                                        <td class="<?= e($cellClass) ?>" style="<?= $style ?>">
                                            <?php if (isset($col['render']) && is_callable($col['render'])): ?>
                                                <?= call_user_func($col['render'], $row, $index) ?>
                                            <?php else: ?>
                                                <?= e($row[$col['key']] ?? '') ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>

                                    <?php if (!empty($this->detailModalConfig['enabled'])): ?>
                                        <td style="text-align: center;">
                                            <button type="button" class="btn small adt-btn-detail" onclick="<?= e($this->id) ?>_showDetail(<?= $index ?>)">
                                                <?= e($this->detailModalConfig['button_label'] ?? $this->t('btn_view_detail', [], 'Chi tiết')) ?>
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <?php if ($this->totalPages > 1): ?>
                <div class="adt-pagination-bar">
                    <div class="adt-pagination-info">
                        <?= $this->t('page_showing', ['from' => $offset + 1, 'to' => min($this->totalRecords, $offset + $this->perPage), 'total' => number_format($this->totalRecords)], 'Hiển thị ' . ($offset + 1) . ' đến ' . min($this->totalRecords, $offset + $this->perPage) . ' trong số ' . number_format($this->totalRecords) . ' bản ghi') ?>
                    </div>
                    <div class="adt-pagination-nav">
                        <?php if ($this->page > 1): ?>
                            <a href="<?= e($this->buildUrl(['page' => 1])) ?>" class="adt-page-btn" title="Trang đầu">&laquo;</a>
                            <a href="<?= e($this->buildUrl(['page' => $this->page - 1])) ?>" class="adt-page-btn" title="<?= e($this->t('page_prev', [], 'Trang trước')) ?>">&lsaquo;</a>
                        <?php endif; ?>

                        <?php
                        $startP = max(1, $this->page - 2);
                        $endP = min($this->totalPages, $this->page + 2);
                        for ($p = $startP; $p <= $endP; $p++):
                        ?>
                            <a href="<?= e($this->buildUrl(['page' => $p])) ?>" class="adt-page-btn <?= $p === $this->page ? 'active' : '' ?>">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($this->page < $this->totalPages): ?>
                            <a href="<?= e($this->buildUrl(['page' => $this->page + 1])) ?>" class="adt-page-btn" title="<?= e($this->t('page_next', [], 'Trang sau')) ?>">&rsaquo;</a>
                            <a href="<?= e($this->buildUrl(['page' => $this->totalPages])) ?>" class="adt-page-btn" title="Trang cuối">&raquo;</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    <?php
    }

    protected function renderDetailModal(): void
    {
        if (empty($this->detailModalConfig['enabled'])) return;
    ?>
        <div id="<?= e($this->id) ?>-modal" class="adt-modal-backdrop" hidden>
            <div class="adt-modal-container">
                <div class="adt-modal-header">
                    <div>
                        <h3 class="adt-modal-title" id="<?= e($this->id) ?>-modal-title"><?= e($this->detailModalConfig['title'] ?? 'Chi tiết Bản ghi') ?></h3>
                        <span class="adt-modal-sub" id="<?= e($this->id) ?>-modal-sub"></span>
                    </div>
                    <button type="button" class="adt-modal-close" onclick="<?= e($this->id) ?>_closeModal()">&times;</button>
                </div>
                <div class="adt-modal-body" id="<?= e($this->id) ?>-modal-body">
                    <!-- Dynamic Content -->
                </div>
                <div class="adt-modal-footer">
                    <span id="<?= e($this->id) ?>-modal-actions"></span>
                    <button type="button" class="btn secondary" onclick="<?= e($this->id) ?>_closeModal()"><?= e($this->t('modal_close', [], 'Đóng')) ?></button>
                </div>
            </div>
        </div>
    <?php
    }

    protected function renderScripts(): void
    {
        $jsonRecords = json_encode($this->records, JSON_UNESCAPED_UNICODE);
        $renderJs = $this->detailModalConfig['render_js'] ?? '';
    ?>
        <script>
            (function() {
                const tableId = <?= json_encode($this->id) ?>;
                const records = <?= $jsonRecords ?: '[]' ?>;
                const customRenderJs = <?= json_encode($renderJs) ?>;

                // Auto-detect and set client timezone offset
                const tzInput = document.getElementById('adt_tz_offset');
                if (tzInput && !tzInput.value) {
                    tzInput.value = new Date().getTimezoneOffset();
                }

                window[tableId + '_showDetail'] = function(index) {
                    const row = records[index];
                    if (!row) return;

                    const modal = document.getElementById(tableId + '-modal');
                    const titleEl = document.getElementById(tableId + '-modal-title');
                    const subEl = document.getElementById(tableId + '-modal-sub');
                    const bodyEl = document.getElementById(tableId + '-modal-body');
                    const actionsEl = document.getElementById(tableId + '-modal-actions');

                    if (customRenderJs && typeof window[customRenderJs] === 'function') {
                        window[customRenderJs](row, {
                            modal: modal,
                            titleEl: titleEl,
                            subEl: subEl,
                            bodyEl: bodyEl,
                            actionsEl: actionsEl,
                            index: index
                        });
                    } else {
                        // Default generic key-value renderer
                        titleEl.innerText = 'Chi tiết Bản ghi #' + (row.id || (index + 1));
                        subEl.innerText = row.created_at ? ('Thời gian: ' + (typeof formatLocalTime === 'function' ? formatLocalTime(row.created_at) : row.created_at)) : '';
                        let html = '<div class="adt-default-detail-grid">';
                        for (const [k, v] of Object.entries(row)) {
                            if (typeof v === 'object' || v === null || v === '') continue;
                            html += `<div class="adt-default-detail-item">
                            <span class="adt-default-label">${k}:</span>
                            <span class="adt-default-val">${v}</span>
                        </div>`;
                        }
                        html += '</div>';
                        bodyEl.innerHTML = html;
                    }

                    if (modal) modal.hidden = false;
                };

                window[tableId + '_closeModal'] = function() {
                    const modal = document.getElementById(tableId + '-modal');
                    if (modal) modal.hidden = true;
                };

                window.addEventListener('click', function(e) {
                    const modal = document.getElementById(tableId + '-modal');
                    if (modal && e.target === modal) {
                        modal.hidden = true;
                    }
                });

                window.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        const modal = document.getElementById(tableId + '-modal');
                        if (modal && !modal.hidden) modal.hidden = true;
                    }
                });
            })();
        </script>
    <?php
    }

    protected function renderStyles(): void
    {
    ?>
        <style>
            .adt-container {
                max-width: 1440px;
                margin: 0 auto;
                color: #1e293b;
            }

            .adt-page-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                flex-wrap: wrap;
                gap: 16px;
                margin-bottom: 24px;
            }

            .adt-page-header.no-title {
                align-items: center;
                margin-top: -6px;
                margin-bottom: 22px;
            }

            .adt-title {
                font-size: 1.6rem;
                font-weight: 750;
                margin: 0 0 6px 0;
                color: #0f172a;
                letter-spacing: -0.02em;
            }

            .adt-subtitle {
                margin: 0;
                color: #64748b;
                font-size: 0.925rem;
            }

            .adt-header-actions {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }

            .adt-btn-action {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 9px 16px;
                font-weight: 600;
                border-radius: 8px;
                cursor: pointer;
            }

            .adt-metrics-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
                gap: 18px;
                margin-bottom: 24px;
            }

            .adt-metric-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 20px;
                display: flex;
                align-items: center;
                gap: 16px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }

            .adt-metric-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 14px rgba(0, 0, 0, 0.06);
            }

            .adt-metric-icon {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            .adt-metric-icon.income {
                background: #ecfdf5;
                color: #059669;
            }

            .adt-metric-icon.sepay,
            .adt-metric-icon.primary {
                background: #eff6ff;
                color: #2563eb;
            }

            .adt-metric-icon.today,
            .adt-metric-icon.warning {
                background: #fef3c7;
                color: #d97706;
            }

            .adt-metric-icon.gateway,
            .adt-metric-icon.neutral {
                background: #f1f5f9;
                color: #475569;
            }

            .adt-metric-content {
                display: flex;
                flex-direction: column;
                gap: 2px;
                min-width: 0;
            }

            .adt-metric-label {
                font-size: 0.8rem;
                font-weight: 600;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }

            .adt-metric-val {
                font-size: 1.35rem;
                font-weight: 750;
                color: #0f172a;
                line-height: 1.2;
            }

            .adt-metric-val.highlight-green {
                color: #059669;
            }

            .adt-metric-sub {
                font-size: 0.8rem;
                color: #94a3b8;
            }

            .adt-filter-panel {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 20px;
                margin-bottom: 24px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            }

            .adt-filter-form {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }

            .adt-filter-row {
                display: flex;
                flex-wrap: wrap;
                gap: 14px;
                align-items: flex-end;
            }

            .adt-filter-row-sub {
                padding-top: 14px;
                border-top: 1px dashed #e2e8f0;
            }

            .adt-form-group {
                display: flex;
                flex-direction: column;
                gap: 6px;
                min-width: 150px;
            }

            .adt-flex-grow {
                flex-grow: 1;
                min-width: 260px;
            }

            .adt-form-group label {
                font-size: 0.825rem;
                font-weight: 600;
                color: #475569;
            }

            .adt-form-group input,
            .adt-form-group select {
                padding: 8px 12px;
                border: 1px solid #cbd5e1;
                border-radius: 7px;
                font-size: 0.9rem;
                background: #ffffff;
                color: #1e293b;
                outline: none;
                transition: border-color 0.15s ease;
            }

            .adt-form-group input:focus,
            .adt-form-group select:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
            }

            .adt-input-search-wrap {
                position: relative;
                display: flex;
                align-items: center;
            }

            .adt-input-search-wrap input {
                width: 100%;
                padding-left: 36px;
            }

            .adt-search-icon {
                position: absolute;
                left: 12px;
                color: #94a3b8;
                pointer-events: none;
            }

            .adt-form-actions {
                display: flex;
                gap: 8px;
                margin-left: auto;
            }

            .adt-table-section {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
                overflow: hidden;
            }

            .adt-table-header {
                padding: 16px 20px;
                border-bottom: 1px solid #f1f5f9;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: #f8fafc;
            }

            .adt-table-stats {
                font-size: 0.925rem;
                color: #334155;
            }

            .adt-badge-filtered {
                color: #2563eb;
                font-size: 0.825rem;
                margin-left: 6px;
                font-weight: 500;
            }

            .adt-page-indicator {
                font-size: 0.85rem;
                color: #64748b;
                font-weight: 500;
            }

            .adt-data-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.875rem;
            }

            .adt-data-table th {
                background: #f8fafc;
                color: #475569;
                font-weight: 650;
                text-transform: uppercase;
                font-size: 0.75rem;
                letter-spacing: 0.04em;
                padding: 12px 16px;
                border-bottom: 1px solid #e2e8f0;
                white-space: nowrap;
            }

            .adt-data-table td {
                padding: 14px 16px;
                border-bottom: 1px solid #f1f5f9;
                vertical-align: middle;
            }

            .adt-row:hover {
                background: #f8fafc;
            }

            .adt-empty-row {
                padding: 48px 20px;
                text-align: center;
            }

            .adt-empty-state {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 12px;
                color: #94a3b8;
            }

            .adt-empty-state p {
                margin: 0;
                font-size: 0.95rem;
                color: #64748b;
            }

            .adt-pagination-bar {
                padding: 16px 20px;
                border-top: 1px solid #f1f5f9;
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
                gap: 12px;
                background: #f8fafc;
            }

            .adt-pagination-info {
                font-size: 0.875rem;
                color: #64748b;
            }

            .adt-pagination-nav {
                display: flex;
                gap: 4px;
            }

            .adt-page-btn {
                min-width: 32px;
                height: 32px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0 6px;
                border: 1px solid #cbd5e1;
                background: #ffffff;
                border-radius: 6px;
                font-size: 0.85rem;
                color: #334155;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.15s ease;
            }

            .adt-page-btn:hover {
                background: #f1f5f9;
                border-color: #94a3b8;
            }

            .adt-page-btn.active {
                background: #2563eb;
                border-color: #2563eb;
                color: #ffffff;
            }

            .adt-modal-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(15, 23, 42, 0.6);
                backdrop-filter: blur(4px);
                z-index: 9999;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .adt-modal-backdrop[hidden] {
                display: none !important;
            }

            .adt-modal-container {
                background: #ffffff;
                border-radius: 14px;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
                width: 100%;
                max-width: 720px;
                max-height: 90vh;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                animation: adtModalFadeIn 0.2s ease-out;
            }

            @keyframes adtModalFadeIn {
                from {
                    opacity: 0;
                    transform: scale(0.96) translateY(8px);
                }

                to {
                    opacity: 1;
                    transform: scale(1) translateY(0);
                }
            }

            .adt-modal-header {
                padding: 20px 24px;
                border-bottom: 1px solid #e2e8f0;
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                background: #f8fafc;
            }

            .adt-modal-title {
                margin: 0 0 4px 0;
                font-size: 1.25rem;
                font-weight: 750;
                color: #0f172a;
            }

            .adt-modal-sub {
                font-size: 0.85rem;
                color: #64748b;
            }

            .adt-modal-close {
                background: transparent;
                border: none;
                font-size: 1.6rem;
                line-height: 1;
                color: #94a3b8;
                cursor: pointer;
                padding: 0 4px;
            }

            .adt-modal-close:hover {
                color: #0f172a;
            }

            .adt-modal-body {
                padding: 24px;
                overflow-y: auto;
                font-size: 0.9rem;
                display: flex;
                flex-direction: column;
                gap: 20px;
            }

            .adt-modal-footer {
                padding: 16px 24px;
                border-top: 1px solid #e2e8f0;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: #f8fafc;
            }

            .adt-default-detail-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }

            .adt-default-detail-item {
                display: flex;
                justify-content: space-between;
                border-bottom: 1px solid #f1f5f9;
                padding: 6px 0;
            }

            .adt-default-label {
                color: #64748b;
                font-weight: 600;
            }

            .adt-default-val {
                font-weight: 600;
                color: #0f172a;
            }
        </style>
<?php
    }
}
