<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 30.06.2015
 * Time: 14:12
 */
class diFeedbackPage extends diAdminBasePage
{
	protected $options = [
		"staticMode" => true,
		"filters" => [
			"defaultSorter" => [
				"sortBy" => "date",
				"dir" => "DESC",
			],
		],
		"showControlPanel" => true,
	];

	protected function initTable()
	{
		$this->setTable("feedback");
	}

	public function renderList()
	{
		$this->getList()->addColumns([
			"_checkbox" => "",
			"id" => "ID",
			"email" => [
				"headAttrs" => [
					"width" => "15%",
				],
			],
			"phone" => [
				"headAttrs" => [
					"width" => "15%",
				],
			],
			"name" => [
				"headAttrs" => [
					"width" => "20%",
				],
			],
			"content" => [
				"headAttrs" => [
					"width" => "40%",
				],
				"bodyAttrs" => [
					"class" => "lite",
				],
				"value" => function(diModel $model) {
					return str_out(str_cut_end($model->get("content"), 200));
				},
			],
			"date" => [
				"title" => "Дата",
				"value" => function(diModel $model) {
					return date("d.m.Y H:i", strtotime($model->get("date")));
				},
				"attrs" => [],
				"headAttrs" => [
					"width" => "10%",
				],
				"bodyAttrs" => [
					"class" => "dt",
				],
			],
			"#edit" => "",
			"#del" => "",
		]);
	}

	public function renderForm()
	{
		$this->getForm()
			->processData("content", function($v) {
				return diDB::_out($v);
			})
			->processData("ip", "bin2ip");
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
			"user_id" => [
				"type" => "int",
				"title" => "Пользователь",
				"default" => "",
			],

			"name" => [
				"type" => "string",
				"title" => "Имя",
				"default" => "",
			],

			"email" => [
				"type" => "string",
				"title" => "E-mail",
				"default" => "",
			],

			"phone" => [
				"type" => "string",
				"title" => "Телефон",
				"default" => "",
			],

			"content" => [
				"type" => "text",
				"title" => "Текст сообщения",
				"default" => "",
			],

			"ip" => [
				"type" => "ip",
				"title" => "IP отправителя",
				"default" => "",
			],

			"date" => [
				"type" => "datetime_str",
				"title" => "Дата/время",
				"default" => "",
			],
		];
	}

	public function getLocalFields()
	{
		return [];
	}

	public function getModuleCaption()
	{
		return "Обратная связь";
	}

	public function addButtonNeededInCaption()
	{
		return false;
	}
}