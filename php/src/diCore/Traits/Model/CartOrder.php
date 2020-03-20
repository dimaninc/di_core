<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 29.08.2019
 * Time: 09:43
 */

namespace diCore\Traits\Model;

use diCore\Tool\CollectionCache;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getUserId
 * @method string	getCreatedAt
 *
 * @method bool hasUserId
 * @method bool hasCreatedAt
 *
 * @method $this setUserId($value)
 * @method $this setCreatedAt($value)
 */
trait CartOrder
{
    /**
     * CartOrder settings
    const item_filter_field = 'cart_id';
    const item_type = \diTypes::cart_item;
    const pre_cache_needed = false;
     */

    /** @var array|null */
    protected $items = null;

    protected function preCacheItems()
    {
        if (static::pre_cache_needed) {
            $byType = [];

            /** @var TargetInside $item */
            foreach ($this->items as $item) {
                if (!isset($byType[$item->getTargetType()])) {
                    $byType[$item->getTargetType()] = [];
                }

                $byType[$item->getTargetType()][] = $item->getTargetId();
            }

            foreach ($byType as $type => $ids) {
                CollectionCache::addManual($type, 'id', $ids);
            }
        }

        return $this;
    }

    public function getItems($forceRefresh = true)
    {
        if (($this->items === null && $this->exists()) || $forceRefresh) {
            $items = \diCollection::create(static::item_type);
            $items
                ->filterBy(self::item_filter_field, $this->getId())
                ->orderById();

            $this->items = [];

            foreach ($items as $item) {
                $this->items[] = $item;
            }

            $this->preCacheItems();
        }

        return $this->items ?: [];
    }

    public function getItem($targetType, $targetId, $additionalFields = [])
    {
        /** @var TargetInside $i */
        foreach ($this->getItems() as $i) {
            if ($i->getTargetType() == $targetType && $i->getTargetId() == $targetId) {
                $ok = true;

                foreach ($additionalFields as $k => $v) {
                    if ($i->get($k) != $v) {
                        $ok = false;

                        break;
                    }
                }

                if ($ok) {
                    $item = $i;

                    break;
                }
            }
        }

        return $item ?? \diModel::create(static::item_type);
    }

    public function getTotalQuantity($options = [])
    {
        $count = 0;
        $options = $this->prepareOptions($options);

        foreach ($this->getItems() as $item) {
            $count += $this->getQuantityOfItem($item, $options);
        }

        return $count;
    }

    public function getTotalRowsCount($options = [])
    {
        $count = 0;
        $options = $this->prepareOptions($options);

        foreach ($this->getItems(true) as $item) {
            $count += $this->getRowCountOfItem($item, $options);
        }

        return $count;
    }

    public function getTotalCost($options = [])
    {
        $cost = 0;
        $options = $this->prepareOptions($options);

        foreach ($this->getItems() as $item) {
            $cost += $this->getCostOfItem($item, $options);
            $cost += $this->getAdditionalCostOfItem($item, $options);
        }

        return $cost;
    }

    public function getTotals()
    {
        return [
            'cost' => $this->getTotalCost(),
            'quantity' => $this->getTotalQuantity(),
            'rows_count' => $this->getTotalRowsCount(),
        ];
    }

    protected function killItems()
    {
        /** @var \diModel $item */
        foreach ($this->getItems() as $item) {
            $item->hardDestroy();
        }

        return $this;
    }
}