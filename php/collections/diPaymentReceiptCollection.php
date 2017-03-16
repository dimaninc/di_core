<?php
/**
 * Created by diModelsManager
 * Date: 20.09.2016
 * Time: 23:34
 */

/**
 * Class diPaymentReceiptCollection
 * Methods list for IDE
 *
 * @method diPaymentReceiptCollection filterById($value, $operator = null)
 * @method diPaymentReceiptCollection filterByTargetType($value, $operator = null)
 * @method diPaymentReceiptCollection filterByTargetId($value, $operator = null)
 * @method diPaymentReceiptCollection filterByUserId($value, $operator = null)
 * @method diPaymentReceiptCollection filterByPaySystem($value, $operator = null)
 * @method diPaymentReceiptCollection filterByVendor($value, $operator = null)
 * @method diPaymentReceiptCollection filterByCurrency($value, $operator = null)
 * @method diPaymentReceiptCollection filterByAmount($value, $operator = null)
 * @method diPaymentReceiptCollection filterByRnd($value, $operator = null)
 * @method diPaymentReceiptCollection filterByOuterNumber($value, $operator = null)
 * @method diPaymentReceiptCollection filterByDateReserved($value, $operator = null)
 * @method diPaymentReceiptCollection filterByDatePayed($value, $operator = null)
 * @method diPaymentReceiptCollection filterByDraftId($value, $operator = null)
 *
 * @method diPaymentReceiptCollection orderById($direction = null)
 * @method diPaymentReceiptCollection orderByTargetType($direction = null)
 * @method diPaymentReceiptCollection orderByTargetId($direction = null)
 * @method diPaymentReceiptCollection orderByUserId($direction = null)
 * @method diPaymentReceiptCollection orderByPaySystem($direction = null)
 * @method diPaymentReceiptCollection orderByVendor($direction = null)
 * @method diPaymentReceiptCollection orderByCurrency($direction = null)
 * @method diPaymentReceiptCollection orderByAmount($direction = null)
 * @method diPaymentReceiptCollection orderByRnd($direction = null)
 * @method diPaymentReceiptCollection orderByOuterNumber($direction = null)
 * @method diPaymentReceiptCollection orderByDateReserved($direction = null)
 * @method diPaymentReceiptCollection orderByDatePayed($direction = null)
 * @method diPaymentReceiptCollection orderByDraftId($direction = null)
 *
 * @method diPaymentReceiptCollection selectId()
 * @method diPaymentReceiptCollection selectTargetType()
 * @method diPaymentReceiptCollection selectTargetId()
 * @method diPaymentReceiptCollection selectUserId()
 * @method diPaymentReceiptCollection selectPaySystem()
 * @method diPaymentReceiptCollection selectVendor()
 * @method diPaymentReceiptCollection selectCurrency()
 * @method diPaymentReceiptCollection selectAmount()
 * @method diPaymentReceiptCollection selectRnd()
 * @method diPaymentReceiptCollection selectOuterNumber()
 * @method diPaymentReceiptCollection selectDateReserved()
 * @method diPaymentReceiptCollection selectDatePayed()
 * @method diPaymentReceiptCollection selectDraftId()
 */
class diPaymentReceiptCollection extends diCollection
{
	const type = diTypes::payment_receipt;
	protected $table = "payment_receipts";
	protected $modelType = "payment_receipt";
}