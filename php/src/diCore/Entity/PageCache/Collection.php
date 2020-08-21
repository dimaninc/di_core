<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.10.2018
 * Time: 13:11
 */

namespace diCore\Entity\PageCache;

use diCore\Traits\Collection\AutoTimestamps;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterByUri($value, $operator = null)
 * @method $this filterByContent($value, $operator = null)
 * @method $this filterByActive($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByUri($direction = null)
 * @method $this orderByContent($direction = null)
 * @method $this orderByActive($direction = null)
 *
 * @method $this selectId()
 * @method $this selectUri()
 * @method $this selectContent()
 * @method $this selectActive()
 */
class Collection extends \diCollection
{
    use AutoTimestamps;

    const type = \diTypes::page_cache;
    const connection_name = 'mongo_main';
    protected $table = 'page_cache';
}