<?php
/**
 * Created by diModelsManager
 * Date: 14.12.2015
 * Time: 18:31
 */

namespace diCore\Entity\PaymentDraft;

use diCore\Payment\System;
use diCore\Payment\Payment;
use diCore\Traits\Model\TargetInside;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getUserId
 * @method string	getOuterNumber
 * @method string	getPaySystem
 * @method string	getVendor
 * @method string	getCurrency
 * @method double	getAmount
 * @method string	getDateReserved
 * @method integer	getStatus
 * @method integer	getPaid
 * @method integer	getIp
 *
 * @method bool hasUserId
 * @method bool hasOuterNumber
 * @method bool hasPaySystem
 * @method bool hasVendor
 * @method bool hasCurrency
 * @method bool hasAmount
 * @method bool hasDateReserved
 * @method bool hasStatus
 * @method bool hasPaid
 * @method bool hasIp
 *
 * @method $this setUserId($value)
 * @method $this setOuterNumber($value)
 * @method $this setPaySystem($value)
 * @method $this setVendor($value)
 * @method $this setCurrency($value)
 * @method $this setAmount($value)
 * @method $this setDateReserved($value)
 * @method $this setStatus($value)
 * @method $this setPaid($value)
 * @method $this setIp($value)
 */
class Model extends \diModel
{
	use TargetInside;

	const type = \diTypes::payment_draft;
    const table = 'payment_drafts';
	protected $table = 'payment_drafts';

	const TARGET_IS_NECESSARY = true;
	protected $customDateFields = ['date_reserved', 'date_payed', 'date_uploaded'];

	public static function isUserIdNeeded()
    {
        return true;
    }

	public function validate()
	{
		if (!$this->hasTargetType() && static::TARGET_IS_NECESSARY) {
			$this->addValidationError('Target type required', 'target_type');
		}

		if (!$this->hasTargetId() && static::TARGET_IS_NECESSARY) {
			$this->addValidationError('Target ID required', 'target_id');
		}

		if (!$this->hasUserId() && static::isUserIdNeeded()) {
			$this->addValidationError('User ID required', 'user_id');
		}

		if (!$this->hasAmount()) {
			$this->addValidationError('Amount required', 'amount');
		}

		return parent::validate();
	}

	public function getTargetTypeStr()
	{
		return \diTypes::getTitle($this->getTargetType());
	}

	public function getPaySystemStr()
	{
		return Payment::systemTitle($this->getPaySystem());
	}

	public function getVendorStr()
	{
		switch ($this->getPaySystem()) {
			case System::yandex_kassa:
				return \diCore\Payment\Yandex\Vendor::title($this->getVendor());

			case System::robokassa:
				return \diCore\Payment\Robokassa\Vendor::title($this->getVendor());

			case System::tinkoff:
				return \diCore\Payment\Tinkoff\Vendor::title($this->getVendor());

			case System::mixplat:
				return \diCore\Payment\Mixplat\MobileVendors::title($this->getVendor());

			case System::sms_online:
				return 'Not implemented yet';

			case System::paypal:
				return 'Paypal';

            case System::crypto_cloud:
                return 'CryptoCloud';

			default:
				return $this->hasVendor() ? 'Vendor #' . $this->getVendor() : '';
		}
	}

	public function getPaySystemWithVendorShortStr()
	{
		$ar = [
			$this->getPaySystemStr(),
		];

		if (!in_array($this->getPaySystem(), [System::paypal, System::crypto_cloud])) {
			$ar[] = $this->getVendorStr() ?: 'Unknown?';
		}

		return join('/', $ar);
	}

	public function getCurrencyStr()
	{
		return Payment::currencyTitle($this->getCurrency());
	}

	public function getStatusStr()
	{
		switch ($this->getPaySystem()) {
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
			'target_type_str' => $this->getTargetTypeStr(),
			'pay_system_str' => $this->getPaySystemStr(),
			'vendor_str' => $this->getVendorStr(),
			'currency_str' => $this->getCurrencyStr(),
			'status_str' => $this->getStatusStr(),
			'pay_system_with_vendor_short_str' => $this->getPaySystemWithVendorShortStr(),
		]);
	}

    public function getLocationStr()
    {
        $location = \diGeo::location(bin2ip($this->getIp()));

        return join(', ', array_filter([$location->getCity(), $location->getRegionName(), $location->getCountryName()]));
    }
}