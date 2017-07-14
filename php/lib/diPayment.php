<?php

use diCore\Payment\System;

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 14.12.15
 * Time: 17:35
 */
class diPayment
{
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

	public function __construct($targetType, $targetId, $userId)
	{
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
			: "Unknown currency #" . $currencyId;
	}

	public static function create($targetType, $targetId, $userId)
	{
		if (\diLib::exists(static::childClassName))
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
	 * @param int $systemId
	 * @param int $vendorId
	 * @return \diPaymentDraftModel
	 * @throws Exception
	 */
	public static function createDraft($targetType, $targetId, $userId, $amount, $systemId, $vendorId = 0)
	{
		static::log("Creating draft for [$targetType, $targetId, $userId, $amount, $systemId, $vendorId]");
		static::log("Route: " . \diRequest::requestUri());

		/** @var diPaymentDraftModel $draft */
		$draft = \diModel::create(\diTypes::payment_draft);

		$draft
			->setUserId($userId)
			->setTargetType($targetType)
			->setTargetId($targetId)
			->setPaySystem((int)$systemId)
			->setVendor((int)$vendorId)
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