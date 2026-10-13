# Ký Ức 10 Ly — standalone matching edition, v1.1.0

## Scope and compatibility
This is the same standalone PHP application as HTTT_KyUc10_CPanel_v1.0.0, not an integration with any other project. The original supplied logo/mascot assets are unchanged. No WordPress, POS, cart, external authentication, SMS, analytics, CDN, database server, npm or Composer dependency is introduced. No font files are distributed.

The on-disk schema remains additive and compatible with v1.0.0. Retain this project's existing app/config.php and private data_dir. Existing users, invitation codes, campaign/reward settings and coupon snapshots are not replaced or migrated into a new database. Existing coupons with the legacy `card` field remain readable/redeemable. New coupons have `game_mode=matching` and `game_stats`; they do not depend on a selected prize-card index.

## Server-owned matching board
app/matching.php adds a shuffled board with exactly two copies of symbols 0–5. Fisher–Yates uses PHP random_int. Formal boards are stored as games[id].matching, under the original exclusive file transaction lock. Demo boards are stored only in the current PHP session.

Each board stores: unique board id, hidden deck, open positions, removed/matched positions, move count, optimistic sequence number, creation/start/completion timestamps and temporary mismatch deadline. A move is one two-card attempt. The first card, repeated selection of the same open card, and already matched positions do not create a move. There is no time limit or failure threshold. Elapsed time is wall-clock time starting at the first accepted reveal; leaving the page does not pause it.

The public matching view sends only already exposed or matched faces. It never sends the hidden deck. General bootstrap, wallet and admin game responses strip the private matching board and may include a progress summary. A mismatch remains exposed for approximately 950 ms, and the server blocks a third reveal during that interval. After that deadline, a fresh view hides the mismatched cards even on reload. Matching cards stay in fixed grid slots in the browser but disappear visually and leave the keyboard focus order.

## API
- POST match_start: create or retrieve the current formal board; resume the session demo, or replace it only for an explicit demo restart.
- POST match_flip: validate mode, owner, claimed invitation, expiry, campaign status, board id, sequence and position. Return the accepted state and event. A stale sequence returns current state without an additional reveal.
- POST play: legacy route retained but now refuses an unfinished formal board. The server checks all twelve matched positions and a completion timestamp before issuing a coupon.
- POST demo: refuses an unfinished demo board; returns an idempotent demonstration reward with no redeemable code.

Both modes retain CSRF checks. Matching requests have a per-session burst guard to avoid a small per-IP cap blocking an entire shop's Wi-Fi. Existing login, claim, reward completion and administrator limits remain. Ownership, active account, code revocation and expiry are checked server-side. These controls are not a claim of bot-proof gameplay or a security certification.

## Exactly one formal reward
Only a completed formal board can enter the existing prize transaction. Prize selection, limited-stock decrement, coupon creation and played_at/coupon_id updates use one exclusive store transaction. Every retry for a paid game returns the original coupon. Prize weighting is independent of attempts, speed or symbol choice. Existing fallback-prize rules and next-order-only coupon restrictions are unchanged. Demo results never insert coupons, decrement prize stock or contribute to formal statistics.

A game-code reissue rotates the invitation, resets activation/expiry and clears that game's unfinished/unawarded matching board. Already awarded games cannot be reissued. Revocation invalidates unfinished play and unused coupons as before. The UI explains the reset before the administrator confirms reissue.

## Storage and deployment
PHP 8.2+ is the declared minimum. Tests used PHP 8.4.23. The original guarded JSON file store uses flock plus a same-directory atomic replacement and keeps one previous snapshot. Storage must remain outside every public web root. This is a single-host small-campaign store, not a high-volume or multi-host database; every formal flip writes the state file. No load or capacity claim is made.

The application secret, configuration and private data must never be included in a public package. Update archives omit installers and config files. A clean full install starts with formal awards OFF and demo ON; an upgrade preserves operator settings. Follow UPDATE_V1.1.0.txt, pause traffic and back up both application files and private data before overwriting code.

## Frontend and recovery
The shipped JavaScript renders 12 indistinguishable backs and only paints a symbol when it has been revealed. A mismatch flips back; a correct pair briefly highlights then fades away without reflow. Completion shows attempts, elapsed time and the original reward/coupon UI. Mobile first interaction scrolls the board into view. Reduced-motion preferences, keyboard interaction, descriptive labels, disabled-state guards and live status messages are supported.

Formal snapshots recover after refresh or another authenticated session. A lost API response leaves a recovery button, not a new prize draw. Cache query versions were bumped to 1.1.0. Old open tabs should be closed/reloaded after deployment.

## Not included
No checkout/cart integration, automated order verification, SMS/OTP verification, MySQL conversion, multi-store staff-role system, automatic backups, native app or independent security audit. The previous manual POS/receipt verification responsibilities remain. Actual cPanel/Apache/TLS configuration and Safari behavior must be checked by the operator.
