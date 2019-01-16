<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 03.02.16
 * Time: 18:13
 */

use diCore\Entity\Content\Model;

class diSitemapModule extends diModule
{
	protected function defineTemplates()
	{
		$this->getTpl()
			->define("sitemap", [
				"page",
				"sitemap_row",
				"sitemap_nohref_row",
			]);

		return $this;
	}

	protected function isContentRowSkipped(Model $model)
	{
		return diSiteMapGenerator::isContentRowSkipped($model);
	}

	protected function printChildRows(\diModel $model)
	{
		return $this;
	}

	public function render()
	{
		$this->defineTemplates();

		/** @var Model $page */
		foreach ($this->getZ()->tables["content"] as $id => $page)
		{
			if ($this->isContentRowSkipped($page))
			{
				continue;
			}

			$this
				->printSubRow($page)
				->printChildRows($page);
		}
	}

	protected function printSubRow(\diModel $m, \diModel $page = null, $printLink = true, $levelNum = null)
	{
		$this->getTpl()->assign($m->getTemplateVars(), "M_");

		if ($page)
		{
			$this->getTpl()->assign([
				"LEVEL_NUM" => $levelNum !== null ? $levelNum : $page->getLevelNum() + 1,
			], "M_");
		}

		$tplName = $printLink ? 'sitemap_row' : 'sitemap_nohref_row';

		$this->getTpl()->process("SITEMAP_ROWS", "." . $tplName);

		return $this;
	}
}