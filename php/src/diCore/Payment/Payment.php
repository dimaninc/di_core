<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 15.02.2018
 * Time: 11:18
 */

namespace diCore\Payment;

use diCore\Base\CMS;
use diCore\Data\Configuration;
use diCore\Entity\PaymentDraft\Model as Draft;
use diCore\Entity\PaymentReceipt\Model as Receipt;
use diCore\Payment\CryptoCloud\Helper as CryptoCloud;
use diCore\Payment\Mixplat\Helper as Mixplat;
use diCore\Payment\Paypal\Helper as Paypal;
use diCore\Payment\Robokassa\Helper as Robokassa;
use diCore\Payment\Sberbank\Helper as Sberbank;
use diCore\Payment\Tinkoff\Helper as Tinkoff;
use diCore\Payment\Yandex\Kassa;
use diCore\Tool\Auth as AuthTool;
use diCore\Tool\Logger;
use diCore\Traits\BasicCreate;

/**
 * Class Payment
 * @package diCore\Payment
 * @method static Payment getClass
 */
class Payment
{
    use BasicCreate;

    const SELECT_FIRST_VENDOR_BY_DEFAULT = false;
    const ORDER_DESCRIPTION = 'Payment';
    const ORDER_THANKS = 'Thank you';

    protected static $paymentVendorsUsed = [
        /* example:
        System::mixplat,
        //System::sms_online,
        System::paypal,

        [
            'system' => System::yandex_kassa,
            'vendors' => [
                \diCore\Payment\Yandex\Vendor::CARD,
            ],
        ],

        [
            'system' => System::robokassa,
            'vendors' => [
                \diCore\Payment\Robokassa\Vendor::CARD,
            ],
        ],
        */
    ];

    // currencies
    const rub = 1;
    const usd = 2;
    const eur = 3;

    public static $currencies = [
        self::rub => 'Руб',
        self::usd => 'Usd',
        self::eur => 'Eur',
    ];

    /** @var  int */
    protected $targetType;
    /** @var  int */
    protected $targetId;
    /** @var  int */
    protected $userId;

    private static $counter;

    public function __construct($targetType, $targetId, $userId)
    {
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->userId = $userId;
    }

    public static function enabled()
    {
        return Configuration::get('epay_enabled');
    }

    public static function getPaymentVendorsUsed()
    {
        $class = static::getClass();

        return $class::$paymentVendorsUsed;
    }

    /**
     * @return int
     */
    public function getTargetType()
    {
        return $this->targetType;
    }

    /**
     * @param int $targetType
     * @return $this
     */
    public function setTargetType($targetType)
    {
        $this->targetType = $targetType;

        return $this;
    }

    /**
     * @return int
     */
    public function getTargetId()
    {
        return $this->targetId;
    }

    /**
     * @param int $targetId
     * @return $this
     */
    public function setTargetId($targetId)
    {
        $this->targetId = $targetId;

        return $this;
    }

    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param int $userId
     * @return $this
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;

        return $this;
    }

    public static function isPaymentMethodAvailable($systemId, $vendorId)
    {
        return true;
    }

    public static function getCurrentSystems()
    {
        return System::$titles;
    }

    public static function systemTitle($systemId)
    {
        return System::title($systemId) ?: 'Unknown payment system #' . $systemId;
    }

    public static function getCurrentVendors()
    {
        $vendors = [];

        foreach (static::getCurrentSystems() as $systemId => $systemTitle) {
            /** @var VendorContainer $class */
            $class = System::getSystemClass($systemId);

            foreach ($class::$titles as $vendorId => $vendorTitle) {
                if (Payment::isVendorUsed($systemId, $vendorId)) {
                    $vendors[$vendorId] = $systemTitle . ': ' . $vendorTitle;
                }
            }
        }

        return $vendors;
    }

    public static function currencyTitle($currencyId)
    {
        return isset(static::$currencies[$currencyId])
            ? static::$currencies[$currencyId]
            : 'Unknown currency #' . $currencyId;
    }

    public function initiateProcess($amount, $paymentSystemName, $paymentVendorName)
    {
        $paymentSystemId = System::id($paymentSystemName);
        $paymentVendorId = System::vendorIdByName($paymentSystemId, $paymentVendorName);

        /** @var Draft $draft */
        $draft = $this->getNewDraft(
            $amount,
            $paymentSystemId,
            $paymentVendorId
        );

        switch ($draft->getPaySystem()) {
            case System::mixplat:
                return $this->initMixplat($draft);

            case System::sms_online:
                return $this->initSmsOnline($draft);

            case System::paypal:
                return $this->initPaypal($draft);

            case System::crypto_cloud:
                return $this->initCryptoCloud($draft);

            case System::yandex_kassa:
                return $this->initYandex($draft);

            case System::robokassa:
                return $this->initRobokassa($draft);

            case System::tinkoff:
                return $this->initTinkoff($draft);

            case System::sberbank:
                return $this->initSberbank($draft);

            default:
                throw new \Exception('Unsupported payment system #' . $draft->getPaySystem());
        }
    }

    public function getNewDraft($amount, $systemId, $vendorId = 0, $currency = self::rub)
    {
        static::log("Creating draft for [target: {$this->getTargetType()}#{$this->getTargetId()}, " .
            "user: {$this->getUserId()}, amount: $amount, system: $systemId, vendor: $vendorId]");
        static::log('Route: ' . \diRequest::requestUri());

        $draft = Draft::create();

        $this->afterDraftCreate($draft);

        $draft
            ->setUserId($this->getUserId())
            ->setTargetType($this->getTargetType())
            ->setTargetId($this->getTargetId())
            ->setPaySystem((int)$systemId)
            ->setVendor((int)$vendorId)
            ->setCurrency($currency)
            ->setAmount($amount)
            ->setIp(ip2bin());

        $this->beforeDraftSave($draft);

        $draft
            ->save();

        static::log("Draft created: #{$draft->getId()}");

        return $draft;
    }

    protected function afterDraftCreate(Draft $draft)
    {
        return $this;
    }

    protected function beforeDraftSave(Draft $draft)
    {
        return $this;
    }

    /**
     * @param int $targetType
     * @param int $targetId
     * @param int $userId
     * @param float $amount
     * @param int $systemId
     * @param int $vendorId
     * @return Draft
     * @throws \Exception
     */
    public static function createDraft($targetType, $targetId, $userId, $amount, $systemId, $vendorId = 0, $currency = self::rub)
    {
        $P = static::basicCreate($targetType, $targetId, $userId);

        return $P->getNewDraft($amount, $systemId, $vendorId, $currency);
    }

    public static function getHiddenInput($name, $value)
    {
        return "<input name=\"{$name}\" value=\"{$value}\" type=\"hidden\">";
    }

    public static function getAutoSubmitScript()
    {
        return <<<'EOF'
<script type="text/javascript">
document.forms[0].submit();
</script>
EOF;
    }

    public static function wrapFormIntoHtml($form, $lang = 'ru')
    {
        return <<<EOF
<!doctype html>
<html lang="{$lang}">
<head>
<title>Payment system redirect</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="robots" content="noindex, nofollow">
</head>
<body>{$form}</body>
</html>
EOF;
    }

    public static function htmlRedirect($url, $body, $lang = 'ru')
    {
        return <<<EOF
<!doctype html>
<html lang="{$lang}">
<head>
<title>Payment system redirect</title>
<meta http-equiv="refresh" content="0; url={$url}" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="robots" content="noindex, nofollow">
</head>
<body>{$body}</body>
</html>
EOF;
    }

    public static function log($message)
    {
        Logger::getInstance()->log($message, 'diPayment', '-payment');
    }

    public static function postProcess(Receipt $receipt)
    {
    }

    protected static function getPaymentVendorRow(
        $systemId,
        $vendorId = null,
        callable $hrefCallback = null,
        $selectedVendorId = null
    )
    {
        $selected = $selectedVendorId && (
                $selectedVendorId == $vendorId ||
                (static::SELECT_FIRST_VENDOR_BY_DEFAULT && !$selectedVendorId && static::$counter == 0)
            );

        if (static::isPaymentMethodAvailable($systemId, $vendorId)) {
            return [
                'vendor' => [
                    'id' => $vendorId,
                    'name' => System::vendorName($systemId, $vendorId),
                    'title' => System::vendorTitle($systemId, $vendorId),
                    'href' => $hrefCallback ? $hrefCallback($systemId, $vendorId) : null,
                    'selected_class' => $selected ? 'selected' : '',
                ],
                'system' => [
                    'id' => $systemId,
                    'name' => System::name($systemId),
                    'title' => System::title($systemId),
                ],
            ];
        }

        return null;
    }

    public static function getTemplateData(callable $hrefCallback = null, $selectedVendorId = null)
    {
        $ar = [
            'payment_vendor_rows' => [],
        ];
        static::$counter = 0;

        foreach (static::$paymentVendorsUsed as $systemId) {
            if (is_array($systemId)) {
                $vendors = $systemId['vendors'];
                $systemId = $systemId['system'];

                if (!is_array($vendors)) {
                    $vendors = [$vendors];
                }

                foreach ($vendors as $vendorId) {
                    $ar['payment_vendor_rows'][] = static::getPaymentVendorRow(
                        $systemId, $vendorId, $hrefCallback, $selectedVendorId
                    );
                    static::$counter++;
                }
            } else {
                $ar['payment_vendor_rows'][] = static::getPaymentVendorRow(
                    $systemId, null, $hrefCallback, $selectedVendorId
                );
                static::$counter++;
            }
        }

        $ar['payment_vendor_rows'] = array_filter($ar['payment_vendor_rows']);

        return $ar;
    }

    public static function isVendorUsed($systemId, $vendorId = null)
    {
        foreach (static::getPaymentVendorsUsed() as $sysId) {
            if (is_array($sysId)) {
                $vendors = $sysId['vendors'];
                $sysId = $sysId['system'];

                if (!is_array($vendors)) {
                    $vendors = [$vendors];
                }

                foreach ($vendors as $venId) {
                    if ($sysId == $systemId && $venId == $vendorId) {
                        return true;
                    }
                }
            } else {
                if ($sysId == $systemId) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function getCustomerEmail()
    {
        return AuthTool::i()->getUserModel()->getEmail();
    }

    protected function getCustomerPhone()
    {
        return AuthTool::i()->getUserModel()->getPhone();
    }

    public function initYandex(Draft $draft)
    {
        $paymentVendor = \diCore\Payment\Yandex\Vendor::code($draft->getVendor());
        $kassa = Kassa::create();

        return static::wrapFormIntoHtml($kassa::getForm($draft, [
            'autoSubmit' => true,
            'customerEmail' => $this->getCustomerEmail(),
            'customerPhone' => $this->getCustomerPhone(),
            'paymentSystem' => $paymentVendor,
            'additionalParams' => [
                'shopSuccessURL' => $kassa->getSuccessUrl($draft),
                'shopFailURL' => $kassa->getFailUrl($draft),
            ],
        ]));
    }

    public function initRobokassa(Draft $draft)
    {
        $rk = Robokassa::basicCreate();

        return static::wrapFormIntoHtml($rk::getForm($draft, [
            'autoSubmit' => true,
            'customerEmail' => $this->getCustomerEmail(),
            'customerPhone' => $this->getCustomerPhone(),
            'description' => static::ORDER_DESCRIPTION,
        ]));
    }

    public function initTinkoff(Draft $draft)
    {
        $t = Tinkoff::create();

        header('Location: ' . $t->getFormUri($draft, [
            'customerEmail' => $this->getCustomerEmail(),
            'customerPhone' => $this->getCustomerPhone(),
            'description' => static::ORDER_DESCRIPTION,
        ]));

        return null;
    }

    public function initSberbank(Draft $draft)
    {
        $sb = Sberbank::create();

        header('Location: ' . $sb->getFormUri($draft, [
            'customerEmail' => $this->getCustomerEmail(),
            'customerPhone' => $this->getCustomerPhone(),
            'description' => static::ORDER_DESCRIPTION,
        ]));

        return null;
    }

    public function initPaypal(Draft $draft)
    {
        $successUrl = \diPaths::defaultHttp() . CMS::makeUrl([CMS::ct('payment_callback'), 'thanks'], [
                'orderNumber' => $draft->getId(),
                'draftNumber' => $draft->getId(),
            ]);
        $failUrl = \diPaths::defaultHttp() . CMS::makeUrl([CMS::ct('payment_callback'), 'failed'], [
                'draftNumber' => $draft->getId(),
            ]);

        $pp = Paypal::create();

        return static::wrapFormIntoHtml($pp::getForm($draft, [
            'autoSubmit' => true,
            'orderTitle' => static::ORDER_DESCRIPTION,
            'currency' => 'RUB',
            'additionalParams' => [
                'return' => $successUrl,
                'cancel_return' => $failUrl,
            ],
        ]));
    }

    public function initMixplat(Draft $draft)
    {
        $mixplat = Mixplat::create();

        $result = $mixplat->queryInit(
            $this->getCustomerPhone(),
            static::ORDER_DESCRIPTION,
            $draft->getAmount(),
            'RUR',
            $draft->getId(),
            static::ORDER_THANKS
        );

        $ok = true;
        $message = '';

        if ($result->isSuccess()) {
            $draft
                ->setVendor(\diCore\Payment\Mixplat\MobileVendors::id($result->getData('operator')))
                ->setStatus(\diCore\Payment\Mixplat\ResultStatus::PENDING)
                ->save();

            $this->log('MixPlat initiated successfully: ' . print_r($result->getData(), true));
        } else {
            $this->log('MixPlat not initiated: (' . $result->getErrorCode() . ') ' . $result->getError());

            $ok = false;

            switch ($result->getErrorCode()) {
                case 8: //Operator is not active
                    // todo: Localization here
                    $message = 'Технические проблемы на стороне оператора связи';

                    $draft
                        ->setStatus(\diCore\Payment\Mixplat\ResultStatus::OPSOS_NOT_SUPPORTED)
                        ->save();

                    break;

                case 18: //No available operator for specified phone
                    // todo: Localization here
                    $message = 'Платёж невозможен для Вашего оператора связи';

                    $draft
                        ->setStatus(\diCore\Payment\Mixplat\ResultStatus::OPSOS_NOT_SUPPORTED)
                        ->save();

                    break;

                default:
                    $message = '(' . $result->getErrorCode() . ') ' . $result->getError();
                    break;
            }
        }

        return [
            'ok' => $ok,
            'message' => $message,
            'draft_id' => $draft->getId(),
        ];
    }

    public function initCryptoCloud(Draft $draft)
    {
        $cc = CryptoCloud::basicCreate();

        $payUrl = $cc->initPayment($draft, [
            'customerEmail' => $this->getCustomerEmail(),
            'customerPhone' => $this->getCustomerPhone(),
        ]);

        return static::htmlRedirect($payUrl, $cc::getRedirectHtmlBody($draft));
    }

    public function initSmsOnline(Draft $draft)
    {
        throw new \Exception('Not implemented yet');

        // return [];
    }
}