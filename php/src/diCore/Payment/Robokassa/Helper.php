<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08.07.2017
 * Time: 16:47
 */

namespace diCore\Payment\Robokassa;


use diCore\Traits\BasicCreate;

class Helper
{
	use BasicCreate;

	const login = null;
	const password1 = null;
	const password2 = null;
	const testPassword1 = null;
	const testPassword2 = null;

	const productionUrl = 'https://auth.robokassa.ru/Merchant/Index.aspx';
	const testUrl = 'http://test.robokassa.ru/Index.aspx';

	const securityType = 'MD5';
	const testMode = false;

	protected $options = [
		'onSuccessPayment' => null,
	];

	public function __construct($options = [])
	{
		$this->options = extend($this->options, $options);
	}

	public static function getUrl()
	{
		return static::productionUrl;
		/*
		return static::testMode
			? static::testUrl
			: static::productionUrl;
		*/
	}

	public static function log($message)
	{
		simple_debug($message, 'Robokassa', "-payment");
	}

	public static function getMerchantLogin()
	{
		return static::login;
	}

	public static function getPassword1()
	{
		return static::testMode
			? static::password1
			: static::testPassword1;
	}

	public static function getPassword2()
	{
		return static::testMode
			? static::password2
			: static::testPassword2;
	}

	public static function isTestMode()
	{
		return static::testMode;
	}

	/**
	 * @param \diPaymentDraftModel $draft
	 * @param array $opts
	 * How to calculate amount: https://partner.robokassa.ru/Help/Doc/f5af7f3b-9c27-41de-b1c3-0aa76445ecd6
	 * @return string
	 */
	public static function getForm(\diPaymentDraftModel $draft, $opts = [])
	{
		$action = static::getUrl();

		$opts = extend([
			'amount' => $draft->getAmount(),
			'userId' => $draft->getUserId(),
			'draftId' => $draft->getId(),
			'customerEmail' => '',
			'customerPhone' => '',
			'autoSubmit' => false,
			'buttonCaption' => 'Заплатить',
			'paymentVendor' => '',
			'additionalParams' => [],
		], $opts);

		array_walk($opts, function(&$item) {
			$item = \diDB::_out($item);
		});

		$button = !$opts['autoSubmit'] ? "<button type=\"submit\">{$opts["buttonCaption"]}</button>" : '';
		$redirectScript = $opts['autoSubmit'] ? \diPayment::getAutoSubmitScript() : '';

		$params = extend([
			'MrchLogin' => static::getMerchantLogin(),
			'OutSum' => sprintf('%.2f', $opts['amount']),
			'customerNumber' => $opts['userId'],
			'InvId' => $opts['draftId'],
			'Desc' => '',
			'SignatureValue' => static::getSignature($draft),
			'IncCurrLabel' => $opts['paymentVendor'] ? Vendor::code($opts['paymentVendor']) : null,
			'Culture' => 'ru',
			'Encoding' => 'utf-8',
		], $opts['additionalParams']);

		if (self::isTestMode())
		{
			$params['IsTest'] = 1;
		}

		$paramsStr = join("\n\t", array_filter(array_map(function($name, $value) {
			return $value !== null ? \diPayment::getHiddenInput($name, $value) : '';
		}, array_keys($params), $params)));

		$form = <<<EOF
<form action="{$action}" method="post" target="_top">
	{$paramsStr}
	{$button}
</form>
$redirectScript
EOF;

		static::log("Robokassa form:\n" . $form);

		return $form;
	}

	public static function getSignature(\diPaymentDraftModel $draft)
	{
		$cost = sprintf('%.2f', $draft->getAmount());

		$source = [
			static::getMerchantLogin(),
			$cost,
			$draft->getId(),
			static::getPassword1(),
		];

		return md5(join(':', $source));
	}

	public static function getSignature2(\diPaymentDraftModel $draft)
	{
		$source = [
			$draft->getAmount(),
			$draft->getId(),
			static::getPassword2(),
			static::getMerchantLogin(),
		];

		return md5(join(':', $source));
	}
}