/**
 * HUIYI Tea - SePay VietQR Payment Service & Controller
 * Implements:
 * 1. SepayPaymentService (API requests, dynamic VietQR generation, state verification)
 * 2. SepayPaymentManager (Polling loop, Countdown timer, 1-click clipboard copy, QR zoom modal, Success animations)
 * 3. Automatic DOM binder for Individual Orders and Group Orders (Split Bill / Host All)
 */

(function () {
  'use strict';

  function t(key, fallback, vars = {}) {
    const dict = (window.__HUIYI_I18N__ && window.__HUIYI_I18N__.sepay) ? window.__HUIYI_I18N__.sepay : {};
    let text = dict[key] || fallback;
    for (const [k, v] of Object.entries(vars)) {
      text = text.replace(new RegExp(`\\{${k}\\}`, 'g'), v);
    }
    return text;
  }

  class SepayPaymentService {
    constructor(config = {}) {
      this.baseUrl = (config.baseUrl || '').replace(/\/+$/, '');
      this.stateEndpoint = config.stateEndpoint || '/payment-state.php';
      this.groupEndpoint = config.groupEndpoint || '/group/state.php';
      this.timeoutMs = config.timeoutMs && config.timeoutMs > 0 ? config.timeoutMs : 8000;
      this.pollIntervalMs = config.pollIntervalMs && config.pollIntervalMs > 0 ? config.pollIntervalMs : 3500;
    }

    async fetchWithTimeout(url, signal) {
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), this.timeoutMs);

      if (signal) {
        signal.addEventListener('abort', () => controller.abort(), { once: true });
      }

      try {
        const res = await fetch(url, {
          method: 'GET',
          headers: {
            Accept: 'application/json',
            'Cache-Control': 'no-store, no-cache',
          },
          credentials: 'same-origin',
          signal: controller.signal,
        });

        if (!res.ok && res.status !== 401 && res.status !== 403) {
          throw new Error(`HTTP error: ${res.status}`);
        }

        return await res.json();
      } catch (err) {
        if (err.name === 'AbortError') {
          throw new Error(`Hết thời gian chờ (${this.timeoutMs}ms).`);
        }
        throw err;
      } finally {
        clearTimeout(timer);
      }
    }

    generateVietQRUrl(params) {
      const acc = (params.accountNumber || '').replace(/[^A-Za-z0-9]/g, '');
      const bank = (params.bankCode || 'VCB').trim();
      const des = (params.description || '').trim();
      const amount = Math.max(0, Math.round(Number(params.amount) || 0));
      const template = params.template || 'compact';

      return `https://qr.sepay.vn/img?acc=${encodeURIComponent(acc)}&bank=${encodeURIComponent(
        bank
      )}${amount > 0 ? `&amount=${amount}` : ''}&des=${encodeURIComponent(des)}&template=${encodeURIComponent(
        template
      )}`;
    }

    async checkOrderPaymentState(orderNo, signal, directUrl) {
      const url = directUrl || `${this.baseUrl}${this.stateEndpoint}?order=${encodeURIComponent(orderNo)}`;
      return await this.fetchWithTimeout(url, signal);
    }

    async checkGroupState(roomId, signal) {
      const url = `${this.baseUrl}${this.groupEndpoint}?id=${encodeURIComponent(roomId)}`;
      return await this.fetchWithTimeout(url, signal);
    }

    getPollInterval() {
      return this.pollIntervalMs;
    }
  }

  class SepayPaymentManager {
    constructor(service, options = {}) {
      this.service = service;
      this.options = options;
      this.isPolling = false;
      this.pollTimer = null;
      this.countdownTimer = null;
      this.remainingSeconds = options.durationSeconds || 900; // 15 mins default
      this.isDestroyed = false;
      this.lastSignature = null;
    }

    init() {
      this.bindCopyButtons();
      this.bindQRModal();
      this.startCountdown();
      this.startPolling();

      window.addEventListener('visibilitychange', () => {
        if (!document.hidden && !this.isDestroyed) {
          this.pollOnce();
        }
      });

      window.addEventListener('online', () => {
        if (!this.isDestroyed) {
          this.pollOnce();
        }
      });
    }

    startPolling() {
      if (this.isPolling || this.isDestroyed) return;
      this.isPolling = true;

      const interval = this.service.getPollInterval();
      const pollLoop = async () => {
        if (!this.isPolling || this.isDestroyed) return;
        await this.pollOnce();
        if (this.isPolling && !this.isDestroyed) {
          this.pollTimer = setTimeout(pollLoop, interval);
        }
      };

      this.pollTimer = setTimeout(pollLoop, interval);
    }

    stopPolling() {
      this.isPolling = false;
      if (this.pollTimer) {
        clearTimeout(this.pollTimer);
        this.pollTimer = null;
      }
    }

    async pollOnce() {
      if (this.isDestroyed) return;

      try {
        if (this.options.orderNo) {
          const res = await this.service.checkOrderPaymentState(this.options.orderNo, null, this.options.stateUrl);
          if (res && res.ok) {
            this.handleOrderState(res);
          }
        } else if (this.options.groupId) {
          const res = await this.service.checkGroupState(this.options.groupId);
          if (res && res.ok) {
            this.handleGroupState(res);
          }
        }
      } catch (err) {
        // Quietly retry on next interval
      }
    }

    handleOrderState(res) {
      const isPaid = res.status === 'confirmed' || res.financial_state === 'confirmed' || res.status === 'overpaid' || res.paid === true;

      if (typeof this.options.onPaymentUpdated === 'function') {
        this.options.onPaymentUpdated(res);
      }

      if (isPaid) {
        this.stopPolling();
        this.stopCountdown();
        this.renderSuccessState(res);
        if (typeof this.options.onPaymentConfirmed === 'function') {
          this.options.onPaymentConfirmed(res);
        }
      }
    }

    handleGroupState(res) {
      if (typeof this.options.onPaymentUpdated === 'function') {
        this.options.onPaymentUpdated(res);
      }

      if (res.signature && this.lastSignature && res.signature !== this.lastSignature) {
        // Reload room UI if group state updated
        const roomContainer = document.querySelector('[data-group-room]');
        if (roomContainer && res.html) {
          roomContainer.innerHTML = res.html;
        }
      }
      this.lastSignature = res.signature || null;

      if (res.all_confirmed || res.can_checkout) {
        this.stopPolling();
        this.stopCountdown();
        if (typeof this.options.onPaymentConfirmed === 'function') {
          this.options.onPaymentConfirmed(res);
        }
      }
    }

    startCountdown() {
      const countdownEl = document.querySelector('[data-sepay-countdown]');
      if (!countdownEl) return;

      const updateDisplay = () => {
        if (this.remainingSeconds <= 0) {
          this.stopCountdown();
          countdownEl.textContent = '00:00';
          const statusNote = document.querySelector('[data-sepay-status-note]');
          if (statusNote) {
            statusNote.textContent = t('sepay_qr_expired', 'Mã QR đã hết thời gian chờ. Vui lòng tải lại trang để tạo mã mới.');
            statusNote.classList.add('is-expired');
          }
          return;
        }
        const m = Math.floor(this.remainingSeconds / 60);
        const s = this.remainingSeconds % 60;
        countdownEl.textContent = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
      };

      updateDisplay();

      this.countdownTimer = setInterval(() => {
        if (this.isDestroyed) return;
        this.remainingSeconds--;
        updateDisplay();
      }, 1000);
    }

    stopCountdown() {
      if (this.countdownTimer) {
        clearInterval(this.countdownTimer);
        this.countdownTimer = null;
      }
    }

    bindCopyButtons() {
      document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-sepay-copy]');
        if (!btn) return;
        const text = btn.dataset.sepayCopy || '';
        if (!text) return;

        try {
          if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
          } else {
            const input = document.createElement('input');
            input.value = text;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            input.remove();
          }
          this.showToast(t('sepay_copied_toast', 'Đã sao chép: {text}', { text }));
        } catch (_) {
          window.prompt(t('sepay_prompt_copy', 'Sao chép nội dung:'), text);
        }
      });
    }

    bindQRModal() {
      document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-sepay-qr-modal]');
        if (!trigger) return;
        const src = trigger.dataset.sepayQrSrc || trigger.src || trigger.getAttribute('href');
        if (!src) return;

        e.preventDefault();
        const modal = document.createElement('div');
        modal.className = 'sepay-modal-overlay';
        modal.innerHTML = `
          <div class="sepay-modal-content">
            <button type="button" class="sepay-modal-close" aria-label="${t('gps_close', 'Đóng')}">&times;</button>
            <img src="${src}" alt="VietQR" class="sepay-modal-qr-img" />
            <p class="sepay-modal-hint">${t('sepay_scan_hint', 'Quét mã bằng ứng dụng Ngân hàng bất kỳ')}</p>
          </div>
        `;
        modal.querySelector('.sepay-modal-close')?.addEventListener('click', () => modal.remove());
        modal.addEventListener('click', (event) => {
          if (event.target === modal) modal.remove();
        });
        document.body.appendChild(modal);
      });
    }

    showToast(msg) {
      let toast = document.querySelector('.sepay-toast');
      if (!toast) {
        toast = document.createElement('div');
        toast.className = 'sepay-toast';
        document.body.appendChild(toast);
      }
      toast.textContent = msg;
      toast.classList.add('is-visible');
      setTimeout(() => {
        toast?.classList.remove('is-visible');
      }, 2500);
    }

    renderSuccessState(res) {
      const panel = document.querySelector('[data-sepay-panel]');
      if (!panel) return;

      this.stopPolling();
      this.stopCountdown();

      panel.classList.add('sepay-payment-confirmed');
      panel.innerHTML = `
        <div class="sepay-success-card">
          <div class="sepay-success-icon" aria-hidden="true">✓</div>
          <h3 class="sepay-success-title">${t('sepay_success_title', 'Đã nhận được thanh toán!')}</h3>
          <p class="sepay-success-desc">${t('sepay_success_desc_live', 'Hệ thống SePay đã xác nhận giao dịch thành công. Cửa hàng bắt đầu chế biến và giao hàng cho bạn.')}</p>
          <div class="sepay-success-details">
            <div><span>${t('sepay_order_number', 'Mã đơn hàng:')}</span> <strong>${this.options.orderNo || ''}</strong></div>
            <div><span>${t('sepay_status', 'Trạng thái:')}</span> <b class="badge-success">${t('sepay_status_paid', 'Đã thanh toán (PAID)')}</b></div>
          </div>
        </div>
      `;

      this.showToast(t('sepay_success_toast', 'Thanh toán thành công qua SePay!'));

      // Smoothly redirect to success page after 1.5s
      setTimeout(() => {
        const targetUrl = (this.options && this.options.successUrl) ? this.options.successUrl : `${this.service.baseUrl || ''}/success.php`;
        window.location.href = targetUrl;
      }, 1500);
    }

    destroy() {
      this.isDestroyed = true;
      this.stopPolling();
      this.stopCountdown();
    }
  }

  // Auto-bind on DOM ready
  document.addEventListener('DOMContentLoaded', () => {
    const individualPanel = document.querySelector('[data-sepay-panel]');
    if (individualPanel) {
      // If the order is already confirmed on server, do not poll or overwrite server-rendered details
      if (individualPanel.dataset.isConfirmed === '1' || individualPanel.querySelector('.sepay-success-card')) {
        return;
      }

      const orderNo = individualPanel.dataset.orderNo || '';
      const stateUrl = individualPanel.dataset.paymentStateUrl || '';
      const durationSeconds = individualPanel.dataset.durationSeconds !== undefined
        ? Math.max(0, parseInt(individualPanel.dataset.durationSeconds, 10))
        : 900;
      const service = new SepayPaymentService();
      const manager = new SepayPaymentManager(service, {
        orderNo,
        stateUrl,
        durationSeconds,
        containerSelector: '[data-sepay-panel]',
        onPaymentConfirmed: () => {
          // Success handled in renderSuccessState
        }
      });
      manager.init();
      window.currentSepayManager = manager;
    }

    const groupPanel = document.querySelector('[data-sepay-group-panel]');
    if (groupPanel) {
      // For group rooms, group-order.js handles room-level state polling.
      // SePay manager binds copy buttons and QR zoom modal for member VietQR cards.
      const service = new SepayPaymentService();
      const manager = new SepayPaymentManager(service, {
        containerSelector: '[data-sepay-group-panel]',
      });
      manager.bindCopyButtons();
      manager.bindQRModal();
      window.currentSepayGroupManager = manager;
    }
  });

  window.SepayPaymentService = SepayPaymentService;
  window.SepayPaymentManager = SepayPaymentManager;
})();
