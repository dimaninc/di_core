<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.10.2018
 * Time: 13:10
 */

namespace diCore\Entity\PageCache;

use diCore\Data\Http\HttpCode;
use diCore\Database\FieldType;
use diCore\Helper\StringHelper;
use diCore\Tool\Cache\Page;

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

    const ERROR_401_URI = '#error_401';
    const ERROR_403_URI = '#error_403';
    const ERROR_404_URI = '#error_404';
    const ERROR_500_URI = '#error_500';

    public static $errorUris = [
        HttpCode::UNAUTHORIZED => self::ERROR_401_URI,
        HttpCode::FORBIDDEN => self::ERROR_403_URI,
        HttpCode::NOT_FOUND => self::ERROR_404_URI,
        HttpCode::INTERNAL_SERVER_ERROR => self::ERROR_500_URI,
    ];

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
    public static function createForCurrentUri($forceUri = null)
    {
        /** @var Collection $col */
        $col = \diCollection::create(static::type);
        $col
            ->filterByUri($forceUri ?: \diRequest::requestUri())
            ->filterByActive(1);

        return $col->getFirstItem();
    }

    public static function getUriByHttpErrorCode($code)
    {
        return isset(static::$errorUris[$code])
            ? static::$errorUris[$code]
            : '#error_' . $code;
    }

    public static function getHttpErrorCodeByUri($uri)
    {
        return array_search($uri, static::$errorUris);
    }

    public function getHref()
    {
        return $this->getUri();
    }

    public function getRebuildUri()
    {
        $uri = in_array($this->getUri(), static::$errorUris)
            ? '/' . substr($this->getUri(), 1) // . '_' . StringHelper::random(32)
            : $this->getUri();

        $uri .= StringHelper::getUrlParamGlue($uri) . Page::FLUSH_PARAM . '=1';

        return $uri;
    }
}