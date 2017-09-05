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
 * @method Collection filterByRnd($value, $operator = null)
 * @method Collection filterByOuterNumber($value, $operator = null)
 * @method Collection filterByDatePayed($value, $operator = null)
 * @method Collection filterByDraftId($value, $operator = null)
 *
 * @method Collection orderByRnd($direction = null)
 * @method Collection orderByOuterNumber($direction = null)
 * @method Collection orderByDatePayed($direction = null)
 * @method Collection orderByDraftId($direction = null)
 *
 * @method Collection selectRnd()
 * @method Collection selectOuterNumber()
 * @method Collection selectDatePayed()
 * @method Collection selectDraftId()
 */
class Collection extends \diCore\Entity\PaymentDraft\Collection
{
	const type = \diTypes::payment_receipt;
	protected $table = "payment_receipts";
	protected $modelType = "payment_receipt";
}