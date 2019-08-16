<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 15:33
 */

namespace diCore\Entity\Order;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterByUserId($value, $operator = null)
 * @method $this filterByType($value, $operator = null)
 * @method $this filterByInvoice($value, $operator = null)
 * @method $this filterByStatus($value, $operator = null)
 * @method $this filterByPack($value, $operator = null)
 * @method $this filterByCost($value, $operator = null)
 * @method $this filterByDelivery($value, $operator = null)
 * @method $this filterByDeliveryCost($value, $operator = null)
 * @method $this filterByPaymentId($value, $operator = null)
 * @method $this filterByComments($value, $operator = null)
 * @method $this filterByUploadDate($value, $operator = null)
 * @method $this filterByExportedTo1c($value, $operator = null)
 * @method $this filterByCreatedAt($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByUserId($direction = null)
 * @method $this orderByType($direction = null)
 * @method $this orderByInvoice($direction = null)
 * @method $this orderByStatus($direction = null)
 * @method $this orderByPack($direction = null)
 * @method $this orderByCost($direction = null)
 * @method $this orderByDelivery($direction = null)
 * @method $this orderByDeliveryCost($direction = null)
 * @method $this orderByPaymentId($direction = null)
 * @method $this orderByComments($direction = null)
 * @method $this orderByUploadDate($direction = null)
 * @method $this orderByExportedTo1c($direction = null)
 * @method $this orderByCreatedAt($direction = null)
 *
 * @method $this selectId()
 * @method $this selectUserId()
 * @method $this selectType()
 * @method $this selectInvoice()
 * @method $this selectStatus()
 * @method $this selectPack()
 * @method $this selectCost()
 * @method $this selectDelivery()
 * @method $this selectDeliveryCost()
 * @method $this selectPaymentId()
 * @method $this selectComments()
 * @method $this selectUploadDate()
 * @method $this selectExportedTo1c()
 * @method $this selectCreatedAt()
 */
class Collection extends \diCollection
{
	const type = \diTypes::order;
	protected $table = 'order';
	protected $modelType = 'order';
}