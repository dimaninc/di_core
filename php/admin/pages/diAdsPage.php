<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 29.05.2015
 * Time: 23:01
 */

class diAdsPage extends diAdminBasePage
{
	protected $options = array(
		"filters" => array(
			"defaultSorter" => array(
				"sortBy" => "order_num",
				"dir" => "ASC",
			),
			/*
			"sortByAr" => array(
				"order_num" => "По порядку",
			),
			*/
		),
	);

	protected function initTable()
	{
		$this->setTable("ads");
	}

	protected function setupFilters()
	{
		$this->getFilters()
			->addFilter(array(
				"field" => "block_id",
				"type" => "int",
				"title" => "Блок",
				"strict" => true,
			))
			->buildQuery()
			->setSelectFromDbInput("block_id", $this->getDb()->rs("ad_blocks", "ORDER BY order_num ASC"));
	}

	public function renderList()
	{
		$this->getList()->addColumns(array(
			"id" => "ID",
			"pic" => array(
				"title" => "Слайд",
				"value" => function(diAdModel $ad) {
					return $ad->hasPic()
						? "<img src=\"/" . get_pics_folder($ad->getTable()) . $ad->getPic() . "\" height=\"100\" />"
						: "&ndash;";
				},
				"headAttrs" => array(
					"width" => "30%",
				),
			),
			"title" => array(
				"headAttrs" => array(
					"width" => "40%",
				),
			),
			"href" => array(
				"headAttrs" => array(
					"width" => "30%",
				),
			),
			"#edit" => "",
			"#del" => "",
			"#visible" => "",
			"#up" => "",
			"#down" => "",
		));
	}

	public function renderForm()
	{
		$this->getForm()
			->setSelectFromArrayInput("transition", diAds::$adTransitionsAr)
			->setSelectFromArrayInput("transition_style", diAds::$adTransitionStylesAr)
			->setSelectFromDbInput("block_id", $this->getDb()->rs("ad_blocks", "ORDER BY order_num ASC"));

	}

	public function submitForm()
	{
		$this->getSubmit()
			->store_pics("pic");
	}

	protected function getQueryParamsForRedirectAfterSubmit()
	{
		$ar = parent::getQueryParamsForRedirectAfterSubmit();

		$ar["block_id"] = $this->getSubmit()->getData("block_id");

		return $ar;
	}

	public function getFormFields()
	{
		try
		{
			$blockHref = $this->getId() && $this->getForm() && $this->getForm()->getModel() && $this->getForm()->getModel()->has("block_id")
				? "/_admin/ad_blocks/form/" . $this->getForm()->getModel()->get("block_id") . "/"
				: null;
		}
		catch (Exception $e)
		{
			$blockHref = null;
		}

		$blockHrefStr = $blockHref
			? " берется из <a href=\"{$blockHref}\" data-link=\"ad_block\">настроек блока</a>"
			: " берется из настроек блока";

		return array(
			"block_id" => array(
				"type"      => "int",
				"title"     => "Блок",
				"default"   => 1,
			),

			"category_id" => array(
				"type"      => "int",
				"title"     => "Категория",
				"default"   => 0,
				"flags"     => array("hidden"),
			),

			"title" => array(
				"type"      => "string",
				"title"     => "Заголовок",
				"default"   => "",
			),

			"button_color" => array(
				"type"      => "string",
				"title"     => "Цвет кнопки",
				"default"   => "",
				"flags"     => array("hidden"),
			),

			"content" => array(
				"type"      => "text",
				"title"     => "Описание",
				"default"   => "",
			),

			"href" => array(
				"type"      => "string",
				"title"     => "Ссылка",
				"default"   => "",
			),

			"onclick" => array(
				"type"      => "string",
				"title"     => "Javascript OnClick",
				"default"   => "",
			),

			"transition" => array(
				"type"      => "int",
				"title"     => "Переход",
				"default"   => 0,
				"notes"		=> array("По умолчанию".$blockHrefStr),
			),

			"transition_style" => array(
				"type"      => "int",
				"title"     => "Стиль перехода (только для скроллинга)",
				"default"   => 0,
				"notes"		=> array("По умолчанию".$blockHrefStr),
			),

			"duration_of_show" => array(
				"type"      => "int",
				"title"     => "Время показа слайда, мс",
				"default"   => -1,
				"notes"		=> array("Если -1,".$blockHrefStr),
			),

			"duration_of_change" => array(
				"type"      => "int",
				"title"     => "Время смены слайда, мс",
				"default"   => -1,
				"notes"		=> array("Если -1,".$blockHrefStr),
			),

			"pic" => array(
				"type"      => "pic",
				"title"     => "Картинка",
				"default"   => "",
				//"notes"     => array("Размер: ".diConfiguration::get("ads_width")."x".diConfiguration::get("ads_height")." пикселей"),
			),

			"date" => array(
				"type"      => "datetime_str",
				"title"     => "Дата создания",
				"default"   => date("Y-m-d H:i:s"),
				"flags"     => array("static"),
			),
		);
	}

	public function getLocalFields()
	{
		return array(
			"order_num" => array(
				"type"      => "order_num",
				"title"     => "Order num",
				"default"   => 0,
				"direction" => 1,
			),

			"pic_w" => array(
				"type"      => "int",
				"title"     => "Pic w",
				"default"   => 0,
			),

			"pic_h" => array(
				"type"      => "int",
				"title"     => "Pic h",
				"default"   => 0,
			),
		);
	}

	public function getModuleCaption()
	{
		return "Слайды рекламного блока";
	}
}