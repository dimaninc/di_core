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
 * @method $this filterById($value, $operator = null)
 * @method $this filterByTargetType($value, $operator = null)
 * @method $this filterByTargetId($value, $operator = null)
 * @method $this filterByUserId($value, $operator = null)
 * @method $this filterByPaySystem($value, $operator = null)
 * @method $this filterByVendor($value, $operator = null)
 * @method $this filterByCurrency($value, $operator = null)
 * @method $this filterByAmount($value, $operator = null)
 * @method $this filterByDateReserved($value, $operator = null)
 * @method $this filterByStatus($value, $operator = null)
 * @method $this filterByPaid($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByTargetType($direction = null)
 * @method $this orderByTargetId($direction = null)
 * @method $this orderByUserId($direction = null)
 * @method $this orderByPaySystem($direction = null)
 * @method $this orderByVendor($direction = null)
 * @method $this orderByCurrency($direction = null)
 * @method $this orderByAmount($direction = null)
 * @method $this orderByDateReserved($direction = null)
 * @method $this orderByStatus($direction = null)
 * @method $this orderByPaid($direction = null)
 *
 * @method $this selectId()
 * @method $this selectTargetType()
 * @method $this selectTargetId()
 * @method $this selectUserId()
 * @method $this selectPaySystem()
 * @method $this selectVendor()
 * @method $this selectCurrency()
 * @method $this selectAmount()
 * @method $this selectDateReserved()
 * @method $this selectStatus()
 * @method $this selectPaid()
 */
class Collection extends \diCollection
{
	const type = \diTypes::payment_draft;
	protected $table = 'payment_drafts';
	protected $modelType = 'payment_draft';
}