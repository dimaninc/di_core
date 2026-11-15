<?php

namespace diCore\Payment\AlfaBank;

/**
 * Failed RBS gateway call, one of three kinds:
 * - transport – no usable answer (cURL error, not 200, not JSON); a retry may help;
 * - bankCode – the bank refused the request (its errorCode);
 * - neither – the bank said "ok" but the answer lacks what the method promises (register.do without
 *   orderId/formUrl). The order may already exist in the bank: a repeat of register.do gets code 1.
 * The message carries no credentials and no request data.
 */
class RequestError extends \Exception
{
    // register.do: an order with this orderNumber is already registered (BPC docs, register.do, the
    // example "Order number is duplicated, order with given order number is processed already").
    // Other methods use code 1 for other things.
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
