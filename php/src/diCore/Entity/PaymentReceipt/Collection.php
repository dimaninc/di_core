<?php
/**
 * Created by diModelsManager
 * Date: 20.09.2016
 * Time: 23:34
 */

namespace diCore\Entity\PaymentReceipt;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterByRnd($value, $operator = null)
 * @method $this filterByOuterNumber($value, $operator = null)
 * @method $this filterByDatePayed($value, $operator = null)
 * @method $this filterByDateUploaded($value, $operator = null)
 * @method $this filterByDraftId($value, $operator = null)
 *
 * @method $this orderByRnd($direction = null)
 * @method $this orderByOuterNumber($direction = null)
 * @method $this orderByDatePayed($direction = null)
 * @method $this orderByDateUploaded($direction = null)
 * @method $this orderByDraftId($direction = null)
 *
 * @method $this selectRnd()
 * @method $this selectOuterNumber()
 * @method $this selectDatePayed()
 * @method $this selectDateUploaded()
 * @method $this selectDraftId()
 */
class Collection extends \diCore\Entity\PaymentDraft\Collection
{
	const type = \diTypes::payment_receipt;
	protected $table = 'payment_receipts';
	protected $modelType = 'payment_receipt';
}