<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 03.02.16
 * Time: 18:13
 */

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

	protected function isContentRowSkipped(diContentModel $model)
	{
		return diSiteMapGenerator::isContentRowSkipped($model);
	}

	protected function printChildRows(diContentModel $model)
	{
		return $this;
	}

	public function render()
	{
		$this->defineTemplates();

		/** @var diContentModel $page */
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

	protected function printSubRow(diModel $m, diContentModel $page = null, $printLink = true)
	{
		$this->getTpl()->assign($m->getTemplateVars(), "M_");

		if ($page)
		{
			$this->getTpl()->assign([
				"LEVEL_NUM" => $page->getLevelNum() + 1,
			], "M_");
		}

		$tplName = $printLink ? 'sitemap_row' : 'sitemap_nohref_row';

		$this->getTpl()->process("SITEMAP_ROWS", "." . $tplName);

		return $this;
	}
}