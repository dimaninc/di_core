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

    /**
     * SBP payload string of an already inited payment, via the GetQr API.
     *
     * That single string is both the QR picture's content and the bank-app deep
     * link, so it is what a caller needs to offer SBP at all.
     *
     * Parses the raw response directly (GetQr returns the JSON body) instead of
     * relying on MerchantApi's success/error flags – those key off the top-level
     * ErrorCode and conflate "the request failed" with "this payment has no QR".
     *
     * The string is returned only if it really is an SBP payload — see
     * isSbpPayload(): it is the payment destination, not a diagnostic.
     *
     * NEVER throws: a transport failure, an empty body, undecodable JSON,
     * Success missing or false all come back as null (and are logged). The
     * caller must be able to work without SBP.
     */
    public function getSbpPayload($paymentId): ?string
    {
        try {
            $raw = $this->getApi()->getQr([
                'PaymentId' => (string) $paymentId,
                'DataType' => 'PAYLOAD',
            ]);
        } catch (\Throwable $e) {
            static::log(
                'GetQr threw ' .
                    get_class($e) .
                    ': ' .
                    static::sanitizeForLog($e->getMessage())
            );

            return null;
        }

        if (!is_string($raw) || $raw === '') {
            // curl_exec() returns false on a transport failure and puts the
            // reason into the api's error — without it a timeout, a DNS and a
            // TLS failure are one indistinguishable line in the log.
            $reason = $raw === false ? $this->getApi()->getError() : '';

            static::log(
                'GetQr returned ' .
                    ($raw === false ? 'no response' : 'an empty response') .
                    ($reason ? ': ' . static::sanitizeForLog($reason) : '')
            );

            return null;
        }

        $json = json_decode($raw, true);

        if (!is_array($json)) {
            static::log(
                'GetQr returned undecodable JSON: ' . static::sanitizeForLog($raw)
            );

            return null;
        }

        // No Success is not a success: an intermediate proxy / WAF answering
        // with its own JSON must not be able to pass off a Data of its own.
        if (!in_array($json['Success'] ?? null, [true, 'true', 1, '1'], true)) {
            static::log(
                'GetQr was not successful: ' . static::sanitizeForLog($raw)
            );

            return null;
        }

        $payload = $json['Data'] ?? null;

        if (!static::isSbpPayload($payload)) {
            static::log(
                'GetQr returned no usable SBP payload: ' .
                    static::sanitizeForLog($raw)
            );

            return null;
        }

        return $payload;
    }

    /**
     * The payload becomes the QR the payer scans and the deep link they tap,
     * i.e. it IS the payment destination — so "any https:// string the gateway
     * sent back" is not enough of a check. NSPK serves them all from nspk.ru;
     * a WAF interstitial, a tampered response or a stray SVG is not a payment
     * link and SBP is better switched off (the caller falls back to the web
     * form) than pointed somewhere else.
     *
     * @param mixed $payload
     */
    protected static function isSbpPayload($payload): bool
    {
        // printable ASCII only: no control chars, no whitespace, no empty string
        if (!is_string($payload) || !preg_match('/^[\x21-\x7e]+$/', $payload)) {
            return false;
        }

        $parts = parse_url($payload);

        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        // https://qr.nspk.ru@evil.tld/ parses with host=evil.tld, so the host
        // check below covers it — but userinfo has no business here at all
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower(rtrim($parts['host'] ?? '', '.'));

        foreach (static::getSbpPayloadDomains() as $domain) {
            $domain = strtolower($domain);

            if (
                $host === $domain ||
                substr($host, -strlen($domain) - 1) === '.' . $domain
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Domains an SBP payload may point at, itself and its subdomains. Override
     * in a project if its acquirer ever serves them from elsewhere.
     */
    protected static function getSbpPayloadDomains(): array
    {
        return ['nspk.ru'];
    }

    /**
     * A response body we did not authenticate goes into the payment log — it
     * must not be able to forge log lines with newlines, to grow the file
     * without bound, or to carry a request Token into it.
     */
    protected static function sanitizeForLog($text, $limit = 1000): string
    {
        $text = preg_replace(
            '/("(?:Token|Password)"\s*:\s*")[^"]*"/i',
            '$1***"',
            (string) $text
        );
        $text = preg_replace('/[\x00-\x1f\x7f]+/', ' ', $text);

        if (mb_strlen($text) > $limit) {
            $text = mb_substr($text, 0, $limit) . '… [truncated]';
        }

        return $text;
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
