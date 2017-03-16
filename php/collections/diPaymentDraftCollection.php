<?php
/**
 * Created by diModelsManager
 * Date: 20.09.2016
 * Time: 23:34
 */

/**
 * Class diPaymentDraftCollection
 * Methods list for IDE
 *
 * @method diPaymentDraftCollection filterById($value, $operator = null)
 * @method diPaymentDraftCollection filterByTargetType($value, $operator = null)
 * @method diPaymentDraftCollection filterByTargetId($value, $operator = null)
 * @method diPaymentDraftCollection filterByUserId($value, $operator = null)
 * @method diPaymentDraftCollection filterByPaySystem($value, $operator = null)
 * @method diPaymentDraftCollection filterByVendor($value, $operator = null)
 * @method diPaymentDraftCollection filterByCurrency($value, $operator = null)
 * @method diPaymentDraftCollection filterByAmount($value, $operator = null)
 * @method diPaymentDraftCollection filterByDateReserved($value, $operator = null)
 * @method diPaymentDraftCollection filterByStatus($value, $operator = null)
 *
 * @method diPaymentDraftCollection orderById($direction = null)
 * @method diPaymentDraftCollection orderByTargetType($direction = null)
 * @method diPaymentDraftCollection orderByTargetId($direction = null)
 * @method diPaymentDraftCollection orderByUserId($direction = null)
 * @method diPaymentDraftCollection orderByPaySystem($direction = null)
 * @method diPaymentDraftCollection orderByVendor($direction = null)
 * @method diPaymentDraftCollection orderByCurrency($direction = null)
 * @method diPaymentDraftCollection orderByAmount($direction = null)
 * @method diPaymentDraftCollection orderByDateReserved($direction = null)
 * @method diPaymentDraftCollection orderByStatus($direction = null)
 *
 * @method diPaymentDraftCollection selectId()
 * @method diPaymentDraftCollection selectTargetType()
 * @method diPaymentDraftCollection selectTargetId()
 * @method diPaymentDraftCollection selectUserId()
 * @method diPaymentDraftCollection selectPaySystem()
 * @method diPaymentDraftCollection selectVendor()
 * @method diPaymentDraftCollection selectCurrency()
 * @method diPaymentDraftCollection selectAmount()
 * @method diPaymentDraftCollection selectDateReserved()
 * @method diPaymentDraftCollection selectStatus()
 */
class diPaymentDraftCollection extends diCollection
{
	const type = diTypes::payment_draft;
	protected $table = "payment_drafts";
	protected $modelType = "payment_draft";
}