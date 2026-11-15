<?php

namespace diCore\Payment\AlfaBank;

/**
 * Failed RBS gateway call. Either the answer never arrived (transport: cURL error, not JSON) or the bank
 * refused the request (bankCode = its errorCode). The message carries no credentials and no request data.
 */
class RequestError extends \Exception
{
    // register.do: an order with this orderNumber is already registered – a repeat gets the same refusal
    const DUPLICATE_ORDER = '1';

    public function __construct(
        string $message,
        public readonly ?string $bankCode = null,
        public readonly bool $transport = false
    ) {
        parent::__construct($message);
    }

    public function isDuplicateOrder(): bool
    {
        return $this->bankCode === self::DUPLICATE_ORDER;
    }
}
