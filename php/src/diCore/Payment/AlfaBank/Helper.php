<?php

namespace diCore\Payment\AlfaBank;

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
 * Credentials and endpoint – either all from constructor options (see $options),
 * or – legacy – from a project subclass `…\Payment\AlfaBank\Settings` resolved
 * via BaseHelper::create(): getLogin()/getPassword() give the pair,
 * isTestMode() picks only the endpoint (the base getLogin() looks at the
 * `testMode` constant, not at isTestMode() – keep them in step in Settings).
 * The `testMode` option needs login and password options: alone it would send
 * the Settings pair to the other endpoint.
 *
 * Every failed call throws RequestError (its three kinds are described there).
 */
class Helper extends BaseHelper
{
    const system = System::alfabank;

    // RBS REST endpoints (one-stage).
    const apiUri = 'https://payment.alfabank.ru/payment/rest/';
    const apiUriDemo = 'https://alfa.rbsuat.com/payment/rest/';

    const currencyRub = 643; // ISO 4217 numeric

    // Two bank calls in a row (another bank first, then this one) must fit a 60 s queue job
    const CONNECT_TIMEOUT_SEC = 5;
    const TIMEOUT_SEC = 15;

    /**
     * login, password; testMode (bool) – test or prod endpoint; apiUri – overrides both (another
     * test stand); caBundle – CURLOPT_CAINFO path, empty – the host store.
     */
    protected $options = [
        'onSuccessPayment' => null,
        'login' => null,
        'password' => null,
        'testMode' => null,
        'apiUri' => null,
        'caBundle' => null,
    ];

    public function __construct($options = [])
    {
        parent::__construct($options);

        if ($this->options['testMode'] !== null
            && ($this->options['login'] === null || $this->options['password'] === null)) {
            throw new \InvalidArgumentException('Alfa-Bank: the testMode option needs the login and password options');
        }
    }

    /** Override in the Settings subclass to drive the endpoint from config. */
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
                'returnUrl' => '',
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
            // The default only when none is given: it reads the draft's target
            'returnUrl' => $opts['returnUrl'] ?: $this->defaultReturnUrl($draft),
        ];

        if ($opts['failUrl']) {
            $params['failUrl'] = $opts['failUrl'];
        }
        if ($opts['description']) {
            $params['description'] = $opts['description'];
        }
        if ($opts['customerEmail']) {
            $params['email'] = $opts['customerEmail'];
        }

        return $this->register($params)['formUrl'];
    }

    /**
     * register.do with the amount already in kopecks. orderId and formUrl of the answer are non-empty
     * strings – otherwise RequestError, never a silent null.
     *
     * @param array $params orderNumber, amount (kopecks), returnUrl plus any optional register.do
     *        parameter (failUrl, description, language, dynamicCallbackUrl, email…)
     * @return array{orderId: string, formUrl: string}
     */
    public function register(array $params)
    {
        $params += ['currency' => static::currencyRub];

        if (isset($params['description'])) {
            $params['description'] = mb_substr((string) $params['description'], 0, 512);
        }

        $response = $this->request('register.do', $params);
        $orderId = $response['orderId'] ?? null;
        $formUrl = $response['formUrl'] ?? null;

        if (!is_string($orderId) || $orderId === '' || !is_string($formUrl) || $formUrl === '') {
            // request() has already logged «ok» – without this line the log would contradict the exception
            static::log('register.do' . static::logLabel($params) . ': no orderId or formUrl in the answer');

            throw new RequestError('Alfa-Bank register.do: no orderId or formUrl in the answer', null, false, 'register.do');
        }

        return $response;
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

    /**
     * Checksum of a callback signed with a symmetric key: every parameter except checksum and
     * sign_alias, sorted by name, as "name;value;" pairs, HMAC-SHA256, upper-case hex.
     *
     * @param array<string, string> $params callback parameters as received (query or form body, decoded)
     */
    public static function callbackChecksum(array $params, string $key): string
    {
        unset($params['checksum'], $params['sign_alias']);
        ksort($params, SORT_STRING);

        $data = '';
        foreach ($params as $name => $value) {
            $data .= $name . ';' . $value . ';';
        }

        return strtoupper(hash_hmac('sha256', $data, $key));
    }

    /**
     * The callback's checksum matches. No key, no checksum or a non-string value (a[]=…) – false.
     * Pass exactly what the bank sent: a parameter of your own in the callback address (?system=…) or one
     * added by a router changes the string and fails the check.
     */
    public static function isCallbackGenuine(array $params, string $key): bool
    {
        $checksum = $params['checksum'] ?? null;

        if ($key === '' || !is_string($checksum)) {
            return false;
        }

        foreach ($params as $value) {
            if (!is_string($value)) {
                return false;
            }
        }

        return hash_equals(static::callbackChecksum($params, $key), strtoupper($checksum));
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

    protected function login()
    {
        return (string) ($this->options['login'] ?? static::getLogin());
    }

    protected function password()
    {
        return (string) ($this->options['password'] ?? static::getPassword());
    }

    protected function apiUri()
    {
        if (!empty($this->options['apiUri'])) {
            return rtrim($this->options['apiUri'], '/') . '/';
        }

        if ($this->options['testMode'] !== null) {
            return $this->options['testMode'] ? static::apiUriDemo : static::apiUri;
        }

        return static::getApiUri();
    }

    /**
     * Form-encoded POST to the RBS gateway, the answer decoded. The log gets the method, orderNumber
     * and the bank's code – never parameters or answers: they carry the customer's email and the form address.
     */
    protected function request($method, array $params)
    {
        $order = static::logLabel($params);
        $params = ['userName' => $this->login(), 'password' => $this->password()] + $params;

        $raw = $this->send($this->apiUri() . $method, http_build_query($params));

        if (is_array($raw)) {
            static::log("$method$order: transport error: " . $raw['error']);

            throw new RequestError("Alfa-Bank $method: no answer: " . $raw['error'], null, true, $method);
        }

        $response = json_decode($raw, true);

        if (!is_array($response)) {
            static::log("$method$order: not JSON");

            throw new RequestError("Alfa-Bank $method: the answer is not JSON", null, true, $method);
        }

        // RBS reports business errors via errorCode (0/absent = ok).
        $errorCode = $response['errorCode'] ?? null;
        if ($errorCode !== null && (string) (is_scalar($errorCode) ? $errorCode : '?') !== '0') {
            $code = is_scalar($errorCode) ? mb_substr((string) $errorCode, 0, 8) : '?';
            $message = $response['errorMessage'] ?? 'Unknown error';
            $message = is_scalar($message) ? mb_substr((string) $message, 0, 200) : 'Unknown error';
            static::log("$method$order: error $code");

            throw new RequestError("Alfa-Bank $method error #$code: $message", $code, false, $method);
        }

        static::log("$method$order: ok");

        return $response;
    }

    /**
     * Which order a log line is about: our orderNumber or the bank's orderId (neither is personal data).
     * Anything but [0-9A-Za-z_-] becomes «?» – a line break in a number would forge a log line.
     */
    protected static function logLabel(array $params)
    {
        foreach (['orderNumber' => 'order', 'orderId' => 'bank order'] as $key => $label) {
            if (isset($params[$key]) && is_scalar($params[$key])) {
                return " $label " . preg_replace('/[^0-9A-Za-z_-]/', '?', mb_substr((string) $params[$key], 0, 64));
            }
        }

        return '';
    }

    /** cURL options of one call; separate from send() so a test can check them without the network. */
    protected function curlOptions(string $body)
    {
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT => static::CONNECT_TIMEOUT_SEC,
            CURLOPT_TIMEOUT => static::TIMEOUT_SEC,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            // No redirects: a redirected POST would carry the credentials wherever it points
            CURLOPT_FOLLOWLOCATION => false,
            // https only: an http apiUri in a config would send the password in clear text
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ];

        if (!empty($this->options['caBundle'])) {
            $options[CURLOPT_CAINFO] = $this->options['caBundle'];
        }

        return $options;
    }

    /**
     * One HTTP POST; seam for tests.
     *
     * @return string|array{error: string} the body of a 200 answer, or the transport error
     */
    protected function send(string $url, string $body)
    {
        $ch = curl_init($url);

        // As in Tinkoff\MerchantApi: a failed curl_init() is a transport failure, not a TypeError in curl_setopt_array()
        if ($ch === false) {
            return ['error' => 'curl_init failed'];
        }

        curl_setopt_array($ch, $this->curlOptions($body));

        $raw = curl_exec($ch);
        $error = curl_error($ch) ?: 'unknown transport error';
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['error' => mb_substr($error, 0, 200)];
        }

        if ($status !== 200) {
            return ['error' => "HTTP $status"];
        }

        return $raw;
    }
}
