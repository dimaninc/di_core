<?php

namespace diCore\Payment\AlfaBank;

/**
 * Failed RBS gateway call, one of three kinds:
 * - transport – no usable answer (cURL error, timeout, not 200, not JSON); the bank may still have
 *   done the operation;
 * - bankCode – the bank refused the request (its errorCode);
 * - neither – the bank said "ok" but the answer lacks what the method promises (register.do without
 *   orderId/formUrl). The order may already exist in the bank.
 * A blind repeat is safe only for getOrderStatusExtended.do and register.do: a repeat of a registered order
 * answers code 1 (isDuplicateOrder()) – no second order, but no form either: the helper finds orders by the
 * bank's orderId only, so that orderNumber is spent and the payment continues under a new one. Never for
 * refund.do: a repeated partial refund is a second refund – ask getOrderStatus() first
 * (paymentAmountInfo.refundedAmount).
 * The message carries no credentials and no request data.
 */
class RequestError extends \Exception
{
    // register.do: an order with this orderNumber is already registered (BPC docs, register.do, the
    // example "Order number is duplicated, order with given order number is processed already"). RBS
    // docs also give code 1 for an invalid order number, so «duplicate» is the likely reading, not a
    // proof: either way the same orderNumber will not get a form – take a new one; errorMessage tells
    // which. Other methods use code 1 for other things.
    const DUPLICATE_ORDER = '1';

    public function __construct(
        string $message,
        public readonly ?string $bankCode = null,
        public readonly bool $transport = false,
        public readonly string $method = ''
    ) {
        parent::__construct($message);
    }

    public function isDuplicateOrder(): bool
    {
        return $this->method === 'register.do' && $this->bankCode === self::DUPLICATE_ORDER;
    }
}
