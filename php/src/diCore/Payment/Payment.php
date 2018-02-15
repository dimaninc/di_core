<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 15.02.2018
 * Time: 11:18
 */

namespace diCore\Payment;

use diCore\Base\CMS;
use diCore\Entity\PaymentDraft\Model as Draft;
use diCore\Payment\Mixplat\Helper as Mixplat;
use diCore\Payment\Paypal\Helper as Paypal;
use diCore\Payment\Robokassa\Helper as Robokassa;
use diCore\Payment\Yandex\Kassa;
use diCore\Tool\Auth as AuthTool;
use diCore\Tool\Logger;

class Payment
{
    use \diCore\Traits\BasicCreate;

    const SELECT_FIRST_VENDOR_BY_DEFAULT = false;
    const ORDER_DESCRIPTION = 'Payment';
    const ORDER_THANKS = 'Thank you';

    protected static $paymentVendorsUsed = [
        /*
        System::mixplat,
        //System::sms_online,

        [
            'system' => System::yandex_kassa,
            'vendors' => [
                //\diCore\Payment\Yandex\Vendor::MOBILE,
                \diCore\Payment\Yandex\Vendor::CARD,
                //\diCore\Payment\Yandex\Vendor::SBERBANK_ONLINE,
            ],
        ],

        [
            'system' => System::robokassa,
            'vendors' => [
                \diCore\Payment\Robokassa\Vendor::CARD,
            ],
        ],

        System::paypal,

        [
            'system' => System::robokassa,
            'vendors' => [
                \diCore\Payment\Robokassa\Vendor::APPLE_PAY,
                \diCore\Payment\Robokassa\Vendor::SAMSUNG_PAY,
            ],
        ],

        [
            'system' => System::yandex_kassa,
            'vendors' => [
                \diCore\Payment\Yandex\Vendor::YANDEX_MONEY,
                \diCore\Payment\Yandex\Vendor::MASTER_PASS,
                \diCore\Payment\Yandex\Vendor::WEBMONEY,
                \diCore\Payment\Yandex\Vendor::QIWI_WALLET,
                //\diCore\Payment\Yandex\Vendor::PROM_SVYAZ_BANK,
                //\diCore\Payment\Yandex\Vendor::ALPHA_CLICK,
                //\diCore\Payment\Yandex\Vendor::QPPI,
                //\diCore\Payment\Yandex\Vendor::TINKOFF,
            ],
        ],

        [
            'system' => System::robokassa,
            'vendors' => [
                //\diCore\Payment\Robokassa\Vendor::YANDEX_MONEY,
                \diCore\Payment\Robokassa\Vendor::WEBMONEY,
                \diCore\Payment\Robokassa\Vendor::QIWI,
                //\diCore\Payment\Robokassa\Vendor::W1,
                //\diCore\Payment\Robokassa\Vendor::ALPHA_CLICK,
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

    private static $class;

    /** @var  int */
    protected $targetType;
    /** @var  int */
    protected $targetId;
    /** @var  int */
    protected $userId;

    public function __construct($targetType, $targetId, $userId)
    {
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->userId = $userId;
    }

    public static function enabled()
    {
        return \diConfiguration::get('epay_enabled');
    }

    /**
     * @return Payment|string
     */
    public static function getClass()
    {
        if (!self::$class)
        {
            self::$class = \diLib::getChildClass(self::class);
        }

        return self::$class;
    }

    final public static function resetClass()
    {
        self::$class = null;
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
        return \diCore\Payment\System::$titles;
    }

    public static function systemTitle($systemId)
    {
        return \diCore\Payment\System::title($systemId) ?: 'Unknown payment system #' . $systemId;
    }

    public static function currencyTitle($currencyId)
    {
        return isset(static::$currencies[$currencyId])
            ? static::$currencies[$currencyId]
            : 'Unknown currency #' . $currencyId;
    }

    public function getNewDraft($amount, $systemId, $vendorId = 0, $currency = self::rub)
    {
        static::log("Creating draft for [target: {$this->getTargetType()}#{$this->getTargetId()}, " .
            "user: {$this->getUserId()}, amount: $amount, system: $systemId, vendor: $vendorId]");
        static::log('Route: ' . \diRequest::requestUri());

        /** @var \diCore\Entity\PaymentDraft\Model $draft */
        $draft = \diModel::create(\diTypes::payment_draft);

        $draft
            ->setUserId($this->getUserId())
            ->setTargetType($this->getTargetType())
            ->setTargetId($this->getTargetId())
            ->setPaySystem((int)$systemId)
            ->setVendor((int)$vendorId)
            ->setCurrency($currency)
            ->setAmount($amount)
            ->save();

        static::log("Draft created: #{$draft->getId()}");

        return $draft;
    }

    /**
     * @param int $targetType
     * @param int $targetId
     * @param int $userId
     * @param float $amount
     * @param int $systemId
     * @param int $vendorId
     * @return \diCore\Entity\PaymentDraft\Model
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

    public static function log($message)
    {
        Logger::getInstance()->log($message, 'diPayment', '-payment');
    }

    public static function postProcess(\diCore\Entity\PaymentReceipt\Model $receipt)
    {
    }

    public static function getTemplateData(callable $hrefCallback = null, $selectedVendorId = null)
    {
        $ar = [
            'payment_vendor_rows' => [],
        ];
        $i = 0;

        $processPaymentVariantRow = function ($systemId, $vendorId = 0) use ($hrefCallback, $selectedVendorId, &$ar, &$i)
        {
            $selected = $selectedVendorId && (
                $selectedVendorId == $vendorId ||
                (static::SELECT_FIRST_VENDOR_BY_DEFAULT && !$selectedVendorId && $i == 0)
            );

            if (static::isPaymentMethodAvailable($systemId, $vendorId))
            {
                $ar['payment_vendor_rows'][] = [
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

                $i++;
            }
        };

        foreach (static::$paymentVendorsUsed as $systemId)
        {
            if (is_array($systemId))
            {
                $vendors = $systemId['vendors'];
                $systemId = $systemId['system'];

                if (!is_array($vendors))
                {
                    $vendors = [$vendors];
                }

                foreach ($vendors as $vendorId)
                {
                    $processPaymentVariantRow($systemId, $vendorId);
                }
            }
            else
            {
                $processPaymentVariantRow($systemId);
            }
        }

        return $ar;
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

        $successUrl = \diPaths::defaultHttp() . CMS::makeUrl([CMS::ct('payment_callback'), 'thanks']);
        $failUrl = \diPaths::defaultHttp() . CMS::makeUrl([CMS::ct("payment_callback"), 'failed']);
        //$failUrl = $successUrl;

        $kassa = Kassa::create();

        return $kassa::getForm($draft, [
            'autoSubmit' => true,
            'customerEmail' => $this->getCustomerEmail(),
            'customerPhone' => $this->getCustomerPhone(),
            'paymentSystem' => $paymentVendor,
            'additionalParams' => [
                'shopSuccessURL' => $successUrl,
                'shopFailURL' => $failUrl,
            ],
        ]);
    }

    public function initRobokassa(Draft $draft)
    {
        $rk = Robokassa::basicCreate();

        return $rk::getForm($draft, [
            'autoSubmit' => true,
            'customerEmail' => $this->getCustomerEmail(),
            'customerPhone' => $this->getCustomerPhone(),
            'description' => static::ORDER_DESCRIPTION,
        ]);
    }

    public function initTinkoff(Draft $draft)
    {
        throw new \Exception('Not implemented yet');

        return [];
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

        return $pp::getForm($draft, [
            'autoSubmit' => true,
            'orderTitle' => static::ORDER_DESCRIPTION,
            'currency' => 'RUB',
            'additionalParams' => [
                'return' => $successUrl,
                'cancel_return' => $failUrl,
            ],
        ]);
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

        if ($result->isSuccess())
        {
            $draft
                ->setVendor(\diCore\Payment\Mixplat\MobileVendors::id($result->getData('operator')))
                ->setStatus(\diCore\Payment\Mixplat\ResultStatus::PENDING)
                ->save();

            $this->log('MixPlat initiated successfully: ' . print_r($result->getData(), true));
        }
        else
        {
            $this->log('MixPlat not initiated: (' . $result->getErrorCode() . ') ' . $result->getError());

            $ok = false;

            switch ($result->getErrorCode())
            {
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

    public function initSmsOnline(Draft $draft)
    {
        throw new \Exception('Not implemented yet');

        return [];
    }
}