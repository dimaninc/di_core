<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 15:33
 */

namespace diCore\Entity\Order;

use diCore\Data\Types;
use diCore\Entity\OrderItem\Model as OrderItem;
use diCore\Traits\Model\CartOrder;

/**
 * Class Model
 * Methods list for IDE
 *
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
 *
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
 *
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
 */
class Model extends \diModel implements \diCore\Interfaces\CartOrder
{
    use CartOrder;

	const type = Types::order;
	const table = 'order';
	protected $table = 'order';

    /**
     * CartOrder settings
     */
    const item_filter_field = 'order_id';
    const item_type = Types::order_item;
    const pre_cache_needed = false;

    protected function prepareOptions($options)
    {
        return $options;
    }

    /**
     * @param $item OrderItem
     * @param $options array
     * @return integer
     */
    public function getQuantityOfItem($item, $options)
    {
        return $item->getQuantity();
    }

    /**
     * @param $item OrderItem
     * @param $options array
     * @return float
     */
    public function getCostOfItem($item, $options)
    {
        return $item->getCost();
    }

    /**
     * @param $item OrderItem
     * @param $options array
     * @return integer
     */
    public function getRowCountOfItem($item, $options)
    {
        return 1;
    }

    public function killRelatedData()
    {
        parent::killRelatedData();

        $this->killItems();

        return $this;
    }

    public function getExportedTo1c()
    {
        return $this->get('exported_to_1c');
    }

    public function hasExportedTo1c()
    {
        return $this->has('exported_to_1c');
    }

    public function setExportedTo1c($value)
    {
        return $this->set('exported_to_1c', $value);
    }
}