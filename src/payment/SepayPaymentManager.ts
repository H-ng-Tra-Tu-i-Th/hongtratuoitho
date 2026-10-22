import { SepayPaymentService } from './SepayPaymentService';
import { PaymentMode, PaymentStateResponse, GroupStateResponse } from './types';

export interface SepayPaymentManagerOptions {
  orderNo?: string;
  stateUrl?: string;
  groupId?: string;
  mode?: PaymentMode;
  expectedAmount?: number;
  durationSeconds?: number;
  expiresAt?: number;
  containerSelector?: string;
  onPaymentConfirmed?: (response: PaymentStateResponse | GroupStateResponse) => void;
  onPaymentUpdated?: (response: PaymentStateResponse | GroupStateResponse) => void;
}

/**
 * Controller managing live polling, UI timers, countdown,
 * copy-to-clipboard, QR modal, and payment confirmation transitions.
 */
export class SepayPaymentManager {
  private readonly service: SepayPaymentService;
  private readonly options: SepayPaymentManagerOptions;
  private isPolling = false;
  private pollTimer: any = null;
  private countdownTimer: any = null;
  private expiresAt: number;
  private remainingSeconds: number;
  private isDestroyed = false;

  constructor(service: SepayPaymentService, options: SepayPaymentManagerOptions = {}) {
    this.service = service;
    this.options = options;
    const now = Math.floor(Date.now() / 1000);
    this.expiresAt = typeof options.expiresAt === 'number' && !isNaN(options.expiresAt) && options.expiresAt > 0
      ? options.expiresAt
      : (typeof options.durationSeconds === 'number' && !isNaN(options.durationSeconds) && options.durationSeconds > 0
        ? now + options.durationSeconds
        : 0);
    this.remainingSeconds = Math.max(0, Math.ceil(this.expiresAt - now));
  }

  public init(): void {
    this.bindCopyButtons();
    this.bindQRModal();
    this.startCountdown();
    this.startPolling();

    // Re-check when window is refocused
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

  public startPolling(): void {
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

  public stopPolling(): void {
    this.isPolling = false;
    if (this.pollTimer) {
      clearTimeout(this.pollTimer);
      this.pollTimer = null;
    }
  }

  public async pollOnce(): Promise<void> {
    if (this.isDestroyed) return;

    try {
      if (this.options.orderNo) {
        const res = await this.service.checkOrderPaymentState(this.options.orderNo, undefined, this.options.stateUrl);
        if (res.ok) {
          this.handleOrderState(res);
        }
      } else if (this.options.groupId) {
        const res = await this.service.checkGroupState(this.options.groupId);
        if (res.ok) {
          this.handleGroupState(res);
        }
      }
    } catch (err) {
      // Quietly log error on network hiccups, will retry next tick
      console.warn('[SepayPaymentManager] Polling warning:', err);
    }
  }

  private handleOrderState(res: PaymentStateResponse): void {
    const isPaid = res.status === 'confirmed' || res.financial_state === 'confirmed' || res.status === 'overpaid' || res.paid === true;
    const isCancelled = (res as any).cancelled || (res as any).order_status === 'cancelled' || res.status === 'cancelled';

    if (this.options.onPaymentUpdated) {
      this.options.onPaymentUpdated(res);
    }

    if (isCancelled) {
      this.stopPolling();
      this.stopCountdown();
      if ((res as any).html) {
        const panel = document.querySelector('[data-sepay-panel]');
        if (panel) {
          panel.outerHTML = (res as any).html;
        }
      }
      document.querySelectorAll('[data-cancel-order-form], .payment-actions-bar form').forEach((el) => el.remove());
      document.querySelectorAll('.payment-info-callout, .payment-page-hero').forEach((el) => el.remove());
      return;
    }

    if (isPaid) {
      this.stopPolling();
      this.stopCountdown();
      this.renderSuccessState(res);
      if (this.options.onPaymentConfirmed) {
        this.options.onPaymentConfirmed(res);
      }
    }
  }

  private handleGroupState(res: GroupStateResponse): void {
    if (this.options.onPaymentUpdated) {
      this.options.onPaymentUpdated(res);
    }

    if (res.all_confirmed || res.can_checkout) {
      this.stopPolling();
      this.stopCountdown();
      if (this.options.onPaymentConfirmed) {
        this.options.onPaymentConfirmed(res);
      }
    }
  }

  private startCountdown(): void {
    const countdownEl = document.querySelector('[data-sepay-countdown]');
    if (!countdownEl) return;

    const updateDisplay = () => {
      if (this.expiresAt) {
        this.remainingSeconds = Math.max(0, Math.ceil(this.expiresAt - Date.now() / 1000));
      }
      if (this.remainingSeconds <= 0) {
        this.stopCountdown();
        document.querySelectorAll('[data-cancel-order-form], .payment-actions-bar form').forEach((el) => {
          (el as HTMLElement).style.display = 'none';
        });
        countdownEl.textContent = '00:00';
        return;
      }
      const m = Math.floor(this.remainingSeconds / 60);
      const s = this.remainingSeconds % 60;
      countdownEl.textContent = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    };

    updateDisplay();

    this.countdownTimer = setInterval(() => {
      if (this.isDestroyed) return;
      updateDisplay();
    }, 1000);
  }

  private stopCountdown(): void {
    if (this.countdownTimer) {
      clearInterval(this.countdownTimer);
      this.countdownTimer = null;
    }
  }

  private bindCopyButtons(): void {
    document.addEventListener('click', async (e: Event) => {
      const btn = (e.target as HTMLElement).closest('[data-sepay-copy]') as HTMLElement | null;
      if (!btn) return;
      const text = btn.dataset.sepayCopy || '';
      if (!text) return;

      try {
        await navigator.clipboard.writeText(text);
        this.showToast('Đã sao chép: ' + text);
      } catch (_) {
        // Fallback prompt
        window.prompt('Sao chép nội dung:', text);
      }
    });
  }

  private bindQRModal(): void {
    document.addEventListener('click', (e: Event) => {
      const trigger = (e.target as HTMLElement).closest('[data-sepay-qr-modal]') as HTMLElement | null;
      if (!trigger) return;
      const src = trigger.dataset.sepayQrSrc || (trigger as HTMLImageElement).src;
      if (!src) return;

      const modal = document.createElement('div');
      modal.className = 'sepay-modal-overlay';
      modal.innerHTML = `
        <div class="sepay-modal-content">
          <button type="button" class="sepay-modal-close" aria-label="Đóng">&times;</button>
          <img src="${src}" alt="VietQR" class="sepay-modal-qr-img" />
          <p class="sepay-modal-hint">Quét mã bằng ứng dụng Ngân hàng bất kỳ</p>
        </div>
      `;
      modal.querySelector('.sepay-modal-close')?.addEventListener('click', () => modal.remove());
      modal.addEventListener('click', (event) => {
        if (event.target === modal) modal.remove();
      });
      document.body.appendChild(modal);
    });
  }

  private showToast(msg: string): void {
    let toast = document.querySelector('.sepay-toast') as HTMLElement | null;
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

  private renderSuccessState(res: PaymentStateResponse): void {
    const panel = document.querySelector('[data-sepay-panel]') as HTMLElement | null;
    if (!panel) return;

    this.stopPolling();
    this.stopCountdown();

    panel.classList.add('sepay-payment-confirmed');
    panel.innerHTML = `
      <div class="sepay-success-card">
        <div class="sepay-success-icon" aria-hidden="true">✓</div>
        <h3 class="sepay-success-title">Đã nhận được thanh toán!</h3>
        <p class="sepay-success-desc">Hệ thống SePay đã xác nhận giao dịch thành công. Cửa hàng bắt đầu chế biến và giao hàng cho bạn.</p>
        <div class="sepay-success-details">
          <span>Mã đơn: <strong>${this.options.orderNo || ''}</strong></span>
          <span>Trạng thái: <b class="badge-success">Đã thanh toán</b></span>
        </div>
      </div>
    `;

    this.showToast('Thanh toán thành công qua SePay!');

    setTimeout(() => {
      window.location.reload();
    }, 1500);
  }

  public destroy(): void {
    this.isDestroyed = true;
    this.stopPolling();
    this.stopCountdown();
  }
}
