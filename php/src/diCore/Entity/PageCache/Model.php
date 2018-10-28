<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.10.2018
 * Time: 13:10
 */

namespace diCore\Entity\PageCache;

use diCore\Database\FieldType;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getUri
 * @method string	getContent
 * @method string	getCreatedAt
 * @method string	getUpdatedAt
 * @method integer	getActive
 *
 * @method bool hasUri
 * @method bool hasContent
 * @method bool hasCreatedAt
 * @method bool hasUpdatedAt
 * @method bool hasActive
 *
 * @method Model setUri($value)
 * @method Model setContent($value)
 * @method Model setCreatedAt($value)
 * @method Model setUpdatedAt($value)
 * @method Model setActive($value)
 */
class Model extends \diCore\Database\Entity\Mongo\Model
{
    const type = \diTypes::page_cache;
    const connection_name = 'mongo_main';
    const table = 'page_cache';
    protected $table = 'page_cache';

    protected static $fieldTypes = [
        'uri' => FieldType::string,
        'content' => FieldType::string,
        'created_at' => FieldType::timestamp,
        'updated_at' => FieldType::timestamp,
        'active' => FieldType::bool,
    ];

    /**
     * @return static
     * @throws \Exception
     */
    public static function createForCurrentUri()
    {
        /** @var Collection $col */
        $col = \diCollection::create(static::type);
        $col
            ->filterByUri(\diRequest::requestUri())
            ->filterByActive(1);

        return $col->getFirstItem();
    }

    public function getHref()
    {
        return $this->getUri();
    }
}