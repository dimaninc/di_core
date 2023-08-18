<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 23.08.2022
 * Time: 20:25
 */

namespace diCore\Payment\CryptoCloud;

use diCore\Entity\PaymentDraft\Model as Draft;
use diCore\Tool\Logger;
use diCore\Traits\BasicCreate;
use WpOrg\Requests\Requests;
use WpOrg\Requests\Transport\Fsockopen;

class Helper
{
    use BasicCreate;

    const DEBUG = true;
    const API_KEY = '';
    const SHOP_UID = '';

    const PRODUCTION_URL = 'https://cryptocloud.plus';
    const INIT_PAYMENT_METHOD = '/api/v2/invoice/create';
    const GET_PAYMENT_STATUS_METHOD = '/api/v2/invoice/status';

    /** @var Draft */
    private $draft;

    public static function getUrl($method)
    {
        return static::PRODUCTION_URL . $method;
    }

    public static function getRequestHeaders()
    {
        return [
            'Authorization' => 'Token ' . static::API_KEY,
        ];
    }

    public static function getUsdToRubRate()
    {
        return 60;
    }

    public static function convertAmount($rubAmount)
    {
        return $rubAmount / static::getUsdToRubRate();
    }

    public function initPayment(Draft $draft, $opts = [])
    {
        $opts = extend(
            [
                'customerEmail' => '',
                'customerPhone' => '',
            ],
            $opts
        );

        $url = static::getUrl(static::INIT_PAYMENT_METHOD);
        $query = [
            'shop_id' => static::SHOP_UID,
            'amount' => static::convertAmount($draft->getAmount()),
            'order_id' => $draft->getId(),
            'email' => $opts['customerEmail'],
            'currency' => 'RUB',
        ];

        static::debugLog('Sending request to POST ' . $url);
        static::debugLog(json_encode($query));

        $res = Requests::post($url, static::getRequestHeaders(), $query, [
            'transport' => Fsockopen::class,
            'timeout' => 14,
            'connect_timeout' => 14,
        ]);

        static::debugLog('Response: ' . json_encode($res->body));
        $body = $res->decode_body();

        if (!$body) {
            throw new \Exception('Empty CryptoCloud response');
        }

        if (!isset($body['status'])) {
            throw new \Exception('Incorrect CryptoCloud response');
        }

        if ($body['status'] !== 'success') {
            throw new \Exception('Non success CryptoCloud response');
        }

        $draft->setOuterNumber($body['invoice_id'])->save();

        return $body['pay_url'];
    }

    public function initDraft(callable $getDraftCallback)
    {
        $draftId = \diRequest::rawPost('invoice_id', '');
        $amount = \diRequest::rawPost('amount_crypto', 0.0);
        $currency = \diRequest::rawPost('currency', '');

        $this->draft = $getDraftCallback($draftId, $amount, $currency);

        return $this;
    }

    public static function getRedirectHtmlBody(Draft $draft, $opts = [])
    {
        return 'Redirecting to payment system...';
    }

    public static function debugLog($message)
    {
        if (static::DEBUG) {
            static::log($message);
        }
    }

    public static function log($message)
    {
        Logger::getInstance()->log($message, 'CryptoCloud', '-payment');
    }
}
