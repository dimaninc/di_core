<?php
/**
 * Created by diAdminPagesManager
 * Date: 15.09.2016
 * Time: 11:43
 */

class diRedirectsPage extends diAdminBasePage
{
	protected $options = [
		"filters" => [
			"defaultSorter" => [
				"sortBy" => "id",
				"dir" => "DESC",
			],
		],
	];

	protected function initTable()
	{
		$this->setTable("redirects");
	}

	public function renderList()
	{
		$this->getList()->addColumns([
			"id" => "ID",
			"status" => [
				"headAttrs" => [
				],
			],
			"old_url" => [
				"headAttrs" => [
					"width" => "45%",
				],
			],
			"new_url" => [
				"headAttrs" => [
					"width" => "45%",
				],
			],
			"date" => [
				"title" => "Дата",
				"value" => function(diRedirectModel $m) {
					return diDateTime::format("d.m.Y H:i", $m->getDate());
				},
				"headAttrs" => [
					"width" => "10%",
				],
				"bodyAttrs" => [
					"class" => "dt",
				],
			],
			"#edit" => "",
			"#del" => "",
			"#active" => "",
		]);
	}

	public function renderForm()
	{
	}

	public function submitForm()
	{
	}

	public function getFormTabs()
	{
	    return [];
	}

	public function getFormFields()
	{
		return [
			"old_url" => [
				"type"		=> "string",
				"title"		=> "Старый URL",
				"default"	=> "",
			],

			"new_url" => [
				"type"		=> "string",
				"title"		=> "Новый URL",
				"default"	=> "",
			],

			"status" => [
				"type"		=> "int",
				"title"		=> "HTTP Статус",
				"default"	=> 301,
			],

			"strict_for_query" => [
				"type"		=> "checkbox",
				"title"		=> "Учитывать GET-параметры",
				"default"	=> 0,
			],

			"date" => [
				"type"		=> "datetime_str",
				"title"		=> "Дата добавления",
				"default"	=> "",
				"flags"		=> ["static", "untouchable"],
			],
		];
	}

	public function getLocalFields()
	{
		return [];
	}

	public function getModuleCaption()
	{
		return "Редиректы";
	}
}