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
     * anyone on the path can redirect a payment.
     *
     * If the gateway's root is missing from the host's store, the answer is a
     * bundle via setCaBundle() — NOT this constant. Turning verification off
     * removes the protection for every method and every host; the bundle adds
     * one anchor. Measured on production: securepay.tinkoff.ru is signed by the
     * Минцифры root ("Russian Trusted Root CA"), which no standard
     * ca-certificates package carries, so on a RF host the bundle is mandatory
     * and upgrading the package does not help.
     */
    const VERIFY_TLS = true;

    /** API methods whose response carries a PaymentURL. */
    const PAYMENT_URL_METHODS = ['Init'];

    /** @var string|null extra CA bundle, null = the host's own store */
    private $caBundlePath = null;

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

    /**
     * Anchor the gateway's certificate with an extra CA bundle (CURLOPT_CAINFO)
     * instead of the host's store. For a root that ca-certificates does not and
     * will not carry — a state CA, a private one — this is the correct fix; the
     * wrong one is `VERIFY_TLS = false`.
     *
     * Not installed system-wide on purpose: such a root may issue a certificate
     * for ANY domain, so adding it to the machine's store would weaken every
     * other outbound connection the host makes to fix one gateway.
     *
     * `null` (the default) keeps the host's store untouched. The path is NOT
     * validated here — an unreadable file makes cURL fail with error 77 naming
     * that file, which is a far better diagnosis than quietly verifying against
     * something else. Callers that prefer a fallback resolve the path first.
     *
     * NB: CAINFO replaces the default CA *file*, but a libcurl built with a
     * default CAPATH (Debian: /etc/ssl/certs) keeps consulting the system
     * directory as well — so whether the bundle must also carry the public
     * roots depends on the build. Make it self-sufficient for the hosts you
     * actually call and the question does not arise.
     *
     * @param string|null $path
     * @return $this
     */
    public function setCaBundle($path)
    {
        $this->caBundlePath = $path !== null && $path !== '' ? (string) $path : null;

        return $this;
    }

    /** @return string|null */
    public function getCaBundle()
    {
        return $this->caBundlePath;
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

            if ($this->caBundlePath !== null) {
                curl_setopt($curl, CURLOPT_CAINFO, $this->caBundlePath);
            }

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
                // Nothing was parsed, so nothing here describes this call — and
                // handleResponse(), which would have cleared it, is never
                // reached on this branch.
                $this->resetResponseState();
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
     * Every field it sets describes THIS response and nothing else — they are
     * cleared up front, before any early return. One api instance is cached in
     * Helper::getApi() and serves a whole reconciler run, so a value carried
     * over from an earlier call would quietly belong to another payment: after
     * GetState(A) an error reply for B used to leave $api->status reporting A's
     * CONFIRMED as B's. A stale value read as the current one is worse than an
     * obviously absent null.
     *
     * The only method-dependent part is whether a MISSING PaymentURL is an
     * error: a GetState or GetQr reply has none, and treating that as a failure
     * put a bogus "missing PaymentURL" into getError() after a perfectly
     * successful call. The URL itself is read whenever the response carries it.
     *
     * @param string $out raw response body
     * @param string|null $path API method name
     */
    protected function handleResponse($out, $path = null)
    {
        $this->error = '';
        $this->resetResponseState();

        $json = json_decode($out);

        if (!$json) {
            $this->error = 'Invalid JSON response from Tinkoff: ' . $out;

            return $this;
        }

        if (($json->ErrorCode ?? null) !== '0') {
            $this->error =
                ($json->Details ?? null) ?:
                ('Tinkoff error code ' . ($json->ErrorCode ?? null) . ': ' . $out);

            return $this;
        }

        $this->paymentId = $json->PaymentId ?? null;
        $this->status = $json->Status ?? null;

        // TODO(BOTCARD-10): PaymentURL is checked for "non-empty string" and
        // nothing else — no host check — even though this is the address the
        // payer is sent to (Helper::getFormUri() -> Payment::redirectTo()).
        // Same hole isSbpPayload() was written for, on a far larger surface:
        // every card payment goes through PaymentURL, SBP is the secondary
        // branch. And the body may not even come from the host we called —
        // CURLOPT_FOLLOWLOCATION is on and curl allows an https -> http
        // redirect by default.
        //
        // When we come back:
        //  1) mirror isSbpPayload() — an overridable whitelist
        //     (securepay.tinkoff.ru and subdomains), https scheme, no userinfo,
        //     printable ASCII anchored with \z, a length cap;
        //  2) an invalid URL is a failed request, not an empty result: write to
        //     $this->error so getFormUri() throws instead of redirecting;
        //  3) settle FOLLOWLOCATION too — drop it, or confine
        //     CURLOPT_REDIR_PROTOCOLS to https;
        //  4) tests along the lines of testForeignHostGivesNull.
        //
        // Deliberately not done here: it changes the live payment path of every
        // di_core consumer, and that belongs in its own release rather than
        // riding along with a new API method.
        $this->paymentUrl = $json->PaymentURL ?? null;

        if (
            static::methodReturnsPaymentUrl($path) &&
            (!is_string($this->paymentUrl) || $this->paymentUrl === '')
        ) {
            $this->error =
                'Tinkoff response missing PaymentURL (status ' .
                (string) $this->status .
                '): ' .
                $out;
        }

        return $this;
    }

    /**
     * Case-insensitive on purpose. buildQuery() is public and its docblock
     * invites custom calls, so a consumer may well spell the method 'init';
     * a strict comparison would then skip the check silently, and the caller
     * would get an empty error alongside no URL to send the payer to.
     *
     * @param string|null $path
     */
    protected static function methodReturnsPaymentUrl($path): bool
    {
        if (!is_string($path)) {
            return false;
        }

        foreach (static::PAYMENT_URL_METHODS as $method) {
            if (strcasecmp($path, $method) === 0) {
                return true;
            }
        }

        return false;
    }

    /** Fields describing the last response, cleared before a new one is read. */
    private function resetResponseState()
    {
        $this->paymentId = null;
        $this->status = null;
        $this->paymentUrl = null;

        return $this;
    }

}
