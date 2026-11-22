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
     * With $sessionId (or $id) and $userId given it never reaches Auth::i(), so
     * it may be called from Auth's own hooks; without them it signs in first.
     *
     * @param null $id
     * @param null $sessionId
     * @param null $userId
     * @return $this
     * @throws \Exception
     */
    public static function autoCreate(
        $id = null,
        $sessionId = null,
        $userId = null
    ) {
        if (static::only_one_instance && static::$instance) {
            return static::$instance;
        }

        // sign in BEFORE reading the session id: a cookie sign-in in Auth's
        // constructor rotates it and moves the guest cart to the new id, so a
        // lookup by the old one would miss it and create a second cart. Only
        // when the id is read: with explicit arguments this is callable from
        // Auth's own hooks, where Auth::i() would recurse
        if (!$id && !$sessionId) {
            Auth::i();
        }

        if ($id) {
            /** @var $this $model */
            $model = self::create(self::type, $id);
        } else {
            $q = ["session_id = '" . ($sessionId ?: \diSession::id()) . "'"];

            // an explicit $userId never asks Auth (callable from its hooks)
            $lookupUserId = $userId ?: Auth::i()->getUserId();

            if ($lookupUserId) {
                $q[] = "user_id = '" . $lookupUserId . "'";
            }

            $model = \diCollection::create(self::type)
                ->filterManual(join(' OR ', $q))
                ->getFirstItem();
        }

        if (!$model->exists()) {
            $model->setSessionId($sessionId ?: \diSession::id());
        }

        if (!$model->hasUserId() && ($userId || Auth::i()->getUserId())) {
            $model->setUserId($userId ?: Auth::i()->getUserId());
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

    /**
     * Hands the guest cart over to a rotated session id (sign-in), or it is
     * lost: autoCreate() and migrateToUser() look it up by the CURRENT id.
     * Moved, not looked up by the old id too: whoever still holds the old id
     * must not find the cart once it belongs to a signed-in user.
     */
    public static function moveSession(string $oldId, string $newId): void
    {
        /** @var static $model */
        $model = \diModel::create(self::type);
        $db = $model::getConnection()->getDb();
        $table = $model->getTable();

        // a project without the cart: no query against a missing table. Once
        // per sign-in where the client brought an id, so the list is affordable
        if (!in_array($table, $model::getConnection()->getTableNames(), true)) {
            return;
        }

        // one UPDATE, not a save() per cart; not diCollection::update(): its
        // aliased `UPDATE cart main_table` is invalid on SQLite
        $db->update(
            $table,
            ['session_id' => $newId],
            // guest carts only: one with a user is found by user_id anyway, and
            // a previous user's cart under the same id must not follow the new one
            'WHERE ' .
                $db->escapeFieldValue('session_id', $oldId) .
                " AND ({$db->escapeField('user_id')} IS NULL OR {$db->escapeField('user_id')} = 0)"
        );
    }

    /** With both arguments given it never reaches Auth::i(): see autoCreate(). */
    public static function migrateToUser($sessionId = null, $userId = null)
    {
        if ($sessionId === null) {
            // before the session id is read, and only then: see autoCreate()
            Auth::i();
            $sessionId = \diSession::id();
        }

        if ($userId === null && Auth::i()->authorized()) {
            $userId = Auth::i()->getUserId();
        }

        if ($userId && $sessionId) {
            /** @var Collection $userCol */
            $userCol = \diCollection::create(self::type);
            /** @var Model $userCart */
            $userCart = $userCol->filterByUserId($userId)->getFirstItem();

            /** @var Collection $sessionCol */
            $sessionCol = \diCollection::create(self::type);
            /** @var Model $sessionCart */
            $sessionCart = $sessionCol
                ->filterBySessionId($sessionId)
                ->getFirstItem();

            if ($userCart->exists()) {
                /** @var CartItem $item */
                foreach ($sessionCart->getItems() as $item) {
                    $item->setCartId($userCart->getId())->save();
                }

                $sessionCart->hardDestroy();

                return $userCart;
            } else {
                $sessionCart->setUserId($userId)->save();

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
                $item->set($k, $v);
            }

            $this->items[] = $item;

            if (static::pre_cache_needed) {
                $cachedCol = CollectionCache::get($item->getTargetType());

                if ($cachedCol) {
                    $cachedCol->addItem($item->getTargetModel());
                } else {
                    CollectionCache::addManual(
                        $item->getTargetType(),
                        'id',
                        $item->getTargetId()
                    );
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
