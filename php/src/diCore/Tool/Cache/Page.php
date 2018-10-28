<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.10.2018
 * Time: 13:08
 */

namespace diCore\Tool\Cache;

use diCore\Data\Config;
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

    public function __construct()
    {
        $this
            ->cleanServerVarsFromFlushParam();
    }

    protected function cleanServerVarsFromFlushParam()
    {
        $_SERVER['REQUEST_URI'] = StringHelper::removeQueryStringParameter($_SERVER['REQUEST_URI'], [
            static::FLUSH_PARAM,
        ]);

        if ($_SERVER['QUERY_STRING'])
        {
            $_SERVER['QUERY_STRING'] = ltrim(StringHelper::removeQueryStringParameter('?' . $_SERVER['QUERY_STRING'], [
                static::FLUSH_PARAM,
            ]), '?');
        }

        return $this;
    }

    protected function canBeUsed()
    {
        return !Auth::i()->authorized() && !\diRequest::get(static::FLUSH_PARAM);
    }

    protected function getCache()
    {
        if (!$this->cache)
        {
            $this->cache = Model::createForCurrentUri();
        }

        return $this->cache;
    }

    public function work()
    {
        if ($this->canBeUsed() && $this->getCache()->hasContent())
        {
            echo $this->getCacheFromModel($this->getCache());

            die();
        }

        return $this;
    }

    protected function getCacheFromModel(Model $cache, $options = [])
    {
        return $cache->exists()
            ? $cache->getContent()
            : null;
    }

    public function rebuildAll()
    {
        /** @var Collection $col */
        $col = \diCollection::create(Types::page_cache);
        $col
            ->filterByActive(1);
        /** @var Model $cache */
        foreach ($col as $cache)
        {
            $this->rebuild($cache);
        }
    }

    public function rebuild($id)
    {
        if ($id instanceof Model)
        {
            $cacheModel = $id;
        }
        else
        {
            /** @var Model $cacheModel */
            $cacheModel = Model::create(Types::page_cache, $id);
        }

        if (!$cacheModel->exists())
        {
            throw new \Exception("Page #{$id} doesn't exist");
        }

        $this->rebuildWorker($cacheModel);

        $cacheModel
            ->setUpdatedAt(\diDateTime::sqlFormat())
            ->save();

        return $this;
    }

    protected function rebuildWorker(Model $cacheModel)
    {
        $uri = Config::getMainProtocol() . Config::getMainDomain() . $cacheModel->getUri();
        $uri .= StringHelper::getUrlParamGlue($uri) . static::FLUSH_PARAM . '=1';
        $content = file_get_contents($uri);

        $this->storeContent($cacheModel, $content);

        return $this;
    }

    protected function storeContent(Model $cacheModel, $content)
    {
        if ($content)
        {
            $cacheModel->setContent($content);
        }

        return $this;
    }
}