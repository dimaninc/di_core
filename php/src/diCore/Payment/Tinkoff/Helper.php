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

use diCore\Traits\BasicCreate;

class Helper
{
	use BasicCreate;

	const login = null;
	const password = null;

	const productionUrl = 'https://securepay.tinkoff.ru/v2';

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
	}

	public static function log($message)
	{
		simple_debug($message, 'Tinkoff', "-payment");
	}

	public static function getMerchantLogin()
	{
		return static::login;
	}

	public static function getPassword()
	{
		return static::password;
	}

	/**
	 * @param \diCore\Entity\PaymentDraft\Model $draft
	 * @param array $opts
	 * @return string
	 */
	public static function getForm(\diCore\Entity\PaymentDraft\Model $draft, $opts = [])
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

		static::log("Tinkoff form:\n" . $form);

		return $form;
	}

	public static function getSignature(\diCore\Entity\PaymentDraft\Model $draft)
	{
		$cost = sprintf('%.2f', $draft->getAmount());

		$source = [
			static::getMerchantLogin(),
			$cost,
			$draft->getId(),
			static::getPassword(),
		];

		return md5(join(':', $source));
	}
}