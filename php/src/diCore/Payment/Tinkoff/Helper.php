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

use diCore\Tool\Logger;

class Helper
{
    const testMode = false;

	const login = null;
	const password = null;

    const loginDemo = null;
    const passwordDemo = null;

    /** @var MerchantApi */
    protected $api;

	protected $options = [
		'onSuccessPayment' => null,
	];

	/**
     * @return Helper
     */
    public static function create($options = [])
    {
        $className = \diLib::getChildClass(self::class, 'Settings');

        $helper = new $className($options);

        return $helper;
    }

    public function __construct($options = [])
	{
		$this->options = extend($this->options, $options);

		$this->api = new MerchantApi(static::getLogin(), static::getPassword());
	}

	protected function getApi()
    {
        return $this->api;
    }

	public static function log($message)
	{
		Logger::getInstance()->log($message, 'Tinkoff', '-payment');
	}

	public static function getLogin()
	{
		return static::testMode
            ? static::loginDemo
            : static::login;
	}

	public static function getPassword()
	{
		return static::testMode
            ? static::passwordDemo
            : static::password;
	}

	/**
	 * @param \diCore\Entity\PaymentDraft\Model $draft
	 * @param array $opts
	 * @return string
	 */
	public function getFormUri(\diCore\Entity\PaymentDraft\Model $draft, $opts = [])
	{
		$opts = extend([
			'amount' => $draft->getAmount(),
			'userId' => $draft->getUserId(),
			'draftId' => $draft->getId(),
			'description' => '',
			'customerEmail' => '',
			'customerPhone' => '',
			'paymentVendor' => '',
			'additionalParams' => [],
		], $opts);

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
            throw new \Exception('Tinkoff init error: ' . $this->getApi()->getError());
        }

        return $this->getApi()->getPaymentUrl();
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