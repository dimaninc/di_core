<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 02.07.2015
 * Time: 17:39
 */

class diTagsPage extends diAdminBasePage
{
	protected $options = array(
		"filters" => array(
			"defaultSorter" => array(
				"sortBy" => "title",
				"dir" => "ASC",
			),
		),
	);

	protected function initTable()
	{
		$this->setTable("tags");
	}

	public function renderList()
	{
		$this->getList()->addColumns(array(
			"id" => "ID",
			"title" => array(
				"headAttrs" => array(
					"width" => "40%",
				),
			),
			"slug" => array(
				"title" => "Название для URL",
				"attrs" => array(),
				"headAttrs" => array(
					"width" => "40%",
				),
				"bodyAttrs" => array(
					"class" => "lite",
				),
			),
			"date" => array(
				"title" => "Добавлен",
				"value" => function(diTagModel $tag) {
					return date("d.m.Y H:i", strtotime($tag->getDate()));
				},
				"attrs" => array(),
				"headAttrs" => array(
					"width" => "20%",
				),
				"bodyAttrs" => array(
					"class" => "dt",
				),
			),
			"#edit" => "",
			"#del" => "",
			"#visible" => "",
		));
	}

	public function renderForm()
	{
	}

	public function submitForm()
	{
		$this->getSubmit()
			->makeSlug()
			->store_pics("pic", "dias_save_file");
	}

	public function getFormTabs()
	{
		return [
			"meta" => "SEO",
		];
	}

	public function getFormFields()
	{
		return [
			"title" => [
				"type" => "string",
				"title" => "Тег",
				"default" => "",
			],

			"slug_source" => [
				"type" => "string",
				"title" => "Название для URL",
				"default" => "",
			],

			"weight" => [
				"type" => "int",
				"title" => "Вес",
				"default" => 0,
				"flags" => ["static"],
			],

			"content" => [
				"type" => "wysiwyg",
				"title" => "Описание",
				"default" => "",
			],

			"pic" => [
				"type" => "pic",
				"title" => "Картинка",
				"default" => "",
				"flags" => ["hidden"],
			],

			"date" => [
				"type" => "datetime_str",
				"title" => "Дата публикации",
				"default" => date("Y-m-d H:i:s"),
			],

			"html_title" => [
				"type" => "string",
				"title" => "Meta-заголовок",
				"default" => "",
				"tab" => "meta",
			],

			"html_keywords" => [
				"type" => "string",
				"title" => "Meta-ключевые слова",
				"default" => "",
				"tab" => "meta",
			],

			"html_description" => [
				"type" => "string",
				"title" => "Meta-описание",
				"default" => "",
				"tab" => "meta",
			],
		];
	}

	public function getLocalFields()
	{
		return [
			"slug" => [
				"type" => "string",
				"title" => "Slug",
				"default" => "",
			],
		];
	}

	public function getModuleCaption()
	{
		return "Теги";
	}
}