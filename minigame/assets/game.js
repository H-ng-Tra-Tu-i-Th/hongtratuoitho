'use strict';
(() => {
  const root = document.querySelector('.kyuc-game');
  if (!root) return;
  const language = ['vi', 'en', 'zh'].includes(root.dataset.lang) ? root.dataset.lang : 'vi';
  const words = {
    vi: {
      loading: 'Đang mở ký ức…', ready: 'Lật hai thẻ để tìm một cặp hình giống nhau.',
      first: 'Tìm người bạn của thẻ vừa mở nhé!', match: 'Một cặp ký ức đã tìm thấy nhau!',
      mismatch: 'Chưa giống nhau. Ghi nhớ hai hình rồi thử lại nhé.',
      complete: 'Đủ 6 cặp rồi! Đang lưu phần thưởng của bạn…', saved: 'Hoàn thành! Phần thưởng đã được lưu vào ví quà của bạn.',
      no_chances: 'Bạn chưa có lượt chơi. Chọn ưu đãi Mua 10 nhận lượt rút thưởng và hoàn tất đơn đủ 10 ly để mở trò chơi.',
      disabled: 'Chương trình trò chơi đang tạm dừng.', unavailable: 'Phần thưởng đang được chuẩn bị. Lượt chơi của bạn được giữ lại.', rate_limit: 'Bạn lật thẻ quá nhanh. Chờ khoảng một phút, rồi tải lại tiến độ để tiếp tục.', error: 'Chưa thể kết nối. Tải lại tiến độ để tiếp tục.',
      incomplete: 'Hãy ghép đủ 6 cặp trước khi nhận phần thưởng.', changed: 'Lượt chơi đã thay đổi. Tải lại tiến độ để tiếp tục.',
      sync: 'Tiến độ đã được đồng bộ. Bạn có thể tiếp tục.', retry: 'Tải lại tiến độ', saving: 'Đang lưu phần thưởng…',
      copy: 'Sao chép mã quà', copied: 'Đã sao chép mã quà.', card: 'Thẻ úp số ', found: 'Đã ghép: ',
      faces: ['Máy bay giấy', 'Con quay', 'Viên bi', 'Kẹo tuổi thơ', 'Ly trà thân quen', 'Đôi bạn nhỏ']
    },
    zh: {
      loading: '正在開啟童年回憶……', ready: '翻開兩張卡，找出相同的童年圖案。',
      first: '找出剛剛那張卡片的好朋友吧！', match: '一組童年回憶配對成功！',
      mismatch: '圖案不同，記住它們的位置再試一次。', complete: '六組回憶都找到了！正在保存獎勵……',
      saved: '配對完成！獎勵已保存到你的優惠券錢包。',
      no_chances: '目前沒有遊戲機會。選擇「買10抽獎」優惠，完成滿10杯飲品的訂單即可解鎖。',
      disabled: '小遊戲活動目前暫停。', unavailable: '獎項正在準備中，你的遊戲機會會保留。', rate_limit: '翻牌速度太快，請稍候約一分鐘，再重新載入遊戲進度。', error: '暫時無法連線，請重新載入遊戲進度。',
      incomplete: '請先完成全部六組配對，再領取獎勵。', changed: '遊戲機會已變更，請重新載入遊戲進度。',
      sync: '遊戲進度已同步，可以繼續配對。', retry: '重新載入遊戲進度', saving: '正在保存獎勵……',
      copy: '複製獎項序號', copied: '獎項序號已複製。', card: '蓋牌第 ', found: '已配對：',
      faces: ['紙飛機', '陀螺', '彈珠', '童年糖果', '熟悉的茶香', '童年好朋友']
    },
    en: {
      loading: 'Opening your memories…', ready: 'Turn two cards and find the matching pictures.',
      first: 'Find the friend of the card you just turned!', match: 'A pair of memories has found each other!',
      mismatch: 'Different pictures. Remember their positions and try again.', complete: 'All 6 pairs found! Saving your reward…',
      saved: 'Complete! Your reward has been saved to your wallet.',
      no_chances: 'No game chances yet. Choose Buy 10 for a prize draw and complete an order with at least 10 drinks to unlock this game.',
      disabled: 'The game campaign is paused.', unavailable: 'Rewards are being prepared. Your game chance has been kept.', rate_limit: 'Cards were turned too quickly. Wait about a minute, then reload your progress to continue.', error: 'Unable to connect. Reload your progress to continue.',
      incomplete: 'Find all 6 pairs before claiming your reward.', changed: 'Your game has changed. Reload your progress to continue.',
      sync: 'Your progress is synced. You can continue.', retry: 'Reload progress', saving: 'Saving your reward…',
      copy: 'Copy reward code', copied: 'Reward code copied.', card: 'Face-down card ', found: 'Matched: ',
      faces: ['Paper plane', 'Spinning top', 'Marbles', 'Childhood candy', 'A familiar cup of tea', 'Childhood friends']
    }
  };
  const t = words[language];
  const paths = [
    '<path d="m5 29 38-22-12 38-9-13-17-3Z"/><path d="M22 32 43 7M22 32l-2 10 7-5"/>',
    '<path d="M26 5v8M9 21c0-6 34-6 34 0S9 27 9 21Z"/><path d="M9 21c3 13 17 22 17 26 0-4 14-13 17-26M13 30c9 4 17 4 26 0M19 38h14"/>',
    '<circle cx="19" cy="31" r="12"/><circle cx="35" cy="17" r="12"/><path d="M13 32c7 0 11-3 11-8M31 13c0 5 3 8 8 8M14 23l2-1M30 8l2-1"/>',
    '<path d="m13 22-8-9 13-2 4 7m16 12 8 9-13 2-4-7"/><rect x="15" y="16" width="22" height="22" rx="8" transform="rotate(-32 26 27)"/><path d="m22 20 11 9M17 26l11 9"/>',
    '<path d="m15 19 3 28h21l3-28M12 18h33v-5H12zM31 13l4-10h5l-4 10M18 26h21"/><circle cx="24" cy="35" r="2"/><circle cx="32" cy="40" r="2"/><circle cx="35" cy="32" r="2"/><circle cx="23" cy="43" r="1"/>',
    '<circle cx="17" cy="19" r="8"/><circle cx="37" cy="20" r="7"/><path d="M3 43v-4c0-14 28-14 28 0v4M32 30c13-2 16 6 16 13M10 16c3-4 8-5 14-1M33 16c3-3 5-3 8 0"/>'
  ];
  const grid = root.querySelector('[data-game-grid]');
  const cards = [...root.querySelectorAll('[data-position]')];
  const notice = root.querySelector('[data-game-notice]');
  const retry = root.querySelector('[data-game-retry]');
  const next = root.querySelector('[data-game-next]');
  const rewardElement = root.querySelector('[data-game-reward]');
  let board = null, reward = null, busy = false, blocked = true, balance = 0;
  let appearance = { faces: [] }, appearanceKey = '';
  let elapsedAt = Date.now(), waitUntil = 0, retryAt = 0, hideTimer = null, finishTimer = null, freshTimer = null, rateTimer = null;
  let freshMatches = [], errorNotice = false;
  const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const formatTime = seconds => `${Math.floor(seconds / 60).toString().padStart(2, '0')}:${(seconds % 60).toString().padStart(2, '0')}`;

  function message(text, error = false) {
    if (!notice) return;
    notice.textContent = text;
    errorNotice = error;
    notice.classList.toggle('kyuc-error', error);
  }

  function imageUrl(value) {
    if (!value || typeof value !== 'string') return '';
    try {
      const url = new URL(value, location.href);
      return url.origin === location.origin && !url.search && !url.hash && /\/uploads\/minigame\/[a-f0-9]{32}\.(jpg|png|webp)$/.test(url.pathname) ? url.href : '';
    } catch { return ''; }
  }

  function faceLabel(face) {
    return appearance.faces?.[face] ? ({ zh: '圖案 ', vi: 'Hình ', en: 'Picture ' }[language]) + (face + 1) : t.faces[face];
  }

  function faceIcon(face) {
    const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    for (const [key, value] of Object.entries({ viewBox: '0 0 52 52', fill: 'none', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'aria-hidden': 'true' })) icon.setAttribute(key, value);
    icon.innerHTML = paths[face];
    return icon;
  }

  function showSettings(response) {
    const settings = response.appearance || {};
    const key = JSON.stringify(settings);
    if (key !== appearanceKey) {
      appearanceKey = key;
      appearance = { faces: Array.from({ length: 6 }, (_, i) => imageUrl(settings.faces?.[i])) };
      root.dataset.theme = ['memory', 'garden', 'night'].includes(settings.theme) ? settings.theme : 'memory';
      const backUrl = imageUrl(settings.back_image);
      cards.forEach(card => {
        const back = card.querySelector('.kyuc-card-back');
        const img = card.querySelector('[data-game-back-image]');
        back?.classList.toggle('kyuc-custom-back', !!backUrl);
        if (!img) return;
        img.hidden = !backUrl;
        if (backUrl) {
          img.onerror = () => { img.hidden = true; back?.classList.remove('kyuc-custom-back'); };
          img.src = backUrl;
        } else img.removeAttribute('src');
      });
    }
    const prizes = Array.isArray(response.prizes) ? response.prizes : [];
    const name = prize => prize.name?.[language] || prize.name?.vi || '';
    const odds = root.querySelector('[data-game-odds]');
    if (odds) { odds.hidden = prizes.length === 0; odds.textContent = prizes.map(prize => `${name(prize)} ${Number(prize.odds_percent).toFixed(2).replace(/\.?0+$/, '')}%`).join(' · '); }
    const details = root.querySelector('[data-game-prize-details]');
    if (details) details.hidden = prizes.length === 0;
    const list = root.querySelector('[data-game-prizes]');
    if (list) {
      list.replaceChildren();
      prizes.forEach(prize => {
        const li = document.createElement('li'), title = document.createElement('strong'), terms = document.createElement('span');
        title.textContent = name(prize);
        terms.textContent = (prize.terms?.[language] || prize.terms?.vi || '') + ' ' + ({ zh: `有效 ${prize.valid_days} 天`, vi: `Có hiệu lực ${prize.valid_days} ngày`, en: `Valid for ${prize.valid_days} days` }[language]);
        li.append(title, terms); list.append(li);
      });
    }
  }

  function render() {
    const waiting = Date.now() < waitUntil;
    root.classList.toggle('kyuc-is-busy', busy);
    if (grid) grid.setAttribute('aria-busy', String(busy));
    cards.forEach(card => {
      const position = Number(card.dataset.position);
      const matched = !!board?.matched.includes(position);
      const fresh = freshMatches.includes(position);
      const open = !!board?.open.includes(position) || fresh;
      const face = board?.faces?.[position];
      const front = card.querySelector('.kyuc-card-face');
      if (front) {
        front.replaceChildren();
        if (Number.isInteger(face) && face >= 0 && face < paths.length && (open || matched)) {
          let icon = faceIcon(face);
          const custom = appearance.faces?.[face];
          if (custom) {
            icon = document.createElement('img'); icon.className = 'kyuc-face-image'; icon.alt = ''; icon.src = custom;
            icon.onerror = () => { if (icon.parentNode === front) icon.replaceWith(faceIcon(face)); };
          }
          const label = document.createElement('strong');
          label.textContent = faceLabel(face);
          front.append(icon, label);
        }
      }
      card.classList.toggle('kyuc-flipped', open);
      card.classList.toggle('kyuc-matched', matched && !fresh);
      card.classList.toggle('kyuc-found', fresh);
      card.disabled = blocked || busy || waiting || !board || board.complete || open || matched;
      card.setAttribute('aria-pressed', String(open));
      const known = Number.isInteger(face) && face >= 0 && face < paths.length;
      card.setAttribute('aria-label', matched && known ? t.found + faceLabel(face) : open && known ? faceLabel(face) : t.card + (position + 1));
      if (matched && !fresh) {
        card.setAttribute('aria-hidden', 'true');
        card.tabIndex = -1;
      } else {
        card.removeAttribute('aria-hidden');
        card.removeAttribute('tabindex');
      }
    });
    root.querySelectorAll('[data-game-balance]').forEach(el => { el.textContent = String(balance); });
    const pairs = root.querySelector('[data-game-pairs]');
    const moves = root.querySelector('[data-game-moves]');
    const progress = root.querySelector('[data-game-progress]');
    if (pairs) pairs.textContent = String(board?.pairs || 0);
    if (moves) moves.textContent = String(board?.moves || 0);
    if (progress) progress.value = board?.pairs || 0;
    updateClock();
    if (retry) retry.disabled = busy || Date.now() < retryAt;
    if (next) { next.hidden = !reward || balance < 1; next.disabled = busy; }
    if (rewardElement) rewardElement.hidden = !reward;
    root.querySelector('[data-game-cleared]')?.toggleAttribute('hidden', !board?.complete || freshMatches.length > 0);
  }

  function updateClock() {
    const element = root.querySelector('[data-game-time]');
    if (!element) return;
    const additional = board?.started && !board.complete ? Math.floor((Date.now() - elapsedAt) / 1000) : 0;
    element.textContent = formatTime((board?.elapsed_seconds || 0) + Math.max(0, additional));
  }

  function showReward() {
    if (!reward) return;
    const code = root.querySelector('[data-game-reward-code]');
    const amount = root.querySelector('[data-game-reward-amount]');
    const copy = root.querySelector('[data-game-reward-copy]');
    const name = root.querySelector('[data-game-reward-name]');
    const terms = root.querySelector('[data-game-reward-terms]');
    const expiry = root.querySelector('[data-game-reward-expiry]');
    if (name) name.textContent = reward.name?.[language] || reward.name?.vi || '';
    if (terms) terms.textContent = reward.terms?.[language] || reward.terms?.vi || '';
    if (expiry) {
      const date = new Date(reward.expires_at || '');
      expiry.hidden = Number.isNaN(date.getTime());
      if (!expiry.hidden) expiry.textContent = ({ vi: 'Hạn dùng: ', zh: '有效至：', en: 'Valid until: ' }[language]) + new Intl.DateTimeFormat({ vi: 'vi-VN', zh: 'zh-TW', en: 'en-GB' }[language], {
        timeZone: 'Asia/Ho_Chi_Minh', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'
      }).format(date) + ' (UTC+7)';
    }
    if (code) { code.textContent = reward.coupon_code || ''; code.hidden = !reward.coupon_code; }
    if (amount) {
      const locale = { vi: 'vi-VN', zh: 'zh-TW', en: 'en-US' }[language];
      const formatted = new Intl.NumberFormat(locale, { maximumFractionDigits: 2 }).format(Number(reward.coupon_amount) || 0);
      amount.textContent = `${root.dataset.currency || ''}${formatted}`;
      amount.hidden = !reward.coupon_code || reward.kind !== 'discount';
    }
    if (copy) { copy.hidden = !reward.coupon_code; copy.setAttribute('aria-label', t.copy); }
  }

  function applyResponse(response, animate = false) {
    showSettings(response);
    const oldMatched = board?.matched || [];
    board = response.board || null;
    balance = Math.max(0, Number(response.balance) || 0);
    reward = response.reward || null;
    elapsedAt = Date.now();
    waitUntil = Date.now() + Math.max(0, Number(board?.wait_ms) || 0);
    blocked = !board || (!reward && balance < 1);
    retryAt = 0;
    clearTimeout(rateTimer);
    clearTimeout(hideTimer);
    clearTimeout(freshTimer);
    freshMatches = animate && board ? board.matched.filter(position => !oldMatched.includes(position)) : [];
    if (retry) retry.hidden = true;
    if (reward) { showReward(); message(t.saved); }
    else if (board?.complete) message(t.complete);
    else if (!board) message(balance > 0 ? t.loading : t.no_chances);
    else message(t[response.event] || (board.open.length === 1 ? t.first : board.wait_ms > 0 ? t.mismatch : t.ready));
    render();
    if (freshMatches.length) {
      freshTimer = window.setTimeout(() => {
        const focused = document.activeElement;
        const focusWasMatched = cards.includes(focused) && board?.matched.includes(Number(focused.dataset.position));
        freshMatches = [];
        render();
        if (focusWasMatched && !board?.complete) cards.find(card => !card.disabled)?.focus({ preventScroll: true });
      }, reducedMotion() ? 0 : 600);
    }
    if (board && board.wait_ms > 0 && !reward) {
      hideTimer = window.setTimeout(() => {
        if (!busy) run('state', false);
      }, board.wait_ms + 80);
    }
  }

  async function request(action, extra = {}) {
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 15000);
    const body = new URLSearchParams({ csrf: root.dataset.csrf || '', action, ...extra });
    try {
      const response = await fetch(root.dataset.api, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store', body,
        headers: { 'Accept': 'application/json' }, signal: controller.signal
      });
      const result = await response.json();
      if (!response.ok || !result.ok) {
        const error = new Error(result.error || 'connection');
        error.code = result.error || 'connection';
        throw error;
      }
      return result;
    } finally { clearTimeout(timeout); }
  }

  function errorText(code) {
    return ({ no_chances: t.no_chances, game_disabled: t.disabled, disabled: t.disabled,
      campaign_disabled: t.disabled, rewards_unavailable: t.unavailable, rate_limit: t.rate_limit,
      matching_incomplete: t.incomplete, board_changed: t.changed })[code] || t.error;
  }

  async function run(action, autoStart = true, extra = {}) {
    if (busy || Date.now() < retryAt) return;
    busy = true;
    blocked = true;
    message(action === 'finish' ? t.saving : action === 'flip' ? notice?.textContent || t.ready : t.loading);
    render();
    clearTimeout(finishTimer);
    let synced = false;
    try {
      const response = await request(action, extra);
      applyResponse(response, action === 'flip');
      if (autoStart && !board && !reward && balance > 0) applyResponse(await request('start'));
    } catch (error) {
      // A lost response may already have changed the server board. Never repeat a flip.
      const unavailable = ['no_chances', 'game_disabled', 'disabled', 'campaign_disabled', 'rewards_unavailable'].includes(error.code);
      const limited = error.code === 'rate_limit';
      if (limited) {
        clearTimeout(hideTimer);
        retryAt = Date.now() + 60000;
        rateTimer = window.setTimeout(render, 60010);
      }
      if (!unavailable && !limited) {
        try {
          applyResponse(await request('state'));
          synced = true;
        } catch (_) { /* Keep the retry control visible when progress cannot be fetched. */ }
      }
      if (!synced) {
        blocked = true;
        message(errorText(error.code), true);
        if (retry) retry.hidden = unavailable;
      } else if (action === 'finish' && !reward) {
        blocked = true;
        message(t.error, true);
        if (retry) retry.hidden = false;
      } else if (!reward && board) message(t.sync);
      else if (!reward && balance > 0) { message(t.error, true); if (retry) retry.hidden = false; }
    } finally {
      busy = false;
      render();
    }
    // Completion is also retried safely after reload; the server returns the same reward.
    if (board?.complete && !reward && !errorNotice) {
      finishTimer = window.setTimeout(() => run('finish', false, { board_id: board.id }), freshMatches.length && !reducedMotion() ? 650 : 0);
    }
  }

  cards.forEach(card => card.addEventListener('click', () => {
    if (blocked || busy || !board || board.complete || Date.now() < waitUntil || card.disabled) return;
    run('flip', false, { board_id: board.id, seq: String(board.seq), position: card.dataset.position });
  }));
  retry?.addEventListener('click', () => run('state'));
  next?.addEventListener('click', () => {
    if (!busy && reward && balance > 0 && board) run('next', false, { board_id: board.id });
  });
  root.querySelector('[data-game-reward-copy]')?.addEventListener('click', async () => {
    if (!reward?.coupon_code) return;
    try { await navigator.clipboard.writeText(reward.coupon_code); message(t.copied); }
    catch (_) { root.querySelector('[data-game-reward-code]')?.focus(); }
  });
  if (notice) { notice.setAttribute('role', 'status'); notice.setAttribute('aria-live', 'polite'); }
  if (retry) retry.textContent = t.retry;
  window.setInterval(updateClock, 1000);
  window.addEventListener('pageshow', event => { if (event.persisted && !busy) run('state'); });
  document.addEventListener('visibilitychange', () => { if (!document.hidden && !busy) run('state'); });
  render();
  run('state');
})();
