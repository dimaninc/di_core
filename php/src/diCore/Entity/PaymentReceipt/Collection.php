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
 * @method $this filterByDatePayed($value, $operator = null)
 * @method $this filterByDateUploaded($value, $operator = null)
 * @method $this filterByDraftId($value, $operator = null)
 * @method $this filterByFiscalMark($value, $operator = null)
 * @method $this filterByFiscalDocId($value, $operator = null)
 * @method $this filterByFiscalDate($value, $operator = null)
 * @method $this filterByFiscalSession($value, $operator = null)
 * @method $this filterByFiscalNumber($value, $operator = null)
 *
 * @method $this orderByRnd($direction = null)
 * @method $this orderByDatePayed($direction = null)
 * @method $this orderByDateUploaded($direction = null)
 * @method $this orderByDraftId($direction = null)
 * @method $this orderByFiscalMark($direction = null)
 * @method $this orderByFiscalDocId($direction = null)
 * @method $this orderByFiscalDate($direction = null)
 * @method $this orderByFiscalSession($direction = null)
 * @method $this orderByFiscalNumber($direction = null)
 *
 * @method $this selectRnd()
 * @method $this selectDatePayed()
 * @method $this selectDateUploaded()
 * @method $this selectDraftId()
 * @method $this selectFiscalMark()
 * @method $this selectFiscalDocId()
 * @method $this selectFiscalDate()
 * @method $this selectFiscalSession()
 * @method $this selectFiscalNumber()
 */
class Collection extends \diCore\Entity\PaymentDraft\Collection
{
	const type = \diTypes::payment_receipt;
	protected $table = 'payment_receipts';
	protected $modelType = 'payment_receipt';
}