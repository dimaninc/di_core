<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 14:43
 */

namespace diCore\Entity\Cart;

use diCore\Data\Types;
use diCore\Entity\CartItem\Collection as CartItems;
use diCore\Entity\CartItem\Model as CartItem;
use diCore\Tool\Auth;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getSessionId
 * @method integer	getUserId
 * @method string	getCreatedAt
 *
 * @method bool hasSessionId
 * @method bool hasUserId
 * @method bool hasCreatedAt
 *
 * @method $this setSessionId($value)
 * @method $this setUserId($value)
 * @method $this setCreatedAt($value)
 */
class Model extends \diModel
{
	const type = \diTypes::cart;
	protected $table = 'cart';

	/** @var array|null */
	protected $items = null;

	public static function autoCreate($id = null, $sessionId = null, $userId = null)
	{
		if ($id)
		{
			/** @var $this $model */
			$model = self::create(self::type, $id);
		}
		else
		{
			$q = [
				"session_id = '" . ($sessionId ?: \diSession::id()) . "'",
			];

			if (Auth::i()->getUserId())
			{
				$q[] = "user_id = '" . ($userId ?: Auth::i()->getUserId()) . "'";
			}

			$model = \diCollection::create(self::type)->filterManual(join(' OR ', $q))->getFirstItem();
		}

		if (!$model->exists())
		{
			$model
				->setSessionId($sessionId ?: \diSession::id())
				->setUserId($userId ?: Auth::i()->getUserId());
		}

		return $model;
	}

	public function getItems()
	{
		if ($this->items === null && $this->exists())
		{
			/** @var CartItems $items */
			$items = \diCollection::create(Types::cart_item);
			$items
				->filterByCartId($this->getId())
				->orderById();

			foreach ($items as $item)
			{
				$this->items[] = $item;
			}
		}

		return $this->items ?: [];
	}

	public static function migrateToUser($sessionId = null, $userId = null)
	{
		if ($sessionId === null)
		{
			$sessionId = \diSession::id();
		}

		if ($userId === null && Auth::i()->authorized())
		{
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

			if ($userCart->exists())
			{
				/** @var CartItem $item */
				foreach ($sessionCart->getItems() as $item)
				{
					$item
						->setCartId($userCart->getId())
						->save();
				}

				$sessionCart->hardDestroy();

				return $userCart;
			}
			else
			{
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
		/** @var CartItem $i */
		foreach ($this->getItems() as $i)
		{
			if ($i->getTargetType() == $targetType && $i->getTargetId() == $targetId)
			{
				$ok = true;

				foreach ($additionalFields as $k => $v)
				{
					if ($i->get($k) != $v)
					{
						$ok = false;

						break;
					}
				}

				if ($ok)
				{
					$item = $i;

					break;
				}
			}
		}

		if (!isset($item))
		{
			/** @var CartItem $item */
			$item = \diModel::create(Types::cart_item);

			$item
				->setCartId($this->getId())
				->setTargetType($targetType)
				->setTargetId($targetId);

			foreach ($additionalFields as $k => $v)
			{
				$item
					->set($k, $v);
			}
		}

		return $item;
	}

	public function killRelatedData()
    {
        parent::killRelatedData();

        /** @var CartItem $item */
        foreach ($this->getItems() as $item) {
            $item->hardDestroy();
        }

        return $this;
    }
}