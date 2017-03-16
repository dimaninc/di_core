<?php
/**
 * Created by diModelsManager
 * Date: 14.12.2015
 * Time: 18:31
 */
/**
 * Class diPaymentReceiptModel
 * Methods list for IDE
 *
 * @method string	getRnd
 * @method string	getOuterNumber
 * @method string	getDatePayed
 * @method integer	getDraftId
 *
 * @method bool hasRnd
 * @method bool hasOuterNumber
 * @method bool hasDatePayed
 * @method bool hasDraftId
 *
 * @method diPaymentReceiptModel setRnd($value)
 * @method diPaymentReceiptModel setOuterNumber($value)
 * @method diPaymentReceiptModel setDatePayed($value)
 * @method diPaymentReceiptModel setDraftId($value)
 */
class diPaymentReceiptModel extends diPaymentDraftModel
{
	const type = diTypes::payment_receipt;
	protected $table = "payment_receipts";

	public function validate()
	{
		if (!$this->hasOuterNumber())
		{
			$this->addValidationError("Outer number required");
		}

		if (!$this->hasDraftId())
		{
			$this->addValidationError("Draft ID required");
		}

		return parent::validate();
	}
}