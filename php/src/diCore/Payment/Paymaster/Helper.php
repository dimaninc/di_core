<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 17.05.2020
 * Time: 11:18
 */

namespace diCore\Payment\Paymaster;

use diCore\Entity\PaymentDraft\Model as Draft;
use diCore\Payment\Payment;
use diCore\Tool\Logger;
use diCore\Traits\BasicCreate;

class Helper
{
    use BasicCreate;

    const MERCHANT_ID = null;
    const SECRET_KEY = null;
    const SIGN_METHOD = 'sha256';
    const testMode = false;

    const URL_BASE = 'https://paymaster.ru/partners/rest/';

    public static function getUrl($method)
    {
        return static::URL_BASE . $method;
    }

    public static function log($message)
    {
        Logger::getInstance()->log($message, 'Paymaster', '-payment');
    }

    /**
     * @param Draft $draft Draft model
     * @param array $opts Options
     * @return string
     */
    public static function getForm(Draft $draft, $opts = [])
    {
        $action = static::getUrl();

        $opts = extend(
            [
                'customerId' => $draft->getUserId(),
                'customerEmail' => '',
                'customerPhone' => '',
                'autoSubmit' => false,
                'buttonCaption' => 'Заплатить',
                'additionalParams' => [],
            ],
            $opts
        );

        $paymentVendor = Vendor::code($draft->getVendor());

        $params = extend(
            [
                'LMI_MERCHANT_ID' => static::MERCHANT_ID,
                'LMI_PAYMENT_AMOUNT' => $draft->getAmount(),
                'LMI_PAYMENT_NO' => $draft->getId(),
                'LMI_PAYMENT_DESC' => $opts['description'],
                'SIGN' => static::getSignatureForm($draft),
                'LMI_CURRENCY' => $paymentVendor,
            ],
            $opts['additionalParams']
        );

        if (self::isTestMode()) {
            $params['IsTest'] = 1;
        }

        if (static::isReceiptUsed()) {
            $params['Receipt'] = static::getReceipt($draft);
        }

        $form = Payment::formHtml($action, $params, $opts);

        static::log("Robokassa form:\n" . $form);

        return $form;
    }
}
