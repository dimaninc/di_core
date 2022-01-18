<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 14:43
 */

namespace diCore\Entity\Cart;

use diCore\Data\Types;
use diCore\Entity\CartItem\Model as CartItem;
use diCore\Tool\Auth;
use diCore\Tool\CollectionCache;
use diCore\Traits\Model\CartOrder;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getSessionId
 *
 * @method bool hasSessionId
 *
 * @method $this setSessionId($value)
 */
class Model extends \diModel implements \diCore\Interfaces\CartOrder
{
    use CartOrder {
        getItem as protected parentGetItem;
    }

	const type = Types::cart;
	protected $table = 'cart';

	/** @var $this */
	protected static $instance;
	const only_one_instance = true;

    /**
     * CartOrder settings
     */
    const item_filter_field = 'cart_id';
    const item_type = Types::cart_item;
	const pre_cache_needed = false;

    /**
     * @param null $id
     * @param null $sessionId
     * @param null $userId
     * @return $this
     * @throws \Exception
     */
	public static function autoCreate($id = null, $sessionId = null, $userId = null)
	{
	    if (static::only_one_instance && static::$instance) {
	        return static::$instance;
        }

		if ($id) {
			/** @var $this $model */
			$model = self::create(self::type, $id);
		} else {
			$q = [
				"session_id = '" . ($sessionId ?: \diSession::id()) . "'",
			];

			if (Auth::i()->getUserId()) {
				$q[] = "user_id = '" . ($userId ?: Auth::i()->getUserId()) . "'";
			}

			$model = \diCollection::create(self::type)->filterManual(join(' OR ', $q))->getFirstItem();
		}

		if (!$model->exists()) {
			$model
				->setSessionId($sessionId ?: \diSession::id());
		}

		if (!$model->hasUserId() && ($userId || Auth::i()->getUserId())) {
            $model
                ->setUserId($userId ?: Auth::i()->getUserId());
        }

        if (static::only_one_instance) {
            static::$instance = $model;
        }

		return $model;
	}

	protected function prepareOptions($options)
    {
        return $options;
    }

    /**
     * @param $item CartItem
     * @param $options array
     * @return integer
     */
	public function getQuantityOfItem($item, $options)
    {
        return $item->getQuantity();
    }

    /**
     * @param $item CartItem
     * @param $options array
     * @return float
     */
    public function getCostOfItem($item, $options)
    {
        return $item->getCost();
    }

    /**
     * @param $item CartItem
     * @param $options array
     * @return float
     */
    public function getAdditionalCostOfItem($item, $options)
    {
        return 0;
    }

    /**
     * @param $item CartItem
     * @param $options array
     * @return integer
     */
    public function getRowCountOfItem($item, $options)
    {
        return 1;
    }

	public static function migrateToUser($sessionId = null, $userId = null)
	{
		if ($sessionId === null) {
			$sessionId = \diSession::id();
		}

		if ($userId === null && Auth::i()->authorized()) {
			$userId = Auth::i()->getUserId();
		}

		if ($userId && $sessionId)
		{
			/** @var Collection $userCol */
			$userCol = \diCollection::create(self::type);
			/** @var Model $userCart */
			$userCart = $userCol
				->filterByUserId($userId)
				->getFirstItem();

			/** @var Collection $sessionCol */
			$sessionCol = \diCollection::create(self::type);
			/** @var Model $sessionCart */
			$sessionCart = $sessionCol
				->filterBySessionId($sessionId)
				->getFirstItem();

			if ($userCart->exists()) {
				/** @var CartItem $item */
				foreach ($sessionCart->getItems() as $item)
				{
					$item
						->setCartId($userCart->getId())
						->save();
				}

				$sessionCart->hardDestroy();

				return $userCart;
			} else {
				$sessionCart
					->setUserId($userId)
					->save();

				return $sessionCart;
			}
		}

		return static::autoCreate();
	}

	public function getItem($targetType, $targetId, $additionalFields = [])
	{
        /** @var CartItem $item */
	    $item = $this->parentGetItem($targetType, $targetId, $additionalFields);

		if (!$item->exists()) {
			$item
				->setCartId($this->getId())
				->setTargetType($targetType)
				->setTargetId($targetId)
                ->updateTargetData();

			foreach ($additionalFields as $k => $v) {
				$item
					->set($k, $v);
			}

			$this->items[] = $item;

			if (static::pre_cache_needed) {
                $cachedCol = CollectionCache::get($item->getTargetType());

                if ($cachedCol) {
                    $cachedCol->addItem($item->getTargetModel());
                } else {
                    CollectionCache::addManual($item->getTargetType(), 'id', $item->getTargetId());
                }
            }
		}

		return $item;
	}

	public function killRelatedData()
    {
        parent::killRelatedData();

        $this->killItems();

        return $this;
    }
}