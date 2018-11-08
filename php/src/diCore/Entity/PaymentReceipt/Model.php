<?php
/**
 * Created by diModelsManager
 * Date: 14.12.2015
 * Time: 18:31
 */

namespace diCore\Entity\PaymentReceipt;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getRnd
 * @method string	getOuterNumber
 * @method string	getDatePayed
 * @method string	getDateUploaded
 * @method integer	getDraftId
 *
 * @method bool hasRnd
 * @method bool hasOuterNumber
 * @method bool hasDatePayed
 * @method bool hasDateUploaded
 * @method bool hasDraftId
 *
 * @method Model setRnd($value)
 * @method Model setOuterNumber($value)
 * @method Model setDatePayed($value)
 * @method Model setDateUploaded($value)
 * @method Model setDraftId($value)
 */
class Model extends \diCore\Entity\PaymentDraft\Model
{
	const type = \diTypes::payment_receipt;
	protected $table = 'payment_receipts';

	public function validate()
	{
		if (!$this->hasOuterNumber())
		{
			$this->addValidationError('Outer number required');
		}

		if (!$this->hasDraftId())
		{
			$this->addValidationError('Draft ID required');
		}

		return parent::validate();
	}

	public function asArrayForCashDesk()
    {
        return [
            'id' => $this->getId(),
            'user_id' => $this->getUserId(),
        ];
    }
}