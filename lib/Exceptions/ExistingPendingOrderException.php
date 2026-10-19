<?php

namespace Lib\Exceptions;

use RuntimeException;

class ExistingPendingOrderException extends RuntimeException
{
    private string $orderNo;
    private float $amount;
    private ?string $createdAt;

    public function __construct(string $orderNo, float $amount = 0.0, ?string $createdAt = null, string $message = '')
    {
        $this->orderNo = $orderNo;
        $this->amount = $amount;
        $this->createdAt = $createdAt;

        if ($message === '') {
            $message = function_exists('client_t')
                ? client_t('pending_order_error_exception', ['order_no' => $orderNo])
                : sprintf(
                    'Tài khoản đang có đơn hàng #%s chờ thanh toán. Vui lòng hoàn tất hoặc hủy đơn này trước khi tạo đơn mới.',
                    $orderNo
                );
        }

        parent::__construct($message, 409);
    }

    public function getOrderNo(): string
    {
        return $this->orderNo;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
}
