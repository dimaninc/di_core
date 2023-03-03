<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 03.03.2023
 * Time: 10:25
 */

namespace diCore\Traits\Model;

use diCore\Data\Types;
use diCore\Tool\CollectionCache;

/**
 * Trait UserInside
 * @package diCore\Traits\Model
 *
 * @method integer	getUserId
 *
 * @method bool hasUserId
 *
 * @method $this setUserId($value)
 */
trait UserInside
{
    /** @var \diModel */
    protected $user;

    public function getUser()
    {
        if (!$this->user) {
            $this->user = CollectionCache::getModel(Types::user, $this->getUserId(), true);
        }

        return $this->user;
    }
}