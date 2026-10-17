import {
  PaymentStateResponse,
  GroupStateResponse,
  SepayServiceConfig,
  VietQRConfig
} from './types';

/**
 * Service encapsulating SePay VietQR payment endpoints,
 * polling, and asynchronous state verification.
 */
export class SepayPaymentService {
  private readonly baseUrl: string;
  private readonly stateEndpoint: string;
  private readonly groupEndpoint: string;
  private readonly timeoutMs: number;
  private readonly pollIntervalMs: number;

  constructor(config: SepayServiceConfig = {}) {
    this.baseUrl = (config.baseUrl || '').replace(/\/+$/, '');
    this.stateEndpoint = config.stateEndpoint || '/payment-state.php';
    this.groupEndpoint = config.groupEndpoint || '/group/state.php';
    this.timeoutMs = config.timeoutMs && config.timeoutMs > 0 ? config.timeoutMs : 8000;
    this.pollIntervalMs = config.pollIntervalMs && config.pollIntervalMs > 0 ? config.pollIntervalMs : 4000;
  }

  /**
   * Helper to perform HTTP GET with timeout and AbortSignal support.
   */
  private async fetchWithTimeout<T>(url: string, externalSignal?: AbortSignal): Promise<T> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeoutMs);

    if (externalSignal) {
      if (externalSignal.aborted) {
        clearTimeout(timer);
        throw new Error('Request was aborted prior to execution.');
      }
      externalSignal.addEventListener('abort', () => controller.abort(), { once: true });
    }

    try {
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          Accept: 'application/json',
          'Cache-Control': 'no-store, no-cache',
        },
        credentials: 'same-origin',
        signal: controller.signal,
      });

      if (!response.ok && response.status !== 401 && response.status !== 403) {
        throw new Error(`Payment HTTP error: ${response.status} ${response.statusText}`);
      }

      const data = (await response.json()) as T;
      return data;
    } catch (error: any) {
      if (error.name === 'AbortError') {
        throw new Error(`Payment status request timed out after ${this.timeoutMs}ms.`);
      }
      throw error;
    } finally {
      clearTimeout(timer);
    }
  }

  /**
   * Generate dynamic SePay VietQR URL.
   */
  public generateVietQRUrl(config: VietQRConfig): string {
    const cleanAcc = (config.accountNumber || '').replace(/[^A-Za-z0-9]/g, '');
    const cleanBank = (config.bankCode || 'VCB').trim();
    const cleanDes = (config.description || '').trim();
    const amount = Math.max(0, Math.round(config.amount));
    const template = config.template || 'compact';

    return `https://qr.sepay.vn/img?acc=${encodeURIComponent(cleanAcc)}&bank=${encodeURIComponent(
      cleanBank
    )}${amount > 0 ? `&amount=${amount}` : ''}&des=${encodeURIComponent(cleanDes)}&template=${encodeURIComponent(
      template
    )}`;
  }

  /**
   * Check order payment state from payment-state.php
   */
  public async checkOrderPaymentState(orderNo: string, signal?: AbortSignal, directUrl?: string): Promise<PaymentStateResponse> {
    const targetUrl = directUrl || `${this.baseUrl}${this.stateEndpoint}?order=${encodeURIComponent(orderNo)}`;
    return await this.fetchWithTimeout<PaymentStateResponse>(targetUrl, signal);
  }

  /**
   * Check group room state from group/state.php
   */
  public async checkGroupState(roomId: string, signal?: AbortSignal): Promise<GroupStateResponse> {
    const targetUrl = `${this.baseUrl}${this.groupEndpoint}?id=${encodeURIComponent(roomId)}`;
    return await this.fetchWithTimeout<GroupStateResponse>(targetUrl, signal);
  }

  public getPollInterval(): number {
    return this.pollIntervalMs;
  }
}
