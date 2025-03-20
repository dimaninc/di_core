<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.06.2017
 * Time: 11:50
 */

namespace diCore\Tool\Cache;

use diCore\Base\CMS;
use diCore\Entity\ModuleCache\Collection;
use diCore\Entity\ModuleCache\Model;
use diCore\Traits\BasicCreate;

class Module
{
    use BasicCreate;

    const BOOTSTRAP_SETTINGS_EQ = ':';
    const BOOTSTRAP_SETTINGS_END = ';';

    protected function createCMS()
    {
        return CMS::fast_lite_create()->assignTwigBasics(true);
    }

    public function rebuild($id)
    {
        if ($id instanceof Model) {
            $cacheModel = $id;
        } else {
            $cacheModel = Model::createById($id);
        }

        if (!$cacheModel->exists()) {
            throw new \Exception("Module #{$id} doesn't exist");
        }

        $this->rebuildWorker($cacheModel);

        $cacheModel->setUpdatedAt(\diDateTime::sqlFormat())->save();

        return $this;
    }

    protected function rebuildWorker(Model $cacheModel)
    {
        /** @var \diModule $module */
        $module = CMS::getModuleClassName($cacheModel->getModuleId());
        $GLOBALS['Z'] = $this->createCMS();
        $module = $module::create($GLOBALS['Z'], [
            'noCache' => true,
            'bootstrapSettings' => $cacheModel->getBootstrapSettings(),
        ]);

        $this->storeContent($cacheModel, $module->getResultPage());

        return $this;
    }

    protected function storeContent(Model $cacheModel, $content)
    {
        $cacheModel->setContent($content);

        return $this;
    }

    public function rebuildAll()
    {
        $col = Collection::create()->filterByActive(1);
        /** @var Model $cache */
        foreach ($col as $cache) {
            $this->rebuild($cache);
        }
    }

    public function getCachedContents(\diModule $module, $options = [])
    {
        /** @var Model $cache */
        $cache = Collection::create()
            ->filterByModuleId($module->getName())
            ->filterByQueryString($options['query_string'])
            ->filterByBootstrapSettings(
                $this->prepareBootstrapSettings($options['bootstrap_settings'])
            )
            ->getFirstItem();

        return $this->getCacheFromModel($cache, $options);
    }

    protected function prepareBootstrapSettings($ar)
    {
        if (!is_array($ar)) {
            return $ar;
        }

        $a = [];

        foreach ($ar as $k => $v) {
            $a[] = $k . Module::BOOTSTRAP_SETTINGS_EQ . $v;
        }

        return join(Module::BOOTSTRAP_SETTINGS_END, $a);
    }

    protected function getCacheFromModel(Model $cache, $options = [])
    {
        return $cache->hasActive() ? $cache->getContent() : null;
    }
}
