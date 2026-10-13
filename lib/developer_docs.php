<?php
/** Read-only, repository-owned development records. No database or config reads. */
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }
define('HUIYI_DEVELOPER_DOCS', true);

function hdd_escape(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function hdd_labels(string $lang): array {
    $labels = [
        'vi' => ['title'=>'Nhà phát triển','intro'=>'Chức năng, quy trình và lịch sử cập nhật của hệ thống đặt đồ uống.','running'=>'Phiên bản chương trình','baseline'=>'Tài liệu nền','history'=>'Lịch sử cập nhật','read'=>'Đọc tài liệu','contents'=>'Mục lục','download'=>'Tải nhật ký','print'=>'In / Lưu PDF','snapshot'=>'Bản ghi lịch sử','snapshot_note'=>'Tài liệu được giữ nguyên tại thời điểm phát hành. Trạng thái triển khai và các kết quả kiểm chứng trong tài liệu thuộc thời điểm đó.','order'=>'Tiếng Việt ở trên · 中文 ở dưới','append'=>'Các bản cập nhật tiếp theo được bổ sung thành bản ghi riêng; tài liệu cũ được giữ lại.','missing'=>'Không tìm thấy phiên bản tài liệu này.','back'=>'Về bản cập nhật mới nhất'],
        'en' => ['title'=>'Developer','intro'=>'Features, workflows and release history for the ordering system.','running'=>'Application version','baseline'=>'Documentation baseline','history'=>'Release history','read'=>'Read documentation','contents'=>'Contents','download'=>'Download log','print'=>'Print / Save PDF','snapshot'=>'Historical record','snapshot_note'=>'Each record preserves its publication date. Deployment status and validation results describe that point in time.','order'=>'Vietnamese first · Traditional Chinese below','append'=>'Each future update adds a separate record and preserves earlier documentation.','missing'=>'This documentation version was not found.','back'=>'View the latest update'],
        'zh' => ['title'=>'開發者','intro'=>'查看訂餐系統的功能、操作流程與版本更新紀錄。','running'=>'程式版本','baseline'=>'文件基線','history'=>'版本更新紀錄','read'=>'閱讀文件','contents'=>'章節目錄','download'=>'下載日誌','print'=>'列印／儲存 PDF','snapshot'=>'歷史文件快照','snapshot_note'=>'各版文件保留發布時的內容；其中的部署狀態與驗證結果屬於當時紀錄。','order'=>'越南文在上 · 繁體中文在下','append'=>'後續每次更新新增一筆版本紀錄，保留既有文件。','missing'=>'找不到這個版本的開發文件。','back'=>'返回最新更新'],
    ];
    return $labels[$lang] ?? $labels['vi'];
}

function hdd_releases(): array {
    static $releases;
    if ($releases !== null) return $releases;
    $manifest = require __DIR__.'/developer/manifest.php';
    $releases = [];
    foreach ($manifest as $version => $metadata) {
        if (!is_string($version) || !preg_match('/^\d+\.\d+\.\d+$/D', $version) || !is_array($metadata)) continue;
        if (!is_file(__DIR__.'/developer/'.$version.'.php')) continue;
        $releases[$version] = $metadata;
    }
    uksort($releases, static fn(string $a, string $b): int => version_compare($b, $a));
    return $releases;
}

function hdd_document(string $version): ?array {
    $releases = hdd_releases();
    if (!isset($releases[$version])) return null;
    // The exact key must be registered before constructing this fixed path.
    $document = require __DIR__.'/developer/'.$version.'.php';
    if (!is_array($document) || ($document['version'] ?? '') !== $version || !is_string($document['markdown'] ?? null)) return null;
    return array_merge($releases[$version], $document);
}

function hdd_safe_href(string $href, string $prefix): ?string {
    if (preg_match('/^#[a-zA-Z0-9_-]+$/D', $href)) return '#'.$prefix.'-'.substr($href, 1);
    if (preg_match('/[\x00-\x20\x7f]/', $href)) return null;
    $url = parse_url($href);
    if ($url !== false && ($url['scheme'] ?? '') === 'https' && !empty($url['host']) && !isset($url['user']) && !isset($url['pass'])) return $href;
    $repositoryFiles = ['README.md','AGENTS.md','GROUP-READINESS-TEST-REPORT.md','MEMBER-TEST-REPORT.md','GROUP-APPROVAL-TEST-REPORT.md','UPDATE-19.5.1.md','PROMOTION-TEST-REPORT.md','DISPATCH-TEST-REPORT.md','NOTIFICATION-TEST-REPORT.md','tests/README.md'];
    if (in_array($href, $repositoryFiles, true)) return 'https://github.com/xzero790601vn/huiyi-order/blob/dc02b9db652f2160672638ba831926f13b9024ce/'.$href;
    return null;
}

function hdd_inline(string $text, string $prefix): string {
    $parts = preg_split('/(`[^`]+`|\[[^\]]+\]\([^)]+\)|\*\*[^*]+\*\*|<br>)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = '';
    foreach ($parts as $part) {
        if ($part === '<br>') $out .= '<br>';
        elseif (preg_match('/^`([^`]+)`$/s', $part, $m)) $out .= '<code>'.hdd_escape($m[1]).'</code>';
        elseif (preg_match('/^\*\*([^*]+)\*\*$/s', $part, $m)) $out .= '<strong>'.hdd_escape($m[1]).'</strong>';
        elseif (preg_match('/^\[([^\]]+)\]\(([^)]+)\)$/s', $part, $m)) {
            $href = hdd_safe_href($m[2], $prefix);
            $out .= $href === null ? hdd_escape($m[1]).' <code>'.hdd_escape($m[2]).'</code>' : '<a href="'.hdd_escape($href).'"'.(str_starts_with($href, 'https://') ? ' target="_blank" rel="noopener noreferrer"' : '').'>'.hdd_escape($m[1]).'</a>';
        } else $out .= hdd_escape($part);
    }
    return $out;
}

/** Parse only our local, quoted-node Mermaid subset. No HTML, links or directives execute. */
function hdd_flow_data(string $code): ?array {
    $nodes = []; $edges = [];
    $node = '[A-Za-z][A-Za-z0-9]*(?:\["[^"\n]+"\]|\{"[^"\n]+"\})?';
    foreach (explode("\n", trim($code)) as $line) {
        $line = trim($line);
        if ($line === '' || $line === 'flowchart TD') continue;
        if (preg_match('/^('.$node.')\s*-->\s*(?:\|([^|\n]+)\|\s*)?('.$node.')$/uD', $line, $m)) {
            $parts = [$m[1], $m[3]];
            preg_match('/^[A-Za-z][A-Za-z0-9]*/', $m[1], $from);
            preg_match('/^[A-Za-z][A-Za-z0-9]*/', $m[3], $to);
            $edges[] = ['from'=>$from[0], 'to'=>$to[0], 'label'=>trim($m[2])];
        } elseif (preg_match('/^'.$node.'$/uD', $line)) $parts = [$line];
        else return null;
        foreach ($parts as $part) {
            if (!preg_match('/^([A-Za-z][A-Za-z0-9]*)(\[|\{)"([^"\n]+)"(\]|\})$/uD', $part, $match)) continue;
            $value = ['id'=>$match[1], 'label'=>$match[3], 'decision'=>$match[2] === '{', 'rank'=>0];
            if (isset($nodes[$match[1]]) && $nodes[$match[1]] !== $value) return null;
            $nodes[$match[1]] = $value;
        }
    }
    if (!$nodes || count($nodes) > 64 || count($edges) > 128) return null;
    $incoming = array_fill_keys(array_keys($nodes), 0); $outgoing = [];
    foreach ($edges as $edge) {
        if (!isset($nodes[$edge['from']], $nodes[$edge['to']])) return null;
        $incoming[$edge['to']]++; $outgoing[$edge['from']][] = $edge['to'];
    }
    $queue = array_keys(array_filter($incoming, static fn(int $count): bool => $count === 0));
    for ($i = 0; $i < count($queue); ++$i) {
        $id = $queue[$i];
        foreach ($outgoing[$id] ?? [] as $target) {
            $nodes[$target]['rank'] = max($nodes[$target]['rank'], $nodes[$id]['rank'] + 1);
            if (--$incoming[$target] === 0) $queue[] = $target;
        }
    }
    // Unsupported cycles stay visible as escaped source, never as an incomplete drawing.
    return count($queue) === count($nodes) ? ['nodes'=>$nodes, 'edges'=>$edges] : null;
}

function hdd_flowchart(string $code): string {
    $graph = hdd_flow_data($code);
    if ($graph === null) return '<pre><code>'.hdd_escape($code).'</code></pre>';
    $rows = []; $steps = []; $number = 0;
    foreach ($graph['nodes'] as $node) {
        $rows[$node['rank']][] = '<div class="hdd-flow-node'.($node['decision'] ? ' hdd-flow-decision' : '').'" data-hdd-node="'.hdd_escape($node['id']).'"><span class="hdd-flow-number" aria-hidden="true">'.($node['decision'] ? '?' : (string)++$number).'</span><span>'.hdd_escape($node['label']).'</span></div>';
    }
    ksort($rows); $columns = max(array_map('count', $rows));
    foreach ($graph['edges'] as $edge) {
        $steps[] = '<li><span>'.hdd_escape($graph['nodes'][$edge['from']]['label']).'</span><span class="hdd-flow-arrow" aria-hidden="true">→</span><span>'.hdd_escape($graph['nodes'][$edge['to']]['label']).'</span>'.($edge['label'] !== '' ? '<small>'.hdd_escape($edge['label']).'</small>' : '').'</li>';
    }
    $html = '<figure class="hdd-flow" data-hdd-flow><figcaption>Sơ đồ thao tác / 操作流程圖</figcaption><p class="hdd-flow-hint" hidden>↔ Vuốt ngang / 左右滑動查看分支</p>';
    $html .= '<div class="hdd-flow-scroll" hidden tabindex="0" role="region" aria-label="Sơ đồ, cuộn ngang khi cần / 流程圖，可橫向捲動"><div class="hdd-flow-canvas" style="--hdd-columns:'.$columns.'" data-hdd-edges="'.hdd_escape(json_encode($graph['edges'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)).'">';
    foreach ($rows as $row) $html .= '<div class="hdd-flow-row">'.implode('', $row).'</div>';
    $html .= '</div></div><details class="hdd-flow-text" open><summary>Các bước và điều kiện / 文字步驟與分支條件</summary><ul>'.implode('', $steps).'</ul></details></figure>';
    return $html;
}

function hdd_outline(string $markdown, string $prefix): array {
    $outline = []; $index = 0; $fenced = false;
    foreach (explode("\n", str_replace("\r", '', $markdown)) as $line) {
        if (str_starts_with($line, '```')) { $fenced = !$fenced; continue; }
        if (!$fenced && preg_match('/^(#{1,6})\s+(.+)$/u', $line, $m)) {
            ++$index;
            if (strlen($m[1]) <= 2) $outline[] = ['id'=>$prefix.'-section-'.$index,'title'=>$m[2],'level'=>strlen($m[1])];
        }
    }
    return $outline;
}

/** A small renderer for the document's known GFM subset; raw HTML is escaped. */
function hdd_render_markdown(string $markdown, string $prefix): string {
    $prefix = preg_replace('/[^a-zA-Z0-9_-]/', '-', $prefix);
    $lines = explode("\n", str_replace("\r", '', $markdown));
    $out = ''; $heading = 0; $list = ''; $language = false;
    $closeList = static function() use (&$out, &$list): void { if ($list !== '') { $out .= '</'.$list.'>'; $list = ''; } };
    for ($i = 0, $count = count($lines); $i < $count; ++$i) {
        $line = $lines[$i];
        if (trim($line) === '') { $closeList(); continue; }
        if (preg_match('/^<a id="(tieng-viet|traditional-chinese)"><\/a>$/D', $line, $m)) {
            $closeList(); if ($language) $out .= '</section>';
            $out .= '<section class="hdd-language" lang="'.($m[1] === 'tieng-viet' ? 'vi' : 'zh-Hant').'"><span id="'.hdd_escape($prefix.'-'.$m[1]).'"></span>'; $language = true; continue;
        }
        if (preg_match('/^```([a-zA-Z0-9_-]*)\s*$/D', $line, $m)) {
            $closeList(); $code = [];
            while (++$i < $count && !str_starts_with($lines[$i], '```')) $code[] = $lines[$i];
            $code = implode("\n", $code);
            $out .= $m[1] === 'mermaid' ? hdd_flowchart($code) : '<pre><code>'.hdd_escape($code).'</code></pre>'; continue;
        }
        if (preg_match('/^(#{1,6})\s+(.+)$/u', $line, $m)) {
            $closeList(); ++$heading; $level = min(6, strlen($m[1]) + 1);
            $out .= '<h'.$level.' id="'.hdd_escape($prefix.'-section-'.$heading).'">'.hdd_inline($m[2], $prefix).'</h'.$level.'>'; continue;
        }
        if (trim($line) === '---') { $closeList(); $out .= '<hr>'; continue; }
        if (str_starts_with($line, '|') && isset($lines[$i+1]) && preg_match('/^\|[\s:|\-]+\|\s*$/D', $lines[$i+1])) {
            $closeList(); $cells = static fn(string $row): array => array_map('trim', explode('|', trim(trim($row), '|')));
            $header = $cells($line); $alignments = $cells($lines[++$i]);
            $out .= '<div class="hdd-table"><table><thead><tr>';
            foreach ($header as $cell) $out .= '<th scope="col">'.hdd_inline($cell, $prefix).'</th>';
            $out .= '</tr></thead><tbody>';
            while (isset($lines[$i+1]) && str_starts_with($lines[$i+1], '|')) {
                $out .= '<tr>';
                foreach ($cells($lines[++$i]) as $column => $cell) $out .= '<td'.(str_ends_with($alignments[$column] ?? '', ':') ? ' class="hdd-number"' : '').'>'.hdd_inline($cell, $prefix).'</td>';
                $out .= '</tr>';
            }
            $out .= '</tbody></table></div>'; continue;
        }
        if (preg_match('/^(?:([-*])\s+|\d+\.\s+)(.+)$/u', $line, $m)) {
            $type = ($m[1] ?? '') !== '' ? 'ul' : 'ol';
            if ($list !== $type) { $closeList(); $out .= '<'.$type.'>'; $list = $type; }
            $content = $m[2];
            if (preg_match('/^\[([ xX])\]\s+(.+)$/u', $content, $check)) $content = ($check[1] === ' ' ? '☐ ' : '☑ ').$check[2];
            $out .= '<li>'.hdd_inline($content, $prefix).'</li>'; continue;
        }
        $closeList(); $out .= '<p>'.hdd_inline($line, $prefix).'</p>';
    }
    $closeList(); if ($language) $out .= '</section>';
    return $out;
}
