<?php
/**
 * @link https://developers.cloudpayments.ru/
 */

namespace diCore\Payment\CloudPayments;

use diCore\Entity\PaymentDraft\Model as Draft;
use diCore\Payment\BaseHelper;
use diCore\Payment\System;

class Helper extends BaseHelper
{
    const system = System::cloud_payments;

    /**
     * Headers the gateway signs a notification with. Both carry base64 of
     * HMAC-SHA256 over the request body on the API Secret; they differ only in
     * whether URL-encoded or URL-decoded parameters were hashed, which is moot
     * for a JSON body. Whichever arrives is accepted — a mismatch in the
     * cabinet's chosen format must not read as a forged request.
     */
    const SIGNATURE_HEADERS = ['Content-HMAC', 'X-Content-HMAC'];

    /** Generous ceiling for the invoice URL; the real ones are ~45 chars. */
    const ORDER_URL_MAX_LEN = 512;

    /** Notification `Status` values that mean the money was taken. */
    const PAID_STATUSES = ['Completed', 'Authorized'];

    /** @var MerchantApi */
    protected $api;

    /** @var Draft */
    protected $draft;

    /** @var string|null raw body of the notification being handled */
    protected $rawNotification;

    /** @var array|null parsed body of the notification being handled */
    protected $notificationParams;

    protected function getApi()
    {
        if (!$this->api) {
            $this->api = $this->createApi();
        }

        return $this->api;
    }

    /**
     * The single place a MerchantApi is built — override to substitute a
     * subclass (a project pinning an extra CA bundle, a test double).
     *
     * @return MerchantApi
     */
    protected function createApi()
    {
        $api = new MerchantApi(static::getLogin(), static::getPassword());
        $api->setCaBundle(static::getCaBundlePath());

        return $api;
    }

    /**
     * Path of an extra CA bundle anchoring the gateway, or null for the host's
     * own store. api.cloudpayments.ru is signed by a public CA, so unlike
     * T-Bank nothing is expected here.
     */
    protected static function getCaBundlePath()
    {
        return null;
    }

    /**
     * Creates an invoice and returns the URL to send the payer to.
     *
     * @throws \Exception when the gateway refused, or answered with a URL we
     *   are not willing to send a payer to
     */
    public function getFormUri(Draft $draft, $opts = [])
    {
        $opts = extend(
            [
                'amount' => $draft->getAmount(),
                'currency' => 'RUB',
                'draftId' => $draft->getId(),
                'description' => '',
                'customerEmail' => '',
                'cultureName' => 'ru-RU',
                'successUrl' => '',
                'failUrl' => '',
            ],
            $opts
        );

        $params = array_filter([
            // Two decimals, as the gateway requires. Rounding here rather than
            // hoping the float prints well: a stray 543.2000000001 is refused.
            'Amount' => round((float) $opts['amount'], 2),
            'Currency' => $opts['currency'],
            'Description' => $opts['description'],
            'InvoiceId' => (string) $opts['draftId'],
            'Email' => $opts['customerEmail'],
            'CultureName' => $opts['cultureName'],
            'SuccessRedirectUrl' => $opts['successUrl'],
            'FailRedirectUrl' => $opts['failUrl'],
        ]);

        // The draft is minted per attempt, so its id is unique per invoice and
        // makes a natural idempotency key: a retried Init cannot leave a second
        // unpaid invoice behind for the same attempt.
        $response = $this->getApi()->ordersCreate(
            $params,
            'draft-' . $draft->getId()
        );

        static::log("orders/create:\n" . print_r($params, true));
        static::log("Response:\n" . static::sanitizeForLog($response));

        if ($this->getApi()->getError()) {
            throw new \Exception(
                'CloudPayments order error: ' . $this->getApi()->getError()
            );
        }

        $url = $this->getApi()->getModelField('Url');

        if (!static::isOrderUrl($url)) {
            throw new \Exception(
                'CloudPayments returned no usable payment URL: ' .
                    static::sanitizeForLog($response)
            );
        }

        try {
            $this->afterOrderCreated($draft, $this->getApi()->getModelField('Id'));
        } catch (\Throwable $e) {
            static::log('afterOrderCreated failed: ' . $e->getMessage());
        }

        return $url;
    }

    /**
     * Fired after an invoice was created, with the gateway's order id. No-op by
     * default — override in a project to persist it. Never throws into the
     * payment flow (the caller swallows).
     *
     * @param string|null $orderId
     */
    protected function afterOrderCreated(Draft $draft, $orderId)
    {
        return $this;
    }

    /**
     * This string IS where the payer's money goes, so "any https URL the
     * gateway sent back" is not enough of a check: a WAF interstitial, a
     * tampered response or an intermediary's redirect page is not a payment
     * page, and failing the payment is better than pointing it elsewhere.
     *
     * Same shape as Tinkoff\Helper::isSbpPayload(), applied here from the start
     * because this gateway has no second path a payer could fall back to.
     *
     * @param mixed $url
     */
    public static function isOrderUrl($url)
    {
        if (!is_string($url) || strlen($url) > static::ORDER_URL_MAX_LEN) {
            return false;
        }

        // Printable ASCII only, anchored with \z — `$` also matches BEFORE a
        // trailing newline, which is how a log-forging "\n" once got through
        // the very check meant to stop it.
        if (!preg_match('/^[\x21-\x7e]+\z/', $url)) {
            return false;
        }

        $parts = parse_url($url);

        // Scheme and host are case-insensitive per RFC 3986 and parse_url()
        // normalises neither.
        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        // https://orders.cloudpayments.ru@evil.tld/ parses with host=evil.tld,
        // so the host check below covers it — but userinfo has no business in a
        // payment URL at all.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower(rtrim($parts['host'] ?? '', '.'));

        foreach (static::getOrderUrlDomains() as $domain) {
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

    /** Domains an invoice URL may point at, itself and its subdomains. */
    protected static function getOrderUrlDomains()
    {
        return ['cloudpayments.ru'];
    }

    /**
     * Reads the notification body once and keeps both the raw bytes and the
     * parsed form. The raw bytes are what the signature covers, so they must be
     * captured before anything re-encodes them.
     */
    public function readNotification($rawBody = null)
    {
        $this->rawNotification =
            $rawBody === null ? (string) \diRequest::rawPost() : (string) $rawBody;

        $json = json_decode($this->rawNotification, true);

        if (is_array($json)) {
            $this->notificationParams = $json;
        } else {
            // The cabinet also offers form-encoded notifications. We configure
            // JSON, but a switch there must degrade to a readable payload
            // rather than to an empty one.
            $parsed = [];
            parse_str($this->rawNotification, $parsed);
            $this->notificationParams = $parsed;
        }

        return $this;
    }

    public function getNotificationParams()
    {
        return $this->notificationParams ?: [];
    }

    public function getRawNotification()
    {
        return (string) $this->rawNotification;
    }

    /**
     * Verifies the notification signature against the RAW body.
     *
     * There is no IP allowlist to fall back on — CloudPayments publishes none —
     * so this signature is the ONLY thing separating a real notification from
     * anyone who can guess a draft id. A missing header is a failure, never a
     * skip.
     */
    public function checkSignature()
    {
        $secret = (string) static::getPassword();

        if ($secret === '' || $this->rawNotification === null) {
            return false;
        }

        $expected = base64_encode(
            hash_hmac('sha256', $this->rawNotification, $secret, true)
        );

        foreach (static::SIGNATURE_HEADERS as $header) {
            $received = \diRequest::header($header);

            // hash_equals, not ===: a signature comparison that returns early
            // on the first differing byte leaks how much of a guess was right.
            if (is_string($received) && hash_equals($expected, $received)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolves the draft this request is about. Reads the notification payload
     * when one was parsed, and the query string otherwise — the success and
     * fail redirects are addresses WE built, so they carry the same parameter
     * names.
     */
    public function initDraft(callable $getDraftCallback)
    {
        $params = $this->getNotificationParams();

        $draftId = $params['InvoiceId'] ?? \diRequest::request('InvoiceId', 0);
        $amount = $params['Amount'] ?? \diRequest::request('Amount', 0);

        $this->draft = $getDraftCallback($draftId, $amount);

        return $this;
    }

    public function getDraft()
    {
        return $this->draft;
    }

    /** Whether a `pay` notification really reports money taken. */
    public static function isPaidStatus($status)
    {
        return in_array((string) $status, static::PAID_STATUSES, true);
    }

    public static function isTestMode(array $params)
    {
        return in_array($params['TestMode'] ?? null, [1, '1', true], true);
    }

    /** The body the gateway expects for an accepted notification. */
    public static function okResponse()
    {
        return ['code' => 0];
    }

    /**
     * Any non-zero code makes the gateway retry — 100 attempts at 1, 2, 5, 10
     * and 30 minute intervals. That is what we want when we could NOT accept a
     * notification, including when the fault is ours (a wrong secret in env is
     * then recoverable without the notification being lost). The numbered code
     * table in the docs describes answers to `check`, which we do not enable.
     */
    public static function retryResponse()
    {
        return ['code' => 13];
    }

    public function success(callable $successCallback)
    {
        static::log('Success redirect: ' . print_r($_GET, true));

        return $successCallback($this);
    }

    public function fail(callable $failCallback)
    {
        static::log('Fail redirect: ' . print_r($_GET, true));

        return $failCallback($this);
    }

    /**
     * A body we did not authenticate goes into the payment log — it must not be
     * able to forge log lines with newlines, to grow the file without bound, or
     * to carry a secret into it.
     */
    public static function sanitizeForLog($text, $limit = 1000)
    {
        // Redact BEFORE truncating: a cut landing inside a value leaves an
        // unterminated quote, the pattern then does not match, and the
        // surviving prefix goes into the log verbatim.
        $text = preg_replace(
            '/("(?:Token|Password|ApiSecret|Secret)"\s*:\s*")[^"]*"/i',
            '$1***"',
            (string) $text
        );
        $text = preg_replace('/[\x00-\x1f\x7f]+/', ' ', $text);

        if (mb_strlen($text) > $limit) {
            $text = mb_substr($text, 0, $limit) . '… [truncated]';
        }

        return $text;
    }
}
