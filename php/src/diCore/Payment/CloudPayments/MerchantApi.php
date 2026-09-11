<?php
/**
 * CloudPayments REST client.
 *
 * @link https://developers.cloudpayments.ru/
 */

namespace diCore\Payment\CloudPayments;

class MerchantApi
{
    /** Bound every gateway call — a hang must not pin an FPM worker. */
    const CONNECT_TIMEOUT_SEC = 5;
    const TIMEOUT_SEC = 20;

    /**
     * The channel carries the API Secret (Basic Auth) and brings back the URL
     * the payer is then sent to, so an unverified peer means anyone on the path
     * can redirect a payment. If a root is ever missing from the host's store,
     * the answer is an extra bundle via setCaBundle() — never this constant:
     * the bundle adds one anchor, the flag removes the protection for every
     * host. api.cloudpayments.ru is signed by a public CA, so no bundle is
     * expected here (unlike securepay.tinkoff.ru).
     */
    const VERIFY_TLS = true;

    const API_URL = 'https://api.cloudpayments.ru/';

    /** @var string|null extra CA bundle, null = the host's own store */
    private $caBundlePath = null;

    private $publicId;
    private $apiSecret;

    private $error;
    private $httpCode;
    private $response;
    private $model;
    private $success;
    private $message;

    public function __construct($publicId, $apiSecret)
    {
        $this->publicId = $publicId;
        $this->apiSecret = $apiSecret;
    }

    /** See Tinkoff\MerchantApi::setCaBundle() for why this is not system-wide. */
    public function setCaBundle($path)
    {
        $this->caBundlePath = $path !== null && $path !== '' ? (string) $path : null;

        return $this;
    }

    public function getCaBundle()
    {
        return $this->caBundlePath;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getHttpCode()
    {
        return $this->httpCode;
    }

    public function getResponse()
    {
        return $this->response;
    }

    /** Decoded `Model` of the last response, or null. */
    public function getModel()
    {
        return $this->model;
    }

    public function getModelField($name, $default = null)
    {
        return is_array($this->model) && array_key_exists($name, $this->model)
            ? $this->model[$name]
            : $default;
    }

    public function isSuccess()
    {
        return $this->success === true;
    }

    /** Gateway-side business message, e.g. why an order was refused. */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Creates an invoice and returns its payment page URL in Model.Url.
     *
     * @link https://developers.cloudpayments.ru/#sozdanie-scheta-dlya-otpravki-po-pochte
     */
    public function ordersCreate(array $args, $requestId = null)
    {
        return $this->buildQuery('orders/create', $args, $requestId);
    }

    public function ordersCancel(array $args, $requestId = null)
    {
        return $this->buildQuery('orders/cancel', $args, $requestId);
    }

    /**
     * @param string $path API method path, e.g. 'orders/create'
     * @param array $args JSON body
     * @param string|null $requestId idempotency key (X-Request-ID), kept by the
     *   gateway for an hour — pass one whenever a retry must not create a
     *   second invoice.
     * @return string|false raw response body, false on a transport failure
     */
    public function buildQuery($path, array $args, $requestId = null)
    {
        return $this->sendRequest(
            static::API_URL . ltrim($path, '/'),
            $args,
            $requestId
        );
    }

    private function sendRequest($url, array $args, $requestId = null)
    {
        $this->error = '';
        $this->httpCode = null;
        $this->resetResponseState();

        $body = json_encode($args, JSON_UNESCAPED_UNICODE);

        $curl = curl_init();

        if (!$curl) {
            $this->error = 'Unable to create a connection to ' . $url;

            return false;
        }

        $headers = ['Content-Type: application/json'];

        if ($requestId !== null && $requestId !== '') {
            $headers[] = 'X-Request-ID: ' . $requestId;
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, static::VERIFY_TLS);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, static::VERIFY_TLS ? 2 : 0);
        // No FOLLOWLOCATION: the response decides where a payer is sent, so a
        // redirect we follow blindly is a redirect an intermediary chose.
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt(
            $curl,
            CURLOPT_USERPWD,
            $this->publicId . ':' . $this->apiSecret
        );
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, static::CONNECT_TIMEOUT_SEC);
        curl_setopt($curl, CURLOPT_TIMEOUT, static::TIMEOUT_SEC);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        if ($this->caBundlePath !== null) {
            curl_setopt($curl, CURLOPT_CAINFO, $this->caBundlePath);
        }

        $out = curl_exec($curl);

        $this->response = $out;
        $this->httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($out === false) {
            $this->error =
                'cURL error: ' . (curl_error($curl) ?: 'unknown transport error');
            curl_close($curl);

            return false;
        }

        curl_close($curl);

        $this->handleResponse($out);

        return $out;
    }

    /**
     * Parses a response body into the instance state.
     *
     * Every field set here describes THIS response: they are cleared up front,
     * ahead of every early return, and the transport-failure branch above never
     * reaches this method at all. The api instance is cached per helper, so a
     * value carried over from an earlier call would quietly belong to another
     * payment — the exact bug Tinkoff\MerchantApi::handleResponse() documents.
     *
     * Note the gateway answers business refusals with HTTP 200 and
     * `Success: false`, so the status code alone proves nothing.
     */
    protected function handleResponse($out)
    {
        $json = json_decode($out, true);

        if (!is_array($json)) {
            $this->error =
                'Invalid JSON response from CloudPayments (HTTP ' .
                (string) $this->httpCode .
                '): ' .
                Helper::sanitizeForLog($out);

            return $this;
        }

        $this->success = ($json['Success'] ?? null) === true;
        $this->message = $json['Message'] ?? null;
        $this->model = is_array($json['Model'] ?? null) ? $json['Model'] : null;

        if (!$this->success) {
            $this->error =
                'CloudPayments refused the request (HTTP ' .
                (string) $this->httpCode .
                '): ' .
                ($this->message ?: Helper::sanitizeForLog($out));
        }

        return $this;
    }

    private function resetResponseState()
    {
        $this->response = null;
        $this->model = null;
        $this->success = null;
        $this->message = null;

        return $this;
    }
}
