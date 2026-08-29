<?php
namespace diCore\Payment\Tinkoff;

use HttpException;

/**
 * https://oplata.tinkoff.ru/landing/develop/documentation/schema
 * Class MerchantApi
 * @package diCore\Payment\Tinkoff
 */
class MerchantApi
{
    /** Bound every gateway call — a hang must not pin an FPM worker / CLI job. */
    const CONNECT_TIMEOUT_SEC = 5;
    const TIMEOUT_SEC = 20;

    /**
     * The channel carries the request Token and brings back the URLs the payer
     * is then sent to (PaymentURL, the SBP payload), so an unverified peer means
     * anyone on the path can redirect a payment. Overridable in a subclass only
     * as an escape hatch for a host with a broken CA bundle.
     */
    const VERIFY_TLS = true;

    /** API methods whose response carries a PaymentURL. */
    const PAYMENT_URL_METHODS = ['Init'];

    private $api_url;
    private $terminalKey;
    private $secretKey;
    private $paymentId;
    private $status;
    private $error;
    private $response;
    private $paymentUrl;

    public function __construct($terminalKey, $secretKey)
    {
        $this->api_url = 'https://securepay.tinkoff.ru/v2/';
        $this->terminalKey = $terminalKey;
        $this->secretKey = $secretKey;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getPaymentUrl()
    {
        return $this->paymentUrl;
    }

    public function __get($name)
    {
        switch ($name) {
            case 'paymentId':
                return $this->paymentId;
            case 'status':
                return $this->status;
            case 'error':
                return $this->error;
            case 'paymentUrl':
                return $this->paymentUrl;
            case 'response':
                return htmlentities($this->response);
            default:
                if ($this->response) {
                    if ($json = json_decode($this->response, true)) {
                        foreach ($json as $key => $value) {
                            if (strtolower($name) == strtolower($key)) {
                                return $json[$key];
                            }
                        }
                    }
                }

                return false;
        }
    }

    /**
     * @param $args mixed You could use associative array or url params string
     * @return bool
     * @throws HttpException
     */
    public function init($args)
    {
        return $this->buildQuery('Init', $args);
    }

    public function getState($args)
    {
        return $this->buildQuery('GetState', $args);
    }

    public function confirm($args)
    {
        return $this->buildQuery('Confirm', $args);
    }

    public function charge($args)
    {
        return $this->buildQuery('Charge', $args);
    }

    public function addCustomer($args)
    {
        return $this->buildQuery('AddCustomer', $args);
    }

    public function getCustomer($args)
    {
        return $this->buildQuery('GetCustomer', $args);
    }

    public function removeCustomer($args)
    {
        return $this->buildQuery('RemoveCustomer', $args);
    }

    public function getCardList($args)
    {
        return $this->buildQuery('GetCardList', $args);
    }

    public function removeCard($args)
    {
        return $this->buildQuery('RemoveCard', $args);
    }

    /**
     * SBP QR of an already inited payment.
     * DataType=PAYLOAD returns the https://qr.nspk.ru/… string, IMAGE – an SVG.
     */
    public function getQr($args)
    {
        return $this->buildQuery('GetQr', $args);
    }

    /**
     * Builds a query string and call sendRequest method.
     * Could be used to custom API call method.
     *
     * @param string $path API method name
     * @param mixed $args query params
     *
     * @return mixed
     * @throws HttpException
     */
    public function buildQuery($path, $args)
    {
        $url = $this->api_url;
        if (is_array($args)) {
            if (!array_key_exists('TerminalKey', $args)) {
                $args['TerminalKey'] = $this->terminalKey;
            }
            if (!array_key_exists('Token', $args)) {
                $args['Token'] = $this->_genToken($args);
            }
        }
        $url = $this->_combineUrl($url, $path);

        return $this->_sendRequest($url, $args, $path);
    }

    /**
     * Generates Token
     *
     * @param $args
     * @return string
     */
    private function _genToken($args)
    {
        $token = '';
        $args['Password'] = $this->secretKey;
        ksort($args);

        foreach ($args as $arg) {
            if (!is_array($arg)) {
                $token .= $arg;
            }
        }
        $token = hash('sha256', $token);

        return $token;
    }

    /**
     * Combines parts of URL. Simply gets all parameters and puts '/' between
     *
     * @return string
     */
    private function _combineUrl(...$args)
    {
        $url = '';
        foreach ($args as $arg) {
            if (is_string($arg)) {
                if ($arg[strlen($arg) - 1] !== '/') {
                    $arg .= '/';
                }
                $url .= $arg;
            }
        }

        return $url;
    }

    /**
     * Main method. Call API with params
     *
     * @param $api_url
     * @param $args
     * @param string|null $path API method name, drives the response parsing
     * @return bool|string
     * @throws HttpException
     */
    private function _sendRequest($api_url, $args, $path = null)
    {
        $this->error = '';
        if (is_array($args)) {
            $args = json_encode($args);
        }

        if ($curl = curl_init()) {
            curl_setopt($curl, CURLOPT_URL, $api_url);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, static::VERIFY_TLS);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, static::VERIFY_TLS ? 2 : 0);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $args);
            // Without these a hung gateway blocks forever — it would pin an FPM
            // worker on Init and stall the CLI reconciler indefinitely.
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, static::CONNECT_TIMEOUT_SEC);
            curl_setopt($curl, CURLOPT_TIMEOUT, static::TIMEOUT_SEC);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
            ]);

            $out = curl_exec($curl);
            $this->response = $out;

            if ($out === false) {
                $curlErr = curl_error($curl) ?: 'unknown transport error';
                $this->error = 'cURL error: ' . $curlErr;
                curl_close($curl);

                return $out;
            }

            $this->handleResponse($out, $path);

            curl_close($curl);

            return $out;
        } else {
            throw new HttpException(
                "Can not create connection to $api_url with args $args",
                404
            );
        }
    }

    /**
     * Parses a gateway response body into the instance state.
     *
     * Only the fields the called method actually returns are touched: a GetState
     * or GetQr reply has no PaymentURL, and blindly assigning it would null the
     * previous Init's url and then raise a "missing PaymentURL" error for a
     * perfectly successful call — on the very same (cached) instance the caller
     * reads getError() from.
     *
     * @param string $out raw response body
     * @param string|null $path API method name
     */
    protected function handleResponse($out, $path = null)
    {
        $this->error = '';
        $json = json_decode($out);

        if (!$json) {
            $this->error = 'Invalid JSON response from Tinkoff: ' . $out;

            return $this;
        }

        if (@$json->ErrorCode !== '0') {
            $this->error =
                @$json->Details ?:
                ('Tinkoff error code ' . @$json->ErrorCode . ': ' . $out);

            return $this;
        }

        if (isset($json->PaymentId)) {
            $this->paymentId = $json->PaymentId;
        }

        if (isset($json->Status)) {
            $this->status = $json->Status;
        }

        if (in_array($path, static::PAYMENT_URL_METHODS, true)) {
            $this->paymentUrl = @$json->PaymentURL;

            if (!is_string($this->paymentUrl) || $this->paymentUrl === '') {
                $this->error =
                    'Tinkoff response missing PaymentURL (status ' .
                    (string) $this->status .
                    '): ' .
                    $out;
            }
        }

        return $this;
    }
}
