<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 30.05.2015
 * Time: 0:13
 */

class diFontsPage extends diAdminBasePage
{
	protected $options = [
		"filters" => [
			"defaultSorter" => [
				"sortBy" => "token",
				"dir" => "ASC",
			],
			"sortByAr" => [
				"token" => "По токену",
				"id" => "По дате добавления",
			],
		],
	];

	protected function initTable()
	{
		$this->setTable("fonts");
	}

	protected function setupFilters()
	{
		$this->getFilters()
			->addFilter([
				"field" => "token",
				"type" => "str",
				"title" => "Токен",
				"where_tpl" => "diaf_substr",
			])
			->buildQuery();
	}

	public function renderList()
	{
		$this->getList()->addColumns([
			"id" => "ID",
			"token" => [
				"title" => "Название/Токен",
				"headAttrs" => [
					"width" => "35%",
				],
			],
			"weight" => [
				"headAttrs" => [
					"width" => "15%",
				],
			],
			"style" => [
				"headAttrs" => [
					"width" => "15%",
				],
			],
			"title" => [
				"title" => "Пояснение",
				"headAttrs" => [
					"width" => "35%",
				],
			],
			"#edit" => "",
			"#del" => "",
		]);
	}

	public function renderForm()
	{
	}

	public function submitForm()
	{
		$this->getSubmit()
			->store_pics(['file_eot', 'file_otf', 'file_ttf', 'file_woff', 'file_svg']);
	}

	protected function afterSubmitForm()
	{
		parent::afterSubmitForm();

		diFonts::storeToCss();
	}

	public function getFormFields()
	{
		return [
			"token" => [
				"type" => "string",
				"title" => "Название шрифта, токен",
				"default" => "",
				"notes" => ["используется как уникальный идентификатор при задании шрифта", "<a href=\"https://www.web-font-generator.com\">Хороший конвертер шрифтов</a>"],
			],

			"title" => [
				"type" => "string",
				"title" => "Пояснение (где используется)",
				"default" => "",
			],

			"weight" => [
				"type" => "string",
				"title" => "font-weight",
				"default" => "normal",
			],

			"style" => [
				"type" => "string",
				"title" => "font-style",
				"default" => "normal",
			],

			"content" => [
				"type" => "text",
				"title" => "Описание",
				"default" => "",
				"flags" => ["hidden"],
			],

			"file_eot" => [
				"type" => "file",
				"title" => "Шрифт в формате EOT",
				"attrs" => ["accept" => ".eot"],
				"default" => "",
			],

			"file_otf" => [
				"type" => "file",
				"title" => "Шрифт в формате OTF",
				"attrs" => ["accept" => ".otf"],
				"default" => "",
			],

			"file_ttf" => [
				"type" => "file",
				"title" => "Шрифт в формате TTF",
				"attrs" => ["accept" => ".ttf"],
				"default" => "",
			],

			"file_woff" => [
				"type" => "file",
				"title" => "Шрифт в формате WOFF",
				"attrs" => ["accept" => ".woff"],
				"default" => "",
			],

			"file_svg" => [
				"type" => "file",
				"title" => "Шрифт в формате SVG",
				"attrs" => ["accept" => ".svg"],
				"default" => "",
			],

			"token_svg" => [
				"type" => "string",
				"title" => "Токен шрифта в SVG",
				"default" => "",
				"notes" => ["Если пусто, берется из общего Токена шрифта"],
			],

			"date" => [
				"type" => "datetime_str",
				"title" => "Дата добавления",
				"default" => "",
				"flags" => ["static"],
			],
		];
	}

	public function getLocalFields()
	{
		return [
			"order_num" => [
				"type" => "order_num",
				"title" => "",
				"default" => 0,
				"direction" => 1,
			],
		];
	}

	public function getModuleCaption()
	{
		return "Шрифты";
	}
}