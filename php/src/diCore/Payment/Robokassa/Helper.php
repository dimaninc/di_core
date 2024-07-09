<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08.07.2017
 * Time: 16:47
 */

namespace diCore\Payment\Robokassa;

use diCore\Data\Types;
use diCore\Entity\PaymentDraft\Model as Draft;
use diCore\Payment\Payment;
use diCore\Tool\Logger;
use diCore\Traits\BasicCreate;
use WpOrg\Requests\Requests;
use WpOrg\Requests\Transport\Fsockopen;

class Helper
{
    use BasicCreate;

    const login = null;
    const password1 = null;
    const password2 = null;
    const testPassword1 = null;
    const testPassword2 = null;

    const productionUrl = 'https://auth.robokassa.ru/Merchant/Index.aspx';

    const securityType = 'MD5';
    const testMode = false;

    const useReceipt = true;
    const taxSystem = TaxSystem::osn;

    /** @var Draft */
    private $draft;

    protected $options = [];

    public function __construct($options = [])
    {
        $this->options = extend($this->options, $options);
    }

    public static function getUrl()
    {
        return static::productionUrl;
    }

    public static function log($message)
    {
        Logger::getInstance()->log($message, 'Robokassa', '-payment');
    }

    public static function getMerchantLogin()
    {
        return static::login;
    }

    public static function getPassword1()
    {
        return static::isTestMode() ? static::testPassword1 : static::password1;
    }

    public static function getPassword2()
    {
        return static::isTestMode() ? static::testPassword2 : static::password2;
    }

    public static function isTestMode()
    {
        return !!static::testMode;
    }

    public static function isReceiptUsed()
    {
        return static::useReceipt;
    }

    public static function formatCost($cost)
    {
        return sprintf('%.2f', $cost);
    }

    public static function getRequest($url)
    {
        $request = Requests::get(
            $url,
            [],
            [
                'transport' => Fsockopen::class,
            ]
        );

        return $request->body;
    }

    public static function getReducedCost($cost, $vendor)
    {
        return $cost;

        $url = sprintf(
            'https://auth.robokassa.ru/Merchant/WebService/Service.asmx/CalcOutSumm?MerchantLogin=%1$s&IncCurrLabel=%3$s&IncSum=%2$s',
            static::getMerchantLogin(),
            $cost,
            $vendor
        );
        $xml = static::getRequest($url);

        preg_match('#<OutSum>(\d+)</OutSum>#', $xml, $regs);

        $reducedCost = !empty($regs[1]) ? (float) $regs[1] : 0;

        return $reducedCost ?: $cost;
    }

    /**
     * @param Draft $draft
     * @param array $opts
     * How to calculate amount: https://partner.robokassa.ru/Help/Doc/f5af7f3b-9c27-41de-b1c3-0aa76445ecd6
     * @return string
     */
    public static function getForm(Draft $draft, $opts = [])
    {
        $action = static::getUrl();

        $opts = extend(
            [
                'amount' => $draft->getAmount(),
                'draftId' => $draft->getId(),
                'description' => '',
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
        $opts['amount'] = static::getReducedCost($opts['amount'], $paymentVendor);

        $params = extend(
            [
                'MrchLogin' => static::getMerchantLogin(),
                'OutSum' => self::formatCost($opts['amount']),
                'InvId' => $opts['draftId'],
                'Desc' => $opts['description'],
                'SignatureValue' => static::getSignatureForm($draft),
                'IncCurrLabel' => $paymentVendor,
                'Culture' => 'ru',
                'Encoding' => 'utf-8',
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

    protected static function getReceipt(Draft $draft)
    {
        $ar = [
            'sno' => TaxSystem::name(static::taxSystem),
            'items' => static::getItemsForReceipt($draft),
        ];

        return urlencode(json_encode($ar));
    }

    protected static function getItemsForReceipt(Draft $draft)
    {
        return [
                /*
			[
				'name' => StringHelper::out('Название товара'),
				'quantity' => 1,
				'sum' => 1000,
				'tax' => Vat::name(Vat::none),
			],
			*/
            ];
    }

    public static function getSignatureForm(Draft $draft)
    {
        $source = array_filter([
            static::getMerchantLogin(),
            static::formatCost($draft->getAmount()),
            $draft->getId(),
            static::isReceiptUsed() ? static::getReceipt($draft) : null,
            static::getPassword1(),
        ]);

        self::log('getSignatureForm source: ' . join(':', $source));

        return md5(join(':', $source));
    }

    public static function getSignatureResult(Draft $draft)
    {
        $cost = \diRequest::request('OutSum'); //static::formatCost($draft->getAmount())

        $source = [$cost, $draft->getId(), static::getPassword2()];

        self::log('getSignatureResult source: ' . join(':', $source));

        return md5(join(':', $source));
    }

    public static function getSignatureSuccess(Draft $draft)
    {
        $cost = \diRequest::request('OutSum'); //static::formatCost($draft->getAmount())

        $source = [$cost, $draft->getId(), static::getPassword1()];

        self::log('getSignatureSuccess source: ' . join(':', $source));

        return md5(join(':', $source));
    }

    public function initDraft(callable $getDraftCallback)
    {
        $draftId = \diRequest::post('InvId', 0);
        $amount = \diRequest::post('OutSum', 0.0);

        $this->draft = $getDraftCallback($draftId, $amount);

        return $this;
    }

    /**
     * @return Draft
     * @throws \Exception
     */
    public function getDraft()
    {
        return $this->draft ?: \diModel::create(Types::payment_draft);
    }

    public function result(callable $paidCallback)
    {
        try {
            $signature = strtolower(\diRequest::post('SignatureValue'));
            $cost = \diRequest::post('OutSum', 0.0);

            if (!$this->getDraft()->exists()) {
                throw new \Exception('No draft found');
            }

            if ($this->getDraft()->getAmount() != $cost) {
                throw new \Exception(
                    'Cost not match: (their) ' .
                        $cost .
                        ', (our) ' .
                        $this->getDraft()->getAmount()
                );
            }

            $ourSignature = static::getSignatureResult($this->getDraft());

            if ($signature != $ourSignature) {
                throw new \Exception(
                    'Signature not matched (' .
                        $signature .
                        ' != ' .
                        $ourSignature .
                        ')'
                );
            }

            self::log(
                'Result method OK, signature received: ' .
                    $signature .
                    ', ours: ' .
                    $ourSignature
            );

            $paidCallback($this);

            return 'OK' . $this->getDraft()->getId();
        } catch (\Exception $e) {
            self::log('Error during `result`: ' . $e->getMessage());

            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function success(callable $successCallback)
    {
        try {
            $signature = strtolower(\diRequest::post('SignatureValue'));

            if (!$this->getDraft()->exists()) {
                throw new \Exception('No draft found');
            }

            if ($signature != static::getSignatureSuccess($this->getDraft())) {
                throw new \Exception('Signature not matched');
            }

            self::log('Success method OK');

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
        self::log('Fail method OK');

        return $failCallback($this);
    }
}
