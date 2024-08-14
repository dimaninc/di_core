<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 03.02.16
 * Time: 18:13
 */

namespace diCore\Module;

use diCore\Entity\Content\Model;

class Sitemap extends \diModule
{
    protected $rows = [];

    protected function isContentRowSkipped(Model $model)
    {
        return \diSiteMapGenerator::isContentRowSkipped($model);
    }

    public function getRows()
    {
        return $this->rows;
    }

    protected function populateRows()
    {
        /** @var Model $page */
        foreach ($this->getZ()->tables['content'] as $page) {
            if ($this->isContentRowSkipped($page)) {
                continue;
            }

            $this->printSubRow($page)->printChildRows($page);
        }

        return $this;
    }

    public function render()
    {
        $this->populateRows();

        $this->getTwig()->renderPage('sitemap/page', [
            'rows' => $this->getRows(),
        ]);
    }

    protected function printSubRow(
        \diModel $m,
        \diModel $page = null,
        $printLink = true,
        $levelNum = null
    ) {
        if ($page) {
            $m->set(
                'level_num',
                $levelNum !== null ? $levelNum : $page->getLevelNum() + 1
            );
        }

        $this->rows[] = $m->setRelated('_sitemapNoLink', !$printLink);

        return $this;
    }

    protected function printChildRows(\diModel $model)
    {
        return $this;
    }
}
