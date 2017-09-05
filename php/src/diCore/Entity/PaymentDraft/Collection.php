<?php
/**
 * Created by diModelsManager
 * Date: 20.09.2016
 * Time: 23:34
 */

namespace diCore\Entity\PaymentDraft;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByTargetType($value, $operator = null)
 * @method Collection filterByTargetId($value, $operator = null)
 * @method Collection filterByUserId($value, $operator = null)
 * @method Collection filterByPaySystem($value, $operator = null)
 * @method Collection filterByVendor($value, $operator = null)
 * @method Collection filterByCurrency($value, $operator = null)
 * @method Collection filterByAmount($value, $operator = null)
 * @method Collection filterByDateReserved($value, $operator = null)
 * @method Collection filterByStatus($value, $operator = null)
 * @method Collection filterByPaid($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByTargetType($direction = null)
 * @method Collection orderByTargetId($direction = null)
 * @method Collection orderByUserId($direction = null)
 * @method Collection orderByPaySystem($direction = null)
 * @method Collection orderByVendor($direction = null)
 * @method Collection orderByCurrency($direction = null)
 * @method Collection orderByAmount($direction = null)
 * @method Collection orderByDateReserved($direction = null)
 * @method Collection orderByStatus($direction = null)
 * @method Collection orderByPaid($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectTargetType()
 * @method Collection selectTargetId()
 * @method Collection selectUserId()
 * @method Collection selectPaySystem()
 * @method Collection selectVendor()
 * @method Collection selectCurrency()
 * @method Collection selectAmount()
 * @method Collection selectDateReserved()
 * @method Collection selectStatus()
 * @method Collection selectPaid()
 */
class Collection extends \diCollection
{
	const type = \diTypes::payment_draft;
	protected $table = "payment_drafts";
	protected $modelType = "payment_draft";
}