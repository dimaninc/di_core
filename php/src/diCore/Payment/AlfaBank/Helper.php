<?php

namespace diCore\Payment\AlfaBank;

use diCore\Helper\ArrayHelper;
use diCore\Payment\BaseHelper;
use diCore\Payment\System;

/**
 * Alfa-Bank internet acquiring helper.
 *
 * Alfa-Bank runs on the RBS/BPC gateway — the same REST protocol as Sberbank
 * (register.do / getOrderStatusExtended.do / refund.do). Implemented over raw
 * HTTP so no extra Composer dependency is required.
 *
 * One-stage scheme only (no hold/two-step): register → pay → funds captured.
 *
 * Credentials live in a project subclass `…\Payment\AlfaBank\Settings`
 * (resolved via BaseHelper::create()), which should pull login/password from
 * Configuration rather than hardcoding secrets.
 */
class Helper extends BaseHelper
{
    const system = System::alfabank;

    // RBS REST endpoints (one-stage).
    const apiUri = 'https://payment.alfabank.ru/payment/rest/';
    const apiUriDemo = 'https://web.rbsuat.com/ab/rest/';

    const currencyRub = 643; // ISO 4217 numeric

    /** Override in the Settings subclass to drive test mode from config. */
    protected static function isTestMode()
    {
        return static::testMode;
    }

    protected static function getApiUri()
    {
        return static::isTestMode() ? static::apiUriDemo : static::apiUri;
    }

    /**
     * Registers an order and returns the URL of the bank payment form to
     * redirect the customer to.
     *
     * @param \diCore\Entity\PaymentDraft\Model $draft
     * @param array $opts
     * @return string
     */
    public function getFormUri(
        \diCore\Entity\PaymentDraft\Model $draft,
        $opts = []
    ) {
        $opts = extend(
            [
                'amount' => $draft->getAmount(),
                'orderNumber' => $draft->getId(),
                'returnUrl' => $this->defaultReturnUrl($draft),
                'failUrl' => '',
                'description' => '',
                'customerEmail' => '',
                'currency' => static::currencyRub,
            ],
            $opts
        );

        $params = [
            'orderNumber' => $opts['orderNumber'],
            'amount' => static::toMinorUnits($opts['amount']),
            'currency' => $opts['currency'],
            'returnUrl' => $opts['returnUrl'],
        ];

        if ($opts['failUrl']) {
            $params['failUrl'] = $opts['failUrl'];
        }
        if ($opts['description']) {
            $params['description'] = mb_substr($opts['description'], 0, 512);
        }
        if ($opts['customerEmail']) {
            $params['email'] = $opts['customerEmail'];
        }

        $response = $this->request('register.do', $params);

        return ArrayHelper::get($response, 'formUrl');
    }

    /**
     * Extended order status (for callbacks / polling). orderStatus === 2 means
     * the payment is fully completed.
     */
    public function getOrderStatus($bankOrderId)
    {
        return $this->request('getOrderStatusExtended.do', [
            'orderId' => $bankOrderId,
        ]);
    }

    /**
     * Full or partial refund of a completed order. $amount is in rubles; pass 0
     * for a full refund.
     */
    public function refund($bankOrderId, $amount = 0)
    {
        return $this->request('refund.do', [
            'orderId' => $bankOrderId,
            'amount' => static::toMinorUnits($amount),
        ]);
    }

    protected function defaultReturnUrl(\diCore\Entity\PaymentDraft\Model $draft)
    {
        $target = $draft->getTargetModel();

        return \diPaths::defaultHttp() .
            ($target && $target->exists() ? $target->getHref() : '/');
    }

    /** Rubles → kopecks (RBS expects the amount in minor currency units). */
    protected static function toMinorUnits($amount)
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * Sends a form-encoded POST to the RBS gateway and returns the decoded JSON
     * response as an array. Credentials are injected from the Settings subclass.
     */
    protected function request($method, array $params)
    {
        $params = extend(
            [
                'userName' => static::getLogin(),
                'password' => static::getPassword(),
            ],
            $params
        );

        $url = static::getApiUri() . $method;

        static::log("$method request:\n" . print_r(
            array_merge($params, ['password' => '***']),
            true
        ));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            static::log("$method curl error: $error");

            throw new \Exception("Alfa-Bank request failed: $error");
        }

        static::log("$method response:\n$raw");

        $response = json_decode($raw, true) ?: [];

        // RBS reports business errors via errorCode (0/absent = ok).
        $errorCode = ArrayHelper::get($response, 'errorCode');
        if ($errorCode !== null && (int) $errorCode !== 0) {
            $message = ArrayHelper::get($response, 'errorMessage', 'Unknown error');

            throw new \Exception("Alfa-Bank error #$errorCode: $message");
        }

        return $response;
    }
}
