<?php
class diAdBlocksPage extends diAdminBasePage
{
	protected $options = array(
		"filters" => array(
			"defaultSorter" => array(
				"sortBy" => "order_num",
				"dir" => "ASC",
			),
		),
	);

	protected function initTable()
	{
		$this->setTable("ad_blocks");
	}

	public function renderList()
	{
		$this->getTpl()->define("`ad_blocks/list", array(
			"page",
		));

		$this->getList()->addColumns(array(
			"id" => "ID",
			"token" => array(
				"title" => "Токен",
				"value" => "[AD-BLOCK-%id%]",
				"attrs" => array(
					"width" => "10%",
				),
			),
			"title" => array(
				"title" => "Название",
				"attrs" => array(
					"width" => "90%",
				),
			),
			"#manage" => array(
				"href" => array(
					"module" => "ads",
					"params" => "block_id=%id%",
				),
				"icon" => "img",
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
		$ad_transitions_ar2 = diAds::$adTransitionsAr;
		unset($ad_transitions_ar2[0]);

		$ad_transition_styles_ar2 = diAds::$adTransitionStylesAr;
		unset($ad_transition_styles_ar2[0]);

		$this->getForm()
			->setSelectFromArrayInput("transition", $ad_transitions_ar2)
			->setSelectFromArrayInput("transition_style", diAds::$adTransitionStylesAr)
			->setSelectFromArrayInput("slides_order", diAds::$adSlidesOrdersAr);

		if ($this->getId())
		{
			$this->getForm()->setInput("token", "[AD-BLOCK-".$this->getId()."]");
		}
		else
		{
			$this->getForm()->setHiddenInput("token");
		}
	}

	public function submitForm()
	{
	}

	public function getFormFields()
	{
		return array(
			"title" => array(
				"type"      => "string",
				"title"     => "Название блока",
				"default"   => "",
			),

			"default_slide_title" => array(
				"type"      => "string",
				"title"     => "Заголовок слайдов по умолчанию",
				"default"   => "",
			),

			"default_slide_content" => array(
				"type"      => "text",
				"title"     => "Описание слайдов по умолчанию",
				"default"   => "",
			),

			"token" => array(
				"type"      => "string",
				"title"     => "Токен",
				"default"   => "",
				"flags"     => array("virtual"),
			),

			"transition" => array(
				"type"      => "int",
				"title"     => "Переход",
				"default"   => 0,
			),

			"transition_style" => array(
				"type"      => "int",
				"title"     => "Стиль перехода (только для скроллинга)",
				"default"   => 0,
			),

			"duration_of_show" => array(
				"type"      => "int",
				"title"     => "Время показа слайда, мс",
				"default"   => 10000,
			),

			"duration_of_change" => array(
				"type"      => "int",
				"title"     => "Время смены слайда, мс",
				"default"   => 800,
			),

			"slides_order" => array(
				"type"      => "int",
				"title"     => "Порядок смены слайдов",
				"default"   => 0,
			),

			"ignore_hover_hold" => array(
				"type"      => "checkbox",
				"title"     => "Игнорировать наведение мыши на слайды (продолжать перелистывать)",
				"default"   => 0,
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
		);
	}

	public function getModuleCaption()
	{
		return "Рекламные блоки";
	}
}