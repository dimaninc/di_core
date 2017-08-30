<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 31.05.2015
 * Time: 21:01
 */

use diCore\Entity\Content\Model;

class diContentPage extends \diAdminBasePage
{
	const MAX_LEVEL_NUM = 2;

	protected $options = [
		"updateSearchIndexOnSubmit" => true,
		"showControlPanel" => true,
		"showHeader" => false,
		"filters" => [
			"defaultSorter" => [
				"sortBy" => "order_num",
				"dir" => "ASC",
			],
		],
	];

	protected function initTable()
	{
		$this->setTable("content");
	}

	protected function getButtonsArForList()
	{
		return [
			"#edit" => "",
			"#create" => [
				"maxLevelNum" => static::MAX_LEVEL_NUM,
			],
			"#del" => "",
			"#visible" => "",
			"#up" => "",
			"#down" => "",
		];
	}

	public function renderList()
	{
		$this->getList()
			->addColumns([
				"_checkbox" => "",
				"_expand" => "",
				"id" => "",
				"#href" => [],
				"title" => [
					"headAttrs" => [
						"width" => "80%",
					],
				],
				"type" => [
					"headAttrs" => [
						"width" => "20%",
					],
					"bodyAttrs" => [
						"class" => "regular",
					],
				],
			])
			->addColumns($this->getButtonsArForList());
	}

	protected function hideFieldsForType($contentTypes, $fields, $allLanguages = true)
	{
		if ($allLanguages)
		{
			$fields = \diCurrentCMS::extendFieldsWithAllLanguages($fields);
		}

		if (!is_array($contentTypes))
		{
			$contentTypes = [$contentTypes];
		}

		$this->getForm()
			->setInputAttribute($fields, [
				'data-hide-for-type' => $contentTypes,
			]);

		return $this;
	}

	public function renderForm()
	{
		$h = new \diHierarchyContentTable();

		$parents = $this->getId()
			? $h->getParents($this->getId())
			: $h->getParentsByParentId($this->getForm()->getModel()->get('parent'));

		$parentsAr = [];
		/** @var Model $parent */
		foreach ($parents as $parent)
		{
			$parentsAr[] = strip_tags($parent->getTitle());
		}

		if ($parentsAr)
		{
			$this->getForm()->setInput("parent", join(" / ", $parentsAr));
		}
		else
		{
			$this->getForm()->setHiddenInput("parent");
		}

		$typesAr = \diContentTypes::get($this->getLanguage());
		array_walk($typesAr, function (&$opts, $type) {
			$opts = "{$opts["title"]} ({$type}.php)";
		});

		$this->getForm()
			->setSelectFromArrayInput("type", $typesAr);
	}

	public function submitForm()
	{
		$this->getSubmit()
			->storeImage(["pic", "pic2", "ico"], [
				[
					"type" => \diCore\Admin\Submit::IMAGE_TYPE_MAIN,
					//"resize" => diImage::DI_THUMB_FIT,
				],
			])
			->makeSlug()
			->makeOrderAndLevelNum();
	}

	protected function afterSubmitForm()
	{
		parent::afterSubmitForm();

		$Z = new \diCurrentCMS();
		$Z->build_content_table_cache();
	}

	public function getFormFields()
	{
		return [
			"parent" => [
				"type" => "int",
				"title" => "Родитель",
				"default" => -1,
				"flags" => ["static"],
			],

			"type" => [
				"type" => "string",
				"title" => "Тип",
				"default" => "user",
				"values" => array_keys(\diContentTypes::get($this->getLanguage())),
			],

			"menu_title" => [
				"type" => "string",
				"title" => "Название для URL",
				"default" => "",
			],

			"title" => [
				"type" => "string",
				"title" => "Название",
				"default" => "",
				"tab" => "content",
			],

			"caption" => [
				"type" => "string",
				"title" => "Заголовок",
				"default" => "",
				"flags" => ["hidden"],
			],

			"short_content" => [
				"type" => "wysiwyg",
				"title" => "Краткое наполнение",
				"default" => "",
				"flags" => "hidden",
				"tab" => "content",
			],

			"content" => [
				"type" => "wysiwyg",
				"title" => "Наполнение",
				"default" => "",
				"tab" => "content",
			],

			"links_content" => [
				"type" => "text",
				"title" => "Блок со ссылками",
				"default" => "",
				"tab" => "content",
				"flags" => ["hidden"],
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

			"comments_enabled" => [
				"type" => "checkbox",
				"title" => "Возможность комментировать страницу",
				"default" => 0,
				"flags" => ["hidden"],
			],

			/*
			"show_links" => [
				"type" => "string",
				"title" => "Блок со ссылками",
				"default" => "nothing",
				"flags" => ["hidden"],
			],
			*/

			"background_color" => [
				"type" => "string",
				"title" => "Цвет фона",
				"default" => "",
				"tab" => "content",
				"flags" => ["hidden"],
			],

			"color" => [
				"type" => "string",
				"title" => "Цвет ссылки, #RRGGBB",
				"default" => "",
				"tab" => "content",
				"flags" => ["hidden"],
			],

			"menu_class" => [
				"type" => "string",
				"title" => "CSS-класс пункта меню",
				"default" => "",
				"flags" => ["hidden"],
			],

			"class" => [
				"type" => "string",
				"title" => "CSS-класс заголовка",
				"default" => "",
				"flags" => ["hidden"],
			],

			"pic" => [
				"type" => "pic",
				"title" => "Картинка",
				"default" => "",
				"tab" => "pics",
				"flags" => ["hidden"],
			],

			"pic2" => [
				"type" => "pic",
				"title" => "Вторая картинка",
				"default" => "",
				"tab" => "pics",
				"flags" => ["hidden"],
			],

			"ico" => [
				"type" => "pic",
				"title" => "Иконка",
				"default" => "",
				"tab" => "pics",
				"flags" => ["hidden"],
			],

			"ad_block_id" => [
				"type" => "int",
				"title" => "Рекламный блок",
				"default" => "",
				"flags" => ["hidden"],
			],
		];
	}

	public function getLocalFields()
	{
		return [
			"clean_title" => [
				"type" => "string",
				"title" => "Clean title",
				"default" => "",
			],

			"level_num" => [
				"type" => "int",
				"title" => "Level num",
				"default" => 0,
			],

			"to_show_content" => [
				"type" => "int",
				"title" => "Show",
				"default" => 0,
			],

			"order_num" => [
				"type" => "int",
				"title" => "Order num",
				"default" => 0,
			],

			"pic_w" => [
				"type" => "int",
				"title" => "Изображение w",
				"default" => 0,
			],

			"pic_h" => [
				"type" => "int",
				"title" => "Изображение h",
				"default" => 0,
			],

			"pic_t" => [
				"type" => "int",
				"title" => "Изображение t",
				"default" => 0,
			],

			"pic2_w" => [
				"type" => "int",
				"title" => "Изображение w",
				"default" => 0,
			],

			"pic2_h" => [
				"type" => "int",
				"title" => "Изображение h",
				"default" => 0,
			],

			"pic2_t" => [
				"type" => "int",
				"title" => "Изображение t",
				"default" => 0,
			],

			"ico_w" => [
				"type" => "int",
				"title" => "Изображение w",
				"default" => 0,
			],

			"ico_h" => [
				"type" => "int",
				"title" => "Изображение h",
				"default" => 0,
			],

			"ico_t" => [
				"type" => "int",
				"title" => "Изображение t",
				"default" => 0,
			],
		];
	}

	public function getFormTabs()
	{
		return [
			"meta" => "SEO",
		];
	}

	public function getModuleCaption()
	{
		return "Страницы";
	}
}