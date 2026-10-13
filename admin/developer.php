<?php
require __DIR__.'/../lib/bootstrap.php';
require __DIR__.'/../lib/layout.php';
require_admin();
header('Cache-Control: private, no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
require_once __DIR__.'/../lib/developer_docs.php';
$labels = hdd_labels(current_lang());
$releases = hdd_releases();
$workflowVersion = null;
foreach ($releases as $releaseVersion => $metadata) {
    if (!empty($metadata['workflows'])) { $workflowVersion = $releaseVersion; break; }
}
$workflowLabels = ['vi'=>'Sơ đồ thao tác', 'en'=>'Operation workflows', 'zh'=>'操作流程圖'];
$requested = $_GET['version'] ?? array_key_first($releases);
$version = is_string($requested) ? $requested : '';
$document = hdd_document($version);
if ($document === null) {
    http_response_code(404);
    admin_header($labels['title']);
    echo '<p>'.e($labels['missing']).'</p><a class="btn" href="'.e(base_url('admin/developer.php')).'">'.e($labels['back']).'</a>';
    admin_footer(); exit;
}
if (isset($_GET['download']) && $_GET['download'] === '1') {
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="HUIYI-development-'.$version.'.md"');
    header('X-Content-Type-Options: nosniff');
    echo $document['markdown']; exit;
}
$running = trim((string)file_get_contents(__DIR__.'/../VERSION'));
$prefix = 'hdd-'.str_replace('.', '-', $version);
$titleKey = current_lang() === 'zh' ? 'title_zh' : 'title_vi';
admin_header($labels['title']);
?>
<link rel="stylesheet" href="<?=e(base_url('assets/css/developer.css?v=19.14.4'))?>">
<section class="developer-page">
  <div class="hdd-intro">
    <div><p><?=e($labels['intro'])?></p><p class="hdd-language-note"><?=e($labels['order'])?></p></div>
    <div class="hdd-tools"><a class="btn" href="<?=e(base_url('admin/developer.php?version='.rawurlencode($version).'&download=1'))?>"><?=e($labels['download'])?></a><button class="btn" type="button" data-hdd-print hidden><?=e($labels['print'])?></button></div>
  </div>
  <div class="hdd-summary"><div><span><?=e($labels['running'])?></span><strong><?=e($running)?></strong></div><div><span><?=e($labels['baseline'])?></span><strong>19.9.1</strong></div></div>
  <?php if ($workflowVersion !== null): ?><nav class="hdd-shortcuts" aria-label="<?=e($labels['read'])?>"><a href="<?=e(base_url('admin/developer.php?version='.rawurlencode($workflowVersion)))?>"<?=$version === $workflowVersion ? ' aria-current="page"' : ''?>><?=e($workflowLabels[current_lang()] ?? $workflowLabels['vi'])?> · <?=e($workflowVersion)?></a><a href="<?=e(base_url('admin/developer.php?version=19.9.1'))?>"<?=$version === '19.9.1' ? ' aria-current="page"' : ''?>><?=e($labels['baseline'])?> · 19.9.1</a></nav><?php endif; ?>
  <div class="hdd-shell">
    <aside class="hdd-sidebar">
      <details class="hdd-history" open><summary><?=e($labels['history'])?></summary><nav class="hdd-version-grid" aria-label="<?=e($labels['history'])?>"><?php foreach ($releases as $releaseVersion => $metadata): ?><a class="hdd-release<?=$releaseVersion === $version ? ' active' : ''?>" href="<?=e(base_url('admin/developer.php?version='.rawurlencode($releaseVersion)))?>"<?=$releaseVersion === $version ? ' aria-current="page"' : ''?>><strong><?=e($releaseVersion)?></strong><span><?=e($metadata[$titleKey])?></span><small><?=e($metadata['date'])?></small></a><?php endforeach; ?></nav></details>
      <details class="hdd-toc" open><summary><?=e($labels['contents'])?></summary><nav aria-label="<?=e($labels['contents'])?>"><?php foreach (hdd_outline($document['markdown'], $prefix) as $item): ?><a href="#<?=e($item['id'])?>" class="hdd-toc-level-<?=$item['level']?>"><?=e($item['title'])?></a><?php endforeach; ?></nav></details>
      <p class="hdd-append-note"><?=e($labels['append'])?></p>
    </aside>
    <article class="hdd-document">
      <div class="hdd-record-heading"><span><?=e($labels['snapshot'])?> · <?=e($document['date'])?></span><h2><?=e($version.' · '.$document[$titleKey])?></h2><p><?=e($labels['snapshot_note'])?></p></div>
      <div class="hdd-prose"><?=hdd_render_markdown($document['markdown'], $prefix)?></div>
    </article>
  </div>
</section>
<script src="<?=e(base_url('assets/js/developer.js?v=19.14.4'))?>" defer></script>
<?php admin_footer(); ?>
