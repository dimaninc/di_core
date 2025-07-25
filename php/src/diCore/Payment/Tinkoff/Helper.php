<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.12.2017
 * Time: 17:11
 * @link https://oplata.tinkoff.ru/landing/develop/
 * @link https://oplata.tinkoff.ru/landing/develop/documentation
 */

namespace diCore\Payment\Tinkoff;

use diCore\Entity\PaymentDraft\Model as Draft;
use diCore\Helper\ArrayHelper;
use diCore\Payment\BaseHelper;
use diCore\Payment\System;

class Helper extends BaseHelper
{
    const system = System::tinkoff;

    /** @var MerchantApi */
    protected $api;

    /** @var Draft */
    protected $draft;

    public function initDraft(callable $getDraftCallback)
    {
        $draftId = \diRequest::request('OrderId', 0);
        $amount = \diRequest::request('Amount', 0) / 100;

        $this->draft = $getDraftCallback($draftId, $amount);

        return $this;
    }

    protected function getApi()
    {
        if (!$this->api) {
            $this->api = new MerchantApi(static::getLogin(), static::getPassword());
        }

        return $this->api;
    }

    /**
     * @param Draft $draft
     * @param array $opts
     * @return string
     */
    public function getFormUri(Draft $draft, $opts = [])
    {
        $opts = extend(
            [
                'amount' => $draft->getAmount(),
                'userId' => $draft->getUserId(),
                'draftId' => $draft->getId(),
                'description' => '',
                'customerEmail' => '',
                'customerPhone' => '',
                'paymentVendor' => '',
                'additionalParams' => [],
            ],
            $opts
        );

        $params = [
            'OrderId' => $opts['draftId'],
            'Amount' => sprintf('%d', $opts['amount'] * 100),
            'Description' => $opts['description'],
            'Language' => 'ru',
            'DATA' => $opts['additionalParams'],
        ];

        $response = $this->getApi()->init(array_filter($params));

        static::log("Init:\n" . print_r($params, true));
        static::log("Response:\n" . print_r($response, true));

        if ($this->getApi()->getError()) {
            throw new \Exception(
                'Tinkoff init error: ' . $this->getApi()->getError()
            );
        }

        return $this->getApi()->getPaymentUrl();
    }

    public function generateToken($params)
    {
        foreach ($params as $key => &$param) {
            if (gettype($param) === 'boolean') {
                $param = $param ? 'true' : 'false';
            }

            if (!is_scalar($param)) {
                unset($params[$key]);
            }
        }

        unset($params['Token']);
        // unset($params['Data']);
        $params['Password'] = static::getPassword();
        ksort($params);

        $line = join('', $params);
        $hash = hash('sha256', $line);

        // self::log('params: ' . print_r($params, true));
        // self::log('line: ' . $line);

        return $hash;
    }

    public function checkToken($params)
    {
        $token = ArrayHelper::get($params, 'Token');
        $generatedToken = $this->generateToken($params);

        // self::log('Generated token: ' . $generatedToken . ', received token: ' . $token);

        return $token && $generatedToken === $token;
    }

    public function success(callable $successCallback)
    {
        try {
            if (\diRequest::request('Success') === 'true') {
                self::log('Success method OK');
            } else {
                throw new \Exception(
                    'Success method not OK: ' . print_r($_GET, true)
                );
            }

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
        self::log('Fail method OK: ' . print_r($_GET, true));

        return $failCallback($this);
    }

    public function tuneVendor(Draft $payment, $sourceStr)
    {
        $this->log("tuneVendor: $sourceStr");

        if (!$sourceStr) {
            return $this;
        }

        $s = strtolower($sourceStr);
        $map = [
            'сards' => Vendor::CARD,
            'sberpay' => Vendor::SBERPAY,
            'sbp' => Vendor::SBP,
            'qrsbp' => Vendor::SBP,
            'mirpay' => Vendor::MIR_PAY,
            'tinkoffpay' => Vendor::TPAY,
            'yandexpay' => Vendor::YANDEX_PAY,
        ];
        $vendor = $map[$s] ?? null;

        if (!$vendor) {
            return $this;
        }

        $payment->setVendor($vendor);

        return $this;
    }

    /*
	public function getState(\diCore\Entity\PaymentDraft\Model $draft)
    {
        $this->getApi()->getState([
            'PaymentId' => $draft->get
        ]);
    }
	*/
}
