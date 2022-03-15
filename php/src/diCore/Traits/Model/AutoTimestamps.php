<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 03/04/2019
 * Time: 18:38
 */

namespace diCore\Traits\Model;

/**
 * Trait AutoTimestamps
 * @package diCore\Traits\Model
 * Methods list for IDE
 *
 * @method string	getCreatedAt
 * @method string	getUpdatedAt
 *
 * @method bool hasCreatedAt
 * @method bool hasUpdatedAt
 *
 * @method $this setCreatedAt($value)
 * @method $this setUpdatedAt($value)
 */
trait AutoTimestamps
{
    protected function generateTimestamps()
    {
        if (defined('static::SKIP_TIMESTAMP_FIELDS') && static::SKIP_TIMESTAMP_FIELDS) {
            return $this;
        }

        $dt = \diDateTime::sqlFormat();

        if (!$this->hasCreatedAt()) {
            $this->setCreatedAt($dt);
        }

        $this->setUpdatedAt($dt);

        return $this;
    }
}
