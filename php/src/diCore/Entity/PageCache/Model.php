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
use diCore\Helper\FileSystemHelper;
use diCore\Helper\StringHelper;
use diCore\Tool\Cache\Page;
use diCore\Traits\Model\AutoTimestamps;
use Romantic\Data\Config;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getUri
 * @method string	getContent
 * @method integer	getActive
 *
 * @method bool hasUri
 * @method bool hasContent
 * @method bool hasActive
 *
 * @method $this setUri($value)
 * @method $this setContent($value)
 * @method $this setActive($value)
 */
class Model extends \diModel
{
    use AutoTimestamps;

    const type = \diTypes::page_cache;
    const connection_name = 'mongo_main';
    const table = 'page_cache';
    protected $table = 'page_cache';

    const SAVE_TO_FILESYSTEM = false;
    const CACHE_FOLDER = '_cfg/cache/page/';
    const CACHE_EXT = '.html';

    const ERROR_401_URI = '#error_401';
    const ERROR_403_URI = '#error_403';
    const ERROR_404_URI = '#error_404';
    const ERROR_410_URI = '#error_410';
    const ERROR_500_URI = '#error_500';

    public static $errorUris = [
        HttpCode::UNAUTHORIZED => self::ERROR_401_URI,
        HttpCode::FORBIDDEN => self::ERROR_403_URI,
        HttpCode::NOT_FOUND => self::ERROR_404_URI,
        HttpCode::GONE => self::ERROR_410_URI,
        HttpCode::INTERNAL_SERVER_ERROR => self::ERROR_500_URI,
    ];

    protected static $fieldTypes = [
        'uri' => FieldType::string,
        'content' => FieldType::string,
        'created_at' => FieldType::datetime,
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
        $col->filterByUri($forceUri ?? \diRequest::requestUri())->filterByActive(
            true
        );

        return $col->getFirstItem();
    }

    public static function getUriByHttpErrorCode($code)
    {
        return static::$errorUris[$code] ?? '#error_' . $code;
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

    public function prepareForSave()
    {
        $this->generateTimestamps();

        return parent::prepareForSave();
    }

    public function afterSave()
    {
        if (static::SAVE_TO_FILESYSTEM) {
            $this->saveToFile();
        }

        return parent::afterSave();
    }

    protected function beforeKill()
    {
        $fn = $this->getCacheFolder() . $this->getCacheFilename();

        if (is_file($fn)) {
            unlink($fn);
        }

        return parent::beforeKill();
    }

    public function getCacheFolder()
    {
        return Config::getCacheFolder() . static::CACHE_FOLDER;
    }

    public function getCacheFilename()
    {
        $fn = trim($this->getUri(), '/');
        $fn = str_replace(['#'], '__', $fn);

        if (!$fn) {
            $fn = '__home';
        }

        return $fn . static::CACHE_EXT;
    }

    public function saveToFile()
    {
        $fn = $this->getCacheFolder() . $this->getCacheFilename();
        $dir = dirname($fn);

        FileSystemHelper::createTree('', $dir);

        file_put_contents($fn, $this->getContent());
        @chmod($fn, 0775);

        return $this;
    }
}
