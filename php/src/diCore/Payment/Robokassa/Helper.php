<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08.07.2017
 * Time: 16:47
 */

namespace diCore\Payment\Robokassa;

use diCore\Data\Types;
use diCore\Entity\PaymentDraft\Model;
use diCore\Tool\Logger;
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

	const securityType = 'MD5';
	const testMode = false;

	/** @var  Model */
	private $draft;

	protected $options = [
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
		Logger::getInstance()->log($message, 'Robokassa', '-payment');
	}

	public static function getMerchantLogin()
	{
		return static::login;
	}

	public static function getPassword1()
	{
		return static::testMode
			? static::testPassword1
			: static::password1;
	}

	public static function getPassword2()
	{
		return static::testMode
			? static::testPassword2
			: static::password2;
	}

	public static function isTestMode()
	{
		return static::testMode;
	}

	public static function formatCost($cost)
	{
		return sprintf('%.2f', $cost);
	}

	public static function getRequest($url)
	{
		$request = \Requests::get(
			$url,
			[], [
				'transport' => 'Requests_Transport_fsockopen',
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

		$reducedCost = !empty($regs[1]) ? (float)$regs[1] : 0;

		return $reducedCost ?: $cost;
	}

	/**
	 * @param \diCore\Entity\PaymentDraft\Model $draft
	 * @param array $opts
	 * How to calculate amount: https://partner.robokassa.ru/Help/Doc/f5af7f3b-9c27-41de-b1c3-0aa76445ecd6
	 * @return string
	 */
	public static function getForm(\diCore\Entity\PaymentDraft\Model $draft, $opts = [])
	{
		$action = static::getUrl();

		$opts = extend([
			'amount' => $draft->getAmount(),
			'draftId' => $draft->getId(),
			'description' => '',
			'customerId' => $draft->getUserId(),
			'customerEmail' => '',
			'customerPhone' => '',
			'autoSubmit' => false,
			'buttonCaption' => 'Заплатить',
			'additionalParams' => [],
		], $opts);

		$paymentVendor = Vendor::code($draft->getVendor());
		$opts['amount'] = static::getReducedCost($opts['amount'], $paymentVendor);

		array_walk($opts, function(&$item) {
			$item = \diDB::_out($item);
		});

		$button = !$opts['autoSubmit'] ? "<button type=\"submit\">{$opts["buttonCaption"]}</button>" : '';
		$redirectScript = $opts['autoSubmit'] ? \diPayment::getAutoSubmitScript() : '';

		$params = extend([
			'MrchLogin' => static::getMerchantLogin(),
			'OutSum' => self::formatCost($opts['amount']),
			'InvId' => $opts['draftId'],
			'Desc' => $opts['description'],
			'SignatureValue' => static::getSignatureForm($draft),
			'IncCurrLabel' => $paymentVendor,
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

	public static function getSignatureForm(\diCore\Entity\PaymentDraft\Model $draft)
	{
		$source = [
			static::getMerchantLogin(),
			static::formatCost($draft->getAmount()),
			$draft->getId(),
			static::getPassword1(),
		];

		return md5(join(':', $source));
	}

	public static function getSignatureResult(\diCore\Entity\PaymentDraft\Model $draft, $alt = false)
	{
		$source = [
			static::formatCost($draft->getAmount()),
			$draft->getId(),
			static::getPassword2(),
			//static::getMerchantLogin(),
		];

		if ($alt)
		{
			$source[] = static::getMerchantLogin();
		}

		return md5(join(':', $source));
	}

	public static function getSignatureSuccess(\diCore\Entity\PaymentDraft\Model $draft)
	{
		$source = [
			static::formatCost($draft->getAmount()),
			$draft->getId(),
			static::getPassword1(),
		];

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
	 * @return Model
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

			if (!$this->getDraft()->exists())
			{
				throw new \Exception('No draft found');
			}

			/*
			if ($this->getDraft()->getAmount() != $cost)
			{
				throw new \Exception('Cost not match: (their) ' . $cost . ', (our) ' . $draft->getAmount());
			}
			*/

			$s1 = static::getSignatureResult($this->getDraft());
			$s2 = static::getSignatureResult($this->getDraft(), true);

			if ($signature != $s1 && $signature != $s2)
			{
				throw new \Exception('Signature not matched (' . $signature . ' != ' . $s1 . ', ' . $s2 . ')');
			}

			self::log('Result method OK');

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

			if (!$this->getDraft()->exists())
			{
				throw new \Exception('No draft found');
			}

			if ($signature != static::getSignatureSuccess($this->getDraft()))
			{
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