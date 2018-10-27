<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.10.2018
 * Time: 13:11
 */

namespace diCore\Entity\PageCache;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByUri($value, $operator = null)
 * @method Collection filterByContent($value, $operator = null)
 * @method Collection filterByCreatedAt($value, $operator = null)
 * @method Collection filterByUpdatedAt($value, $operator = null)
 * @method Collection filterByActive($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByUri($direction = null)
 * @method Collection orderByContent($direction = null)
 * @method Collection orderByCreatedAt($direction = null)
 * @method Collection orderByUpdatedAt($direction = null)
 * @method Collection orderByActive($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectUri()
 * @method Collection selectContent()
 * @method Collection selectCreatedAt()
 * @method Collection selectUpdatedAt()
 * @method Collection selectActive()
 */
class Collection extends \diCore\Database\Entity\Mongo\Collection
{
    const type = \diTypes::page_cache;
    const connection_name = 'mongo_main';
    protected $table = 'page_cache';
}