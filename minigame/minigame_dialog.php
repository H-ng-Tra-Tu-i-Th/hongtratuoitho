<?php
// Trỏ ra ngoài 1 cấp thư mục để vào thư mục lib/
require_once __DIR__ . '/../lib/minigame.php';
require_once __DIR__ . '/../lib/promotion_views.php';

$state = hymg_request('state');

$hasReward = isset($state['reward']) && is_array($state['reward']);

$canPlay = minigame_enabled()
    && isset($state['balance']) && (int)$state['balance'] > 0
    && (
        !empty($state['board'])
        ? (isset($state['prizes']) && is_array($state['prizes']) && count($state['prizes']) > 0)
        : hgr_ready()
    );

$appearance = $state['appearance'] ?? ['theme' => '', 'back_image' => ''];
$prizes = $state['prizes'] ?? [];

$prizeDays = ['zh' => '有效 %d 天', 'vi' => 'Có hiệu lực %d ngày', 'en' => 'Valid for %d days'][current_lang()] ?? 'Valid for %d days';
$prizeHeading = ['zh' => '獎項與使用條件', 'vi' => 'Quà và điều kiện sử dụng', 'en' => 'Prizes and conditions'][current_lang()] ?? 'Prizes and conditions';
?>

<!-- File CSS của Minigame -->
<link rel="stylesheet" href="<?= e(base_url('minigame/assets/style.css?v=19.9.0')) ?>">

<style>
    /* Style tùy chỉnh cơ bản cho Dialog */
    .kyuc-dialog {
        border: none;
        border-radius: 16px;
        padding: 24px;
        max-width: 850px;
        width: 92vw;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        position: relative;
    }

    .kyuc-dialog::backdrop {
        background: rgba(0, 0, 0, 0.65);
        backdrop-filter: blur(3px);
    }

    .kyuc-dialog-close {
        position: absolute;
        top: 12px;
        right: 16px;
        background: transparent;
        border: none;
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
        color: #666;
        z-index: 10;
        font-size: xx-large;
        margin: -18px -12px;
    }

    .kyuc-dialog-close:hover {
        color: #000;
    }
</style>

<!-- Thẻ Dialog chứa toàn bộ Game -->
<dialog id="minigameModal" class="kyuc-dialog">
    <!-- Nút đóng Dialog -->
    <button type="button" class="kyuc-dialog-close" onclick="document.getElementById('minigameModal').close()">✕</button>

    <div class="kyuc-game" data-theme="<?= e($appearance['theme'] ?? '') ?>" data-api="<?= e(base_url('actions/minigame.php')) ?>" data-csrf="<?= e(csrf_token()) ?>" data-lang="<?= e(current_lang()) ?>" data-currency="<?= e(currency()) ?>">

        <div class="kyuc-layout">
            <!-- Banner Hero -->
            <section class="kyuc-hero">
                <div class="kyuc-hero-copy">
                    <span class="kyuc-eyebrow">HỒNG TRÀ TUỔI THƠ · LITTLE MEMORIES</span>
                    <h1><?= e(hgr_text('title')) ?></h1>
                    <p class="kyuc-lead"><?= e(hgr_text('intro')) ?></p>
                </div>
                <div class="kyuc-mascots" aria-hidden="true">
                    <img class="kyuc-boy" src="<?= e(base_url('minigame/assets/boy.webp')) ?>" alt="">
                    <img class="kyuc-girl" src="<?= e(base_url('minigame/assets/girl.webp')) ?>" alt="">
                </div>
            </section>

            <?php if (!$canPlay && !$hasReward): ?>
                <!-- Màn hình hết lượt chơi / không đủ điều kiện -->
                <section class="kyuc-board kyuc-empty">
                    <h2><?= e(isset($state['balance']) && (int)$state['balance'] > 0 && minigame_enabled() ? hgr_text('prepare') : t('game_no_chances')) ?></h2>
                    <p><?= e(hgr_text('rules')) ?></p>
                    <button type="button" class="btn primary" onclick="document.getElementById('minigameModal').close()"><?= e(t('return_menu')) ?></button>
                </section>
            <?php else: ?>
                <!-- Khung Game chính -->
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

                    <!-- Lưới thẻ bài -->
                    <div class="kyuc-grid-wrap">
                        <div class="kyuc-grid" data-game-grid aria-label="<?= e(t('game_center')) ?>">
                            <?php for ($i = 0; $i < 12; $i++): ?>
                                <button type="button" class="kyuc-card" data-position="<?= $i ?>" disabled>
                                    <span class="kyuc-card-inner">
                                        <span class="kyuc-card-back <?= ($appearance['back_image'] ?? '') !== '' ? 'kyuc-custom-back' : '' ?>" aria-hidden="true">
                                            <span class="kyuc-card-mark">✦</span>
                                            <span class="kyuc-card-label">KÝ ỨC</span>
                                            <small class="kyuc-card-brand">TUỔI THƠ</small>
                                            <?php if (($appearance['back_image'] ?? '') !== ''): ?>
                                                <img data-game-back-image src="<?= e($appearance['back_image']) ?>" alt="">
                                            <?php else: ?>
                                                <img data-game-back-image hidden alt="">
                                            <?php endif; ?>
                                        </span>
                                        <span class="kyuc-card-face"></span>
                                    </span>
                                </button>
                            <?php endfor; ?>
                        </div>

                        <!-- Màn hình hoàn thành game -->
                        <div class="kyuc-cleared" data-game-cleared hidden>
                            <span aria-hidden="true">✓</span>
                            <h3><?= e(t('game_complete')) ?></h3>
                            <p><?= e(hgr_text('reward')) ?></p>
                        </div>
                    </div>

                    <p class="kyuc-notice" data-game-notice role="status" aria-live="polite"></p>

                    <div class="kyuc-actions">
                        <button class="btn" type="button" data-game-retry hidden><?= e(hgr_text('retry')) ?></button>
                        <button class="btn primary" type="button" data-game-next hidden><?= e(hgr_text('next')) ?></button>
                    </div>

                    <!-- Màn hình nhận thưởng -->
                    <section class="kyuc-reward" data-game-reward hidden>
                        <span class="kyuc-eyebrow"><?= e(hgr_text('reward')) ?></span>
                        <h3 data-game-reward-name></h3>
                        <p data-game-reward-terms></p>
                        <code data-game-reward-code></code>
                        <span data-game-reward-amount hidden></span>
                        <p data-game-reward-expiry></p>
                        <div class="kyuc-reward-actions">
                            <button class="btn primary" type="button" onclick="document.getElementById('minigameModal').close()">Đóng & Dùng Quà</button>
                        </div>
                    </section>
                </section>

                <!-- Script nạp logic Game -->
                <script defer src="<?= e(base_url('minigame/assets/game.js?v=19.9.0')) ?>"></script>
            <?php endif; ?>
        </div>

        <!-- Quy tắc game & Chi tiết phần thưởng -->
        <section class="kyuc-rules">
            <p><?= e(hgr_text('rules')) ?></p>
            <p class="kyuc-odds" data-game-odds <?= $prizes ? '' : 'hidden' ?>>
                <?php foreach ($prizes as $i => $prize): ?>
                    <?= $i ? ' · ' : '' ?><?= e(hgr_label($prize) . ' ' . rtrim(rtrim(number_format((float)$prize['odds_percent'], 2, '.', ''), '0'), '.') . '%') ?>
                <?php endforeach; ?>
            </p>
            <p><?= e(pt(hym_current_member() ? 'member_saved' : 'guest_saved')) ?></p>
        </section>

        <details class="kyuc-prize-details" data-game-prize-details <?= $prizes ? '' : 'hidden' ?>>
            <summary><?= e($prizeHeading) ?></summary>
            <ul data-game-prizes>
                <?php foreach ($prizes as $prize): ?>
                    <li>
                        <strong><?= e(hgr_label($prize)) ?></strong>
                        <span><?= e(hgr_label($prize, 'terms') . ' ' . sprintf($prizeDays, (int)($prize['valid_days'] ?? 0))) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </details>
    </div>
</dialog>

<!-- <script defer src="<?= e(base_url('minigame/assets/game.js?v=19.9.0')) ?>"></script> -->

<!-- Dòng 188 cũ của bạn -->
<script defer src="<?= e(base_url('minigame/assets/game.js?v=19.9.0')) ?>"></script>

<!-- BỔ SUNG ĐOẠN SCRIPT NÀY NGAY BÊN DƯỚI -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const minigameModal = document.getElementById('minigameModal');

        if (minigameModal) {
            // Click ra ngoài vùng Dialog (click vào phần nền xám) để đóng
            minigameModal.addEventListener('click', function(event) {
                const rect = minigameModal.getBoundingClientRect();
                const isInDialog = (
                    rect.top <= event.clientY &&
                    event.clientY <= rect.top + rect.height &&
                    rect.left <= event.clientX &&
                    event.clientX <= rect.left + rect.width
                );
                if (!isInDialog) {
                    minigameModal.close();
                }
            });
        }
    });

    // Hàm toàn cục để dễ gọi mở/đóng từ nút bấm trên Checkout
    window.openMinigameModal = function() {
        const modal = document.getElementById('minigameModal');
        if (modal) modal.showModal();
    };

    window.closeMinigameModal = function() {
        const modal = document.getElementById('minigameModal');
        if (modal) modal.close();
    };
</script>