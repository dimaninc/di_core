<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.10.2018
 * Time: 13:08
 */

namespace diCore\Tool\Cache;

use diCore\Data\Config;
use diCore\Data\Http\HttpCode;
use diCore\Data\Types;
use diCore\Entity\PageCache\Collection;
use diCore\Entity\PageCache\Model;
use diCore\Helper\StringHelper;
use diCore\Tool\Auth;
use diCore\Traits\BasicCreate;

class Page
{
    use BasicCreate;

    const FLUSH_PARAM = 'no_page_cache';

    /** @var  Model */
    protected $cache;

    /** @var bool Force use cache */
    protected $force = false;

    public function __construct()
    {
        $this->cleanServerVarsFromFlushParam();
    }

    protected function cleanServerVarsFromFlushParam()
    {
        if (!empty($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = StringHelper::removeQueryStringParameter(
                $_SERVER['REQUEST_URI'],
                [static::FLUSH_PARAM]
            );
        }

        if (!empty($_SERVER['QUERY_STRING'])) {
            $_SERVER['QUERY_STRING'] = ltrim(
                StringHelper::removeQueryStringParameter(
                    '?' . $_SERVER['QUERY_STRING'],
                    [static::FLUSH_PARAM]
                ),
                '?'
            );
        }

        return $this;
    }

    protected function canBeUsed()
    {
        return ($this->force || !Auth::i()->authorized()) &&
            !\diRequest::get(static::FLUSH_PARAM);
    }

    protected function getCache()
    {
        if (!$this->cache) {
            $this->cache = Model::createForCurrentUri();
        }

        return $this->cache;
    }

    public function work($forceUri = null)
    {
        if ($forceUri) {
            $this->force = true;
            $this->cache = Model::createForCurrentUri($forceUri);
        }

        if ($this->canBeUsed() && $this->getCache()->hasContent()) {
            if ($forceUri) {
                $code = Model::getHttpErrorCodeByUri($forceUri);

                if ($code) {
                    HttpCode::header($code);
                }
            }

            echo $this->getCacheFromModel($this->getCache());

            die();
        }

        return $this;
    }

    protected function getCacheFromModel(Model $cache, $options = [])
    {
        return $cache->exists() ? $cache->getContent() : null;
    }

    public function rebuildAll()
    {
        /** @var Collection $col */
        $col = \diCollection::create(Types::page_cache);
        $col->filterByActive(1);
        /** @var Model $cache */
        foreach ($col as $cache) {
            $this->rebuild($cache);
        }
    }

    public function rebuild($id)
    {
        if ($id instanceof Model) {
            $cacheModel = $id;
        } else {
            /** @var Model $cacheModel */
            $cacheModel = Model::create(Types::page_cache, $id);
        }

        if (!$cacheModel->exists()) {
            throw new \Exception("Page #{$id} doesn't exist");
        }

        $this->rebuildWorker($cacheModel);

        $cacheModel->setUpdatedAt(\diDateTime::sqlFormat())->save();

        return $this;
    }

    protected function rebuildWorker(Model $cacheModel)
    {
        $uri =
            Config::getMainProtocol() .
            Config::getMainDomain() .
            $cacheModel->getRebuildUri();
        $content = file_get_contents(
            $uri,
            false,
            stream_context_create([
                'http' => [
                    'ignore_errors' => true,
                ],
            ])
        );

        $this->storeContent($cacheModel, $content);

        return $this;
    }

    protected function storeContent(Model $cacheModel, $content)
    {
        if ($content) {
            $cacheModel->setContent($content);
        }

        return $this;
    }
}
