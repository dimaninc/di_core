<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 20.09.15
 * Time: 23:52
 */

namespace diCore\Admin\Page;

use diCore\Entity\Localization\Model;

class Localization extends \diCore\Admin\BasePage
{
	protected $options = [
		"filters" => [
			"defaultSorter" => [
				"sortBy" => "id",
				"dir" => "DESC",
			],
			"buttonOptions" => [
				"suffix" => "<button type='button' name='export' class='blue'>Экспорт</button>",
			],
			"sortByAr" => [
				"name" => "По токену",
				"value" => "По рус.значению",
				"en_value" => "По англ.значению",
				"id" => "По ID",
			],
		],
	];

	protected function initTable()
	{
		$this->setTable("localization");
	}

	protected function setupFilters()
	{
		$this->getFilters()
			->addFilter([
				"field" => "id",
				"type" => "int",
				"title" => "ID",
			])
			->addFilter([
				"field" => "name",
				"type" => "string",
				"title" => "Токен",
				"where_tpl" => "diaf_substr",
			])
			->addFilter([
				"field" => "value",
				"type" => "string",
				"title" => "Рус.значение",
				"where_tpl" => "diaf_substr",
			])
			->addFilter([
				"field" => "en_value",
				"type" => "string",
				"title" => "Eng.значение",
				"where_tpl" => "diaf_substr",
			])
			->buildQuery();
	}

	public static function valueOut(Model $m, $field)
	{
		$orig = $s = $m->get($field);

		$s = utf8_wordwrap($s, 21, " ", true);
		$s = strip_tags($s);
		$s = str_cut_end($s, 100);

		return $s . "<div class=\"display-none\" data-purpose=\"orig\">$orig</div>";
	}

	public function renderList()
	{
		$this->setAfterTableTemplate('admin/localization/after_list');

		$valueOut = function(Model $m, $field) {
			return self::valueOut($m, $field);
		};

		$this->getList()->addColumns([
			"id" => "ID",
			"_checkbox" => "",
			"name" => [
				"headAttrs" => [
					"width" => "20%",
				],
				"bodyAttrs" => [
					"class" => "regular",
				],
				"noHref" => true,
			],
			"value" => [
				"title" => "Rus",
				"headAttrs" => [
					"width" => "40%",
				],
				"value" => $valueOut,
			],
			"en_value" => [
				"title" => "Eng",
				"headAttrs" => [
					"width" => "40%",
				],
				"value" => $valueOut,
			],
			"#edit" => "",
			"#del" => [
				'active' => function(Model $m, $field) {
					return $this->getAdmin()->isAdminSuper();
				},
			],
		]);
	}

	public function renderForm()
	{
		$this->setAfterFormTemplate('admin/localization/after_form');

		if (!$this->getAdmin()->isAdminSuper() && $this->getId())
		{
			$this->getForm()->setStaticInput("name");
		}

		$this->getForm()->setSubmitButtonsOptions([
			"show_additional" => "clone",
		]);
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
			"name" => [
				"type" => "string",
				"title" => "Токен",
				"default" => "",
			],

			"value" => [
				"type" => "text",
				"title" => "Значение (RUS)",
				"default" => "",
				'options' => [
					'rows' => 3,
				],
			],

			"en_value" => [
				"type" => "text",
				"title" => "Значение (ENG)",
				"default" => "",
				'options' => [
					'rows' => 3,
				],
			],
		];
	}

	public function getLocalFields()
	{
		return [];
	}

	public function getModuleCaption()
	{
		return "Локализация";
	}

	public function useEditLog()
	{
		return true;
	}
}