<?php
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/promotion_views.php';
require_once __DIR__ . '/../lib/minigame.php';
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
$state = hymg_request('state');
$hasReward = is_array($state['reward']);
$canPlay = minigame_enabled() && (int)$state['balance'] > 0 && ($state['board'] ? count($state['prizes']) > 0 : hgr_ready());
$appearance = $state['appearance'];
$prizeDays = ['zh' => '有效 %d 天', 'vi' => 'Có hiệu lực %d ngày', 'en' => 'Valid for %d days'][current_lang()] ?? 'Valid for %d days';
$prizeHeading = ['zh' => '獎項與使用條件', 'vi' => 'Quà và điều kiện sử dụng', 'en' => 'Prizes and conditions'][current_lang()] ?? 'Prizes and conditions';
if (!$canPlay && !$hasReward) http_response_code(403);
front_header(hgr_text('title'));
?>
<link rel="stylesheet" href="<?= e(base_url('minigame/assets/style.css?v=19.9.8')) ?>">
<div class="kyuc-game" data-theme="<?= e($appearance['theme']) ?>" data-api="<?= e(base_url('actions/minigame.php')) ?>" data-csrf="<?= e(csrf_token()) ?>" data-lang="<?= e(current_lang()) ?>" data-currency="<?= e(currency()) ?>">
  <div class="kyuc-toolbar"><a class="back" href="<?= e(base_url('index.php')) ?>">← <?= e(t('back_menu')) ?></a>
    <div class="kyuc-balance"><span><?= e(t('game_chances')) ?></span><strong data-game-balance><?= (int)$state['balance'] ?></strong></div>
  </div>
  <div class="kyuc-layout">
    <section class="kyuc-hero">
      <div class="kyuc-hero-copy"><span class="kyuc-eyebrow">HỒNG TRÀ TUỔI THƠ · LITTLE MEMORIES</span>
        <h1><?= e(hgr_text('title')) ?></h1>
        <p class="kyuc-lead"><?= e(hgr_text('intro')) ?></p>
      </div>
      <div class="kyuc-mascots" aria-hidden="true"><img class="kyuc-boy" src="<?= e(base_url('minigame/assets/boy.webp')) ?>" alt=""><img class="kyuc-girl" src="<?= e(base_url('minigame/assets/girl.webp')) ?>" alt=""></div>
    </section>
    <?php if (!$canPlay && !$hasReward): ?>
      <section class="kyuc-board kyuc-empty">
        <h2><?= e((int)$state['balance'] > 0 && minigame_enabled() ? hgr_text('prepare') : t('game_no_chances')) ?></h2>
        <p><?= e(hgr_text('rules')) ?></p><a class="btn primary" href="<?= e(base_url('index.php')) ?>"><?= e(t('return_menu')) ?></a>
      </section>
    <?php else: ?>
      <section class="kyuc-board">
        <div class="kyuc-board-heading">
          <h2><?= e(t('game_center')) ?></h2>
          <p><?= e(hgr_text('no_limit')) ?></p>
        </div>
        <div class="kyuc-stats">
          <div class="kyuc-stat"><span><?= e(hgr_text('pairs')) ?></span><strong><b data-game-pairs>0</b> / 6</strong></div>
          <div class="kyuc-stat"><span><?= e(hgr_text('moves')) ?></span><strong data-game-moves>0</strong></div>
          <div class="kyuc-stat"><span><?= e(hgr_text('time')) ?></span><strong data-game-time>00:00</strong></div>
        </div>
        <progress data-game-progress max="6" value="0" aria-label="<?= e(hgr_text('pairs')) ?>"></progress>
        <div class="kyuc-grid-wrap">
          <div class="kyuc-grid" data-game-grid aria-label="<?= e(t('game_center')) ?>">
            <?php for ($i = 0; $i < 12; $i++): ?><button type="button" class="kyuc-card" data-position="<?= $i ?>" disabled><span class="kyuc-card-inner"><span class="kyuc-card-back <?= $appearance['back_image'] !== '' ? 'kyuc-custom-back' : '' ?>" aria-hidden="true"><span class="kyuc-card-mark">✦</span><span class="kyuc-card-label">KÝ ỨC</span><small class="kyuc-card-brand">TUỔI THƠ</small><img data-game-back-image <?= $appearance['back_image'] !== '' ? 'src="' . e($appearance['back_image']) . '"' : 'hidden' ?> alt=""></span><span class="kyuc-card-face"></span></span></button><?php endfor; ?>
          </div>
          <div class="kyuc-cleared" data-game-cleared hidden><span aria-hidden="true">✓</span>
            <h3><?= e(t('game_complete')) ?></h3>
            <p><?= e(hgr_text('reward')) ?></p>
          </div>
        </div>
        <p class="kyuc-notice" data-game-notice role="status" aria-live="polite"></p>
        <div class="kyuc-actions"><button class="btn" type="button" data-game-retry hidden><?= e(hgr_text('retry')) ?></button><button class="btn primary" type="button" data-game-next hidden><?= e(hgr_text('next')) ?></button></div>
        <section class="kyuc-reward" data-game-reward hidden><span class="kyuc-eyebrow"><?= e(hgr_text('reward')) ?></span>
          <h3 data-game-reward-name></h3>
          <p data-game-reward-terms></p><code data-game-reward-code></code><span data-game-reward-amount hidden></span>
          <p data-game-reward-expiry></p>
<div class="kyuc-share-bonus" data-game-share-bonus hidden><p></p><button class="btn primary" type="button"></button></div>
          <div class="kyuc-reward-actions"><a class="btn primary" href="<?= e(base_url('cart.php')) ?>"><?= e(t('cart')) ?></a><a class="btn" href="<?= e(base_url('member/index.php#member-rewards')) ?>"><?= e(pt('view_member_rewards')) ?></a></div>
        </section>
      </section>
      <script defer src="<?= e(base_url('minigame/assets/game.js?v=19.9.13')) ?>"></script>
    <?php endif; ?>
  </div>
  <section class="kyuc-rules">
    <p><?= e(hgr_text('rules')) ?></p>
    <p class="kyuc-odds" data-game-odds data-money-label="<?= e(hgr_text('odds_money')) ?>" <?= $state['prizes'] ? '' : 'hidden' ?>><?= hgr_odds_html($state['prizes']) ?></p>
    <p><?= e(pt(hym_current_member() ? 'member_saved' : 'guest_saved')) ?></p>
  </section>
  <?php require __DIR__ . '/share_facebook.php'; ?>
  <details class="kyuc-prize-details" data-game-prize-details <?= $state['prizes'] ? '' : 'hidden' ?>>
    <summary><?= e($prizeHeading) ?></summary>
    <ul data-game-prizes><?php foreach ($state['prizes'] as $prize): if (hgr_reward_is_zero($prize)) continue; ?><li><strong><?= e(hgr_label($prize)) ?></strong><span><?= e(hgr_label($prize, 'terms') . ' ' . sprintf($prizeDays, (int)$prize['valid_days'])) ?></span></li><?php endforeach; ?></ul>
  </details>
</div>
<?php front_footer(); ?>