<?php
/** Server-owned 12-card matching boards. Hidden faces never leave this file's view filter. */
declare(strict_types=1);
if (!defined('KYUC_APP')) { http_response_code(404); exit; }

const MATCHING_PAIRS = 6;
const MATCHING_HIDE_MS = 950;

function matching_now(): int { return (int)floor(microtime(true) * 1000); }

function matching_new(): array {
    $deck = array_merge(range(0, MATCHING_PAIRS - 1), range(0, MATCHING_PAIRS - 1));
    for ($i = count($deck) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$deck[$i], $deck[$j]] = [$deck[$j], $deck[$i]];
    }
    return ['id' => uid(), 'deck' => $deck, 'open' => [], 'matched' => [], 'moves' => 0,
        'seq' => 0, 'created_at' => matching_now(), 'started_at' => null,
        'completed_at' => null, 'hide_at' => 0];
}

/** A short, per-session burst guard avoids penalizing all customers on one shop Wi-Fi. */
function matching_throttle(): void {
    $now = time();
    $r = $_SESSION['matching_rate'] ?? ['until' => $now + 60, 'n' => 0];
    if ($r['until'] <= $now) $r = ['until' => $now + 60, 'n' => 0];
    $r['n']++;
    $_SESSION['matching_rate'] = $r;
    if ($r['n'] > 240) fail('rate_limit', 429);
}

function matching_view(array $b): array {
    $now = matching_now();
    // A stale mismatched pair is already hidden in state responses, including after a reload.
    $open = ($b['hide_at'] && $b['hide_at'] <= $now) ? [] : $b['open'];
    $faces = [];
    foreach (array_unique(array_merge($open, $b['matched'])) as $pos) $faces[$pos] = $b['deck'][$pos];
    $end = $b['completed_at'] ?? $now;
    return ['id' => $b['id'], 'seq' => $b['seq'], 'open' => array_values($open),
        'matched' => $b['matched'], 'faces' => (object)$faces,
        'moves' => $b['moves'], 'pairs' => intdiv(count($b['matched']), 2),
        'total_pairs' => MATCHING_PAIRS, 'started' => $b['started_at'] !== null,
        'complete' => $b['completed_at'] !== null,
        'elapsed_seconds' => $b['started_at'] === null ? 0 : max(0, intdiv($end - $b['started_at'], 1000)),
        'wait_ms' => max(0, $b['hide_at'] - $now)];
}

function matching_summary(array $b): array {
    $v = matching_view($b);
    return array_intersect_key($v, array_flip(['moves', 'pairs', 'total_pairs', 'elapsed_seconds', 'complete']));
}

/** Optimistic sequence checks make retries / concurrent tabs sync without turning another card. */
function matching_flip(array &$b, string $boardId, int $seq, int $pos): string {
    if (!hash_equals($b['id'], $boardId)) fail('board_changed', 409);
    if ($b['hide_at'] && $b['hide_at'] <= matching_now()) { $b['open'] = []; $b['hide_at'] = 0; }
    if ($seq !== $b['seq']) return 'sync';
    if ($b['completed_at'] !== null) return 'complete';
    if ($b['hide_at'] > matching_now()) return 'wait';
    if (in_array($pos, $b['matched'], true) || in_array($pos, $b['open'], true)) return 'ignored';
    $b['started_at'] ??= matching_now();
    $b['open'][] = $pos;
    $b['seq']++;
    if (count($b['open']) === 1) return 'first';
    $b['moves']++;
    [$a, $c] = $b['open'];
    if ($b['deck'][$a] === $b['deck'][$c]) {
        $b['matched'] = array_merge($b['matched'], $b['open']);
        $b['open'] = [];
        if (count($b['matched']) === MATCHING_PAIRS * 2) $b['completed_at'] = matching_now();
        return $b['completed_at'] !== null ? 'complete' : 'match';
    }
    $b['hide_at'] = matching_now() + MATCHING_HIDE_MS;
    return 'mismatch';
}

function matching_game(array $d, string $id): array {
    $u = require_user($d);
    $g = $d['games'][$id] ?? null;
    if (!$g || $g['user_id'] !== $u['id'] || $g['revoked_at']) fail('code_invalid', 404);
    // Issued coupons remain readable when a campaign is paused or the invitation expires.
    if ($g['coupon_id']) return $g;
    if (!$d['settings']['active']) fail('campaign_paused', 409);
    if (!$g['claimed_at'] || $g['expires_at'] <= time()) fail('code_expired', 409);
    return $g;
}
