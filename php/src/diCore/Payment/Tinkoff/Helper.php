<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.12.2017
 * Time: 17:11
 * @link https://oplata.tinkoff.ru/landing/develop/
 * @link https://oplata.tinkoff.ru/landing/develop/documentation
 */

namespace diCore\Payment\Tinkoff;

use diCore\Entity\PaymentDraft\Model as Draft;
use diCore\Helper\ArrayHelper;
use diCore\Payment\BaseHelper;
use diCore\Payment\System;

class Helper extends BaseHelper
{
    const system = System::tinkoff;

    /** @var MerchantApi */
    protected $api;

    /** @var Draft */
    protected $draft;

    public function initDraft(callable $getDraftCallback)
    {
        $draftId = \diRequest::request('OrderId', 0);
        $amount = \diRequest::request('Amount', 0) / 100;

        $this->draft = $getDraftCallback($draftId, $amount);

        return $this;
    }

    protected function getApi()
    {
        if (!$this->api) {
            $this->api = new MerchantApi(static::getLogin(), static::getPassword());
        }

        return $this->api;
    }

    /**
     * @param Draft $draft
     * @param array $opts
     * @return string
     */
    public function getFormUri(Draft $draft, $opts = [])
    {
        $opts = extend(
            [
                'amount' => $draft->getAmount(),
                'userId' => $draft->getUserId(),
                'draftId' => $draft->getId(),
                'description' => '',
                'customerEmail' => '',
                'customerPhone' => '',
                'paymentVendor' => '',
                'additionalParams' => [],
            ],
            $opts
        );

        $params = [
            'OrderId' => $opts['draftId'],
            'Amount' => sprintf('%d', $opts['amount'] * 100),
            'Description' => $opts['description'],
            'Language' => 'ru',
            'DATA' => $opts['additionalParams'],
        ];

        $response = $this->getApi()->init(array_filter($params));

        static::log("Init:\n" . print_r($params, true));
        static::log("Response:\n" . print_r($response, true));

        if ($this->getApi()->getError()) {
            throw new \Exception(
                'Tinkoff init error: ' . $this->getApi()->getError()
            );
        }

        $url = $this->getApi()->getPaymentUrl();

        if (!is_string($url) || $url === '') {
            throw new \Exception(
                'Tinkoff init returned no PaymentURL. Response: ' .
                    (is_string($response) ? $response : var_export($response, true))
            );
        }

        // Neutral post-Init hook (projects may persist the PaymentId for an
        // out-of-band GetState reconciler — T-Bank does NOT push a notification
        // for an on-form card decline). MUST never affect the redirect.
        try {
            $this->afterInit($draft, $this->getApi()->paymentId);
        } catch (\Throwable $e) {
            static::log('afterInit failed: ' . $e->getMessage());
        }

        return $url;
    }

    /**
     * Fired after a successful Init, with T-Bank's PaymentId. No-op by default —
     * override in a project to persist it. Never throws into the payment flow
     * (the caller swallows).
     *
     * @param string|int|null $paymentId
     */
    protected function afterInit(Draft $draft, $paymentId)
    {
        return $this;
    }

    /**
     * Query the current state of a payment via the GetState API.
     *
     * Parses the raw response directly (GetState returns the JSON body) instead
     * of relying on MerchantApi's success/error flags — those key off the
     * top-level ErrorCode, which conflates "GetState request failed" with "the
     * payment was declined". Returns null on a transport / decode failure.
     *
     * @return array{Status:?string,ErrorCode:?string,Message:?string,Details:?string,Success:mixed,raw:array}|null
     */
    public function getPaymentState($paymentId): ?array
    {
        try {
            $raw = $this->getApi()->getState(['PaymentId' => (string) $paymentId]);
        } catch (\Throwable $e) {
            static::log('GetState threw: ' . $e->getMessage());

            return null;
        }

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $json = json_decode($raw, true);

        if (!is_array($json)) {
            return null;
        }

        return [
            'Status' => $json['Status'] ?? null,
            'ErrorCode' => $json['ErrorCode'] ?? null,
            'Message' => $json['Message'] ?? null,
            'Details' => $json['Details'] ?? null,
            'Success' => $json['Success'] ?? null,
            'raw' => $json,
        ];
    }

    public function generateToken($params)
    {
        foreach ($params as $key => &$param) {
            if (gettype($param) === 'boolean') {
                $param = $param ? 'true' : 'false';
            }

            if (!is_scalar($param)) {
                unset($params[$key]);
            }
        }

        unset($params['Token']);
        // unset($params['Data']);
        $params['Password'] = static::getPassword();
        ksort($params);

        $line = join('', $params);
        $hash = hash('sha256', $line);

        // self::log('params: ' . print_r($params, true));
        // self::log('line: ' . $line);

        return $hash;
    }

    public function checkToken($params)
    {
        $token = ArrayHelper::get($params, 'Token');
        $generatedToken = $this->generateToken($params);

        // self::log('Generated token: ' . $generatedToken . ', received token: ' . $token);

        return $token && $generatedToken === $token;
    }

    public function success(callable $successCallback)
    {
        try {
            if (\diRequest::request('Success') === 'true') {
                self::log('Success method OK');
            } else {
                throw new \Exception(
                    'Success method not OK: ' . print_r($_GET, true)
                );
            }

            return $successCallback($this);
        } catch (\Exception $e) {
            self::log('Error during `success`: ' . $e->getMessage());

            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function fail(callable $failCallback)
    {
        self::log('Fail method OK: ' . print_r($_GET, true));

        return $failCallback($this);
    }

    public function tuneVendor(Draft $payment, $sourceStr)
    {
        $this->log("tuneVendor: $sourceStr");

        if (!$sourceStr) {
            return $this;
        }

        $s = strtolower($sourceStr);
        $map = [
            'сards' => Vendor::CARD,
            'sberpay' => Vendor::SBERPAY,
            'sbp' => Vendor::SBP,
            'qrsbp' => Vendor::SBP,
            'mirpay' => Vendor::MIR_PAY,
            'tinkoffpay' => Vendor::TPAY,
            'yandexpay' => Vendor::YANDEX_PAY,
        ];
        $vendor = $map[$s] ?? null;

        if (!$vendor) {
            return $this;
        }

        $payment->setVendor($vendor);

        return $this;
    }

}
