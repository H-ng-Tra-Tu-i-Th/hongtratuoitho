export type PaymentMode = 'individual' | 'group_together' | 'group_split';

export type PaymentFinancialState =
  | 'unpaid'
  | 'pending'
  | 'requested'
  | 'partial'
  | 'confirmed'
  | 'overpaid'
  | 'refund_pending'
  | 'refunded'
  | 'not_required'
  | 'rejected'
  | 'legacy';

export interface VietQRConfig {
  accountNumber: string;
  bankCode: string;
  amount: number;
  description: string;
  template?: 'compact' | 'qronly' | 'standee';
}

export interface BankAccountDetails {
  bankName: string;
  bankCode: string;
  accountNumber: string;
  accountName: string;
  recipientLabel?: string;
  qrUrl?: string;
}

export interface PaymentStateResponse {
  ok: boolean;
  signature?: string;
  html?: string;
  status?: PaymentFinancialState | string;
  financial_state?: PaymentFinancialState;
  received_amount?: number;
  balance_due?: number;
  message?: string;
  paid?: boolean;
}

export interface GroupMemberPaymentStatus {
  id: number;
  nickname: string;
  isHost: boolean;
  subtotal: number;
  due: number;
  receivedAmount: number;
  method: 'bank_transfer' | 'cash' | 'host_advance';
  status: 'pending' | 'requested' | 'paid' | 'rejected' | 'refunded';
  confirmed: boolean;
  canRequest: boolean;
  canConfirm: boolean;
}

export interface GroupStateResponse {
  ok: boolean;
  revision: number;
  html?: string;
  server_time: number;
  signature?: string;
  all_confirmed?: boolean;
  can_checkout?: boolean;
}

export interface SepayServiceConfig {
  baseUrl?: string;
  stateEndpoint?: string;
  groupEndpoint?: string;
  timeoutMs?: number;
  pollIntervalMs?: number;
}
