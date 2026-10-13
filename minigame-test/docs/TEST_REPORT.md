# V1.1.0 local test report

Tested package: standalone 12-card / 6-pair matching upgrade.
Date: 2026-09-19. PHP runtime: 8.4.23. Minimum declared runtime: PHP 8.2.

## Method and limits
API tests used real HTTP requests against a local PHP development server (four worker processes for concurrent-completion tests) and isolated synthetic v1.0.0-format private data. The existing user/coupon model and new matching endpoints were exercised together. Fresh-install tests used a separate clean copy and a separate private directory.

UI tests executed the shipped JavaScript and CSS in Chromium. This environment's managed browser blocks direct navigation, so HTML was supplied using Playwright set_content, original images were supplied as data URIs, and fetch was bridged to the real local PHP HTTP endpoints. No application logic or matching outcome was mocked. This is a browser/HTTP-bridge test, not an end-to-end deployment on cPanel. The tests did not exercise an actual HTTPS cookie/proxy path, Apache .htaccess handling, iPhone Safari, large-scale load or independent penetration testing.

Only synthetic accounts and coupons were used. Test credentials, config, private state and test server scripts are excluded from both distribution archives. Preview images use synthetic data.

## API and compatibility — 40 passed

- PASS: Bootstrap advertises v1.1.0
- PASS: Legacy direct draw is blocked until matching is complete
- PASS: Forged demo completion is blocked
- PASS: Every new board contains exactly 6 distinct pairs
- PASS: Initial API snapshot exposes no hidden faces
- PASS: bootstrap cannot expose the secret board
- PASS: wallet cannot expose the secret board
- PASS: admin_data cannot expose the secret board
- PASS: Other accounts cannot open this member board
- PASS: Invalid card index is rejected
- PASS: First reveal exposes only the selected face
- PASS: Selecting the same card twice is a no-op
- PASS: Stale/concurrent request synchronizes without changing board
- PASS: Mismatch increments one move and reveals two cards
- PASS: Third card is blocked during mismatch reveal
- PASS: Expired mismatch is hidden after refresh
- PASS: Equal faces are matched and removed from selectable cards
- PASS: A removed card cannot be counted twice
- PASS: Matching progress survives another browser/session
- PASS: Partial progress still cannot claim a prize
- PASS: All six pairs complete the board
- PASS: No coupon is issued before completion transaction
- PASS: Concurrent completion issues exactly one coupon
- PASS: Coupon contains matching summary
- PASS: Subsequent play returns the same original coupon
- PASS: Completed board startup returns existing coupon
- PASS: Existing v1.0 coupon remains accessible without replay
- PASS: Revoked game cannot continue
- PASS: Reissue clears the unfinished board
- PASS: Reissued game requires new code activation
- PASS: New code starts fresh board
- PASS: Requests for previous board are rejected
- PASS: Demo finishes only after all six pairs
- PASS: Demo never inserts a redeemable coupon
- PASS: Demo completion retry returns the same result
- PASS: Demo replay creates a fresh board
- PASS: Legacy coupon may not be used on original purchase
- PASS: Old coupon remains redeemable on eligible next purchase
- PASS: A coupon cannot be redeemed twice
- PASS: Matching endpoints retain CSRF verification

## UI and responsive flows — 28 passed

- PASS: Player board has twelve face-down cards
- PASS: Hidden cards do not contain matching symbols
- PASS: Keyboard Enter reveals exactly one selected card
- PASS: Both unmatched faces stay visible before flipping back
- PASS: Mismatched pair flips back and counts one attempt
- PASS: Identical pair disappears visually
- PASS: Remaining cards stay in their original grid positions
- PASS: Changing language preserves progress
- PASS: Leaving and returning to the game keeps removed pairs
- PASS: Fresh page load recovers server-saved formal progress
- PASS: Clearing all pairs opens the completion/reward dialog
- PASS: Coupon is saved automatically after completion
- PASS: All twelve completed cards are removed
- PASS: Official logo and mascot images load without replacement artwork
- PASS: The new reward appears beside the original v1 coupon
- PASS: Original v1.0 coupon dialog remains usable
- PASS: A dropped response presents recovery, not a new draw
- PASS: Recovery returns the already-saved first reveal
- PASS: 390px layout has no horizontal overflow
- PASS: First mobile flip brings the play area into view
- PASS: Mobile touch reveals the selected card
- PASS: Demo reshuffle resets attempts and reveals
- PASS: 320px layout fits without horizontal overflow
- PASS: 360px layout fits without horizontal overflow
- PASS: 768px layout fits without horizontal overflow
- PASS: 1024px layout fits without horizontal overflow
- PASS: Existing management dashboard still loads
- PASS: No JavaScript runtime errors during these flows

## Fresh installation — 5 passed

- PASS: Fresh v1.1.0 installation succeeds with private storage
- PASS: Fresh install retains live-awards OFF / demo ON defaults
- PASS: Demo server board starts after fresh installation
- PASS: Direct matching helper entry returns 404
- PASS: Installer refuses to rerun after configuration exists

## Release checks

All shipped PHP files passed php -l and all three JavaScript files passed node --check. Both ZIP archives were tested for CRC integrity. Hash manifests are provided for the files in each archive. These syntax and archive checks do not replace hosting-side acceptance testing.
