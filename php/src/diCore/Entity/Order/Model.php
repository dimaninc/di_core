<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 15:33
 */

namespace diCore\Entity\Order;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getUserId
 * @method integer	getType
 * @method integer	getInvoice
 * @method integer	getStatus
 * @method integer	getPack
 * @method double	getCost
 * @method integer	getDelivery
 * @method double	getDeliveryCost
 * @method integer	getPaymentId
 * @method string	getComments
 * @method string	getUploadDate
 * @method integer	getExportedTo1c
 * @method string	getCreatedAt
 *
 * @method bool hasUserId
 * @method bool hasType
 * @method bool hasInvoice
 * @method bool hasStatus
 * @method bool hasPack
 * @method bool hasCost
 * @method bool hasDelivery
 * @method bool hasDeliveryCost
 * @method bool hasPaymentId
 * @method bool hasComments
 * @method bool hasUploadDate
 * @method bool hasExportedTo1c
 * @method bool hasCreatedAt
 *
 * @method $this setUserId($value)
 * @method $this setType($value)
 * @method $this setInvoice($value)
 * @method $this setStatus($value)
 * @method $this setPack($value)
 * @method $this setCost($value)
 * @method $this setDelivery($value)
 * @method $this setDeliveryCost($value)
 * @method $this setPaymentId($value)
 * @method $this setComments($value)
 * @method $this setUploadDate($value)
 * @method $this setExportedTo1c($value)
 * @method $this setCreatedAt($value)
 */
class Model extends \diModel
{
	const type = \diTypes::order;
	const table = 'order';
	protected $table = 'order';
}