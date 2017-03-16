<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 14.12.15
 * Time: 17:35
 */
class diPayment
{
	// payment systems
	const webmoney = 1;
	const robo = 2;
	const yandex = 3;
	const mixplat = 4;
	const paypal = 5;
	const sms_online = 6;

	public static $systems = [
		self::webmoney => "Webmoney",
		self::robo => "Робокасса",
		self::yandex => "Yandex.Kassa",
		self::mixplat => 'Mixplat',
		self::paypal => 'Paypal',
		self::sms_online => 'SMS online',
	];

	// currencies
	const rub = 1;
	const usd = 2;
	const eur = 3;

	public static $currencies = [
		self::rub => "Руб",
		self::usd => "Usd",
		self::eur => "Eur",
	];

	const childClassName = "diCustomPayment";

	const DEFAULT_SYSTEM = self::yandex;

	public function __construct($targetType, $targetId, $userId)
	{
	}

	public static function getCurrentSystems()
	{
		return static::$systems;
	}

	public static function systemTitle($systemId)
	{
		return isset(static::$systems[$systemId])
			? static::$systems[$systemId]
			: "Unknown payment system #" . $systemId;
	}

	public static function currencyTitle($currencyId)
	{
		return isset(static::$currencies[$currencyId])
			? static::$currencies[$currencyId]
			: "Unknown currency #" . $currencyId;
	}

	public static function create($targetType, $targetId, $userId)
	{
		if (diLib::exists(static::childClassName))
		{
			$className = static::childClassName;
		}
		else
		{
			$className = get_called_class();
		}

		return new $className($targetType, $targetId, $userId);
	}

	/**
	 * @param int $targetType
	 * @param int $targetId
	 * @param int $userId
	 * @param float $amount
	 * @param int $vendor
	 * @return diPaymentDraftModel
	 * @throws Exception
	 */
	public static function createDraft($targetType, $targetId, $userId, $amount, $vendor = 0)
	{
		static::log("Creating draft for [$targetType, $targetId, $userId, $amount, $vendor]");
		static::log("Route: " . diRequest::requestUri());

		/** @var diPaymentDraftModel $draft */
		$draft = diModel::create(diTypes::payment_draft);

		switch ($vendor)
		{
			case diYandexKassaVendors::MIXPLAT:
				$system = self::mixplat;
				$vendor = 0;
				break;

			case diYandexKassaVendors::SMS_ONLINE:
				$system = self::sms_online;
				$vendor = 0;
				break;

			case diYandexKassaVendors::PAYPAL:
				$system = self::paypal;
				$vendor = 0;
				break;

			default:
				$system = self::DEFAULT_SYSTEM;
				break;
		}

		$draft
			->setUserId($userId)
			->setTargetType($targetType)
			->setTargetId($targetId)
			->setPaySystem($system)
			->setVendor((int)$vendor)
			->setCurrency(self::rub)
			->setAmount($amount)
			->save();

		static::log("Draft created: #{$draft->getId()}");

		return $draft;
	}

	public static function getHiddenInput($name, $value)
	{
		return "<input name=\"{$name}\" value=\"{$value}\" type=\"hidden\">";
	}

	public static function getAutoSubmitScript()
	{
		return <<<EOF
<script type="text/javascript">
document.forms[0].submit();
</script>
EOF;
	}

	public static function log($message)
	{
		simple_debug($message, "diPayment", "-payment");
	}

	public static function postProcess(diPaymentReceiptModel $receipt)
	{
	}
}