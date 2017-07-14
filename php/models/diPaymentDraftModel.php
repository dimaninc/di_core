<?php
/**
 * Created by diModelsManager
 * Date: 14.12.2015
 * Time: 18:31
 */

use diCore\Traits\TargetInside;
use diCore\Payment\System;

/**
 * Class diPaymentDraftModel
 * Methods list for IDE
 *
 * @method integer	getTargetType
 * @method integer	getTargetId
 * @method integer	getUserId
 * @method string	getPaySystem
 * @method string	getVendor
 * @method string	getCurrency
 * @method double	getAmount
 * @method string	getDateReserved
 * @method integer	getStatus
 *
 * @method bool hasTargetType
 * @method bool hasTargetId
 * @method bool hasUserId
 * @method bool hasPaySystem
 * @method bool hasVendor
 * @method bool hasCurrency
 * @method bool hasAmount
 * @method bool hasDateReserved
 * @method bool hasStatus
 *
 * @method diPaymentDraftModel setTargetType($value)
 * @method diPaymentDraftModel setTargetId($value)
 * @method diPaymentDraftModel setUserId($value)
 * @method diPaymentDraftModel setPaySystem($value)
 * @method diPaymentDraftModel setVendor($value)
 * @method diPaymentDraftModel setCurrency($value)
 * @method diPaymentDraftModel setAmount($value)
 * @method diPaymentDraftModel setDateReserved($value)
 * @method diPaymentDraftModel setStatus($value)
 */
class diPaymentDraftModel extends diModel
{
	use TargetInside;

	const type = diTypes::payment_draft;
	protected $table = "payment_drafts";

	protected $customDateFields = ["date_reserved", "date_payed"];

	public function validate()
	{
		if (!$this->hasTargetType())
		{
			$this->addValidationError("Target type required");
		}

		if (!$this->hasTargetId())
		{
			$this->addValidationError("Target ID required");
		}

		if (!$this->hasUserId())
		{
			$this->addValidationError("User ID required");
		}

		if (!$this->hasAmount())
		{
			$this->addValidationError("Amount required");
		}

		return parent::validate();
	}

	public function getTargetTypeStr()
	{
		return \diTypes::getTitle($this->getTargetType());
	}

	public function getPaySystemStr()
	{
		return \diPayment::systemTitle($this->getPaySystem());
	}

	public function getVendorStr()
	{
		switch ($this->getPaySystem())
		{
			case System::yandex_kassa:
				return \diCore\Payment\Yandex\Vendor::title($this->getVendor());

			case System::robokassa:
				return \diCore\Payment\Robokassa\Vendor::title($this->getVendor());

			case System::mixplat:
				return \diCore\Payment\Mixplat\MobileVendors::title($this->getVendor());

			case System::sms_online:
				return 'Not implemented yet';

			case System::paypal:
				return 'Paypal';

			default:
				return $this->hasVendor() ? 'Vendor #' . $this->getVendor() : '';
		}
	}

	public function getCurrencyStr()
	{
		return \diPayment::currencyTitle($this->getCurrency());
	}

	public function getStatusStr()
	{
		switch ($this->getPaySystem())
		{
			case System::mixplat:
				return \diCore\Payment\Mixplat\ResultStatus::title($this->getStatus());

			case System::sms_online:
				return 'Not implemented yet';

			default:
				return null;
		}
	}

	public function getCustomTemplateVars()
	{
		return extend(parent::getCustomTemplateVars(), [
			"target_type_str" => $this->getTargetTypeStr(),
			'pay_system_str' => $this->getPaySystemStr(),
			"vendor_str" => $this->getVendorStr(),
			"currency_str" => $this->getCurrencyStr(),
			'status_str' => $this->getStatusStr(),
		]);
	}
}