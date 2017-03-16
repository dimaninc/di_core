<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 11.10.15
 * Time: 10:22
 */
class diAlbumsPage extends diAdminBasePage
{
	protected $options = [
		"filters" => [
			"defaultSorter" => [
				"sortBy" => "order_num",
				"dir" => "ASC",
			],
		],
	];

	protected function initTable()
	{
		$this->setTable("albums");
	}

	public function renderList()
	{
		$this->getList()->addColumns([
			"id" => "ID",
			"title" => [
				"headAttrs" => [
					"width" => "90%",
				],
			],
			"date" => [
				"title" => "Дата",
				"value" => function(diAlbumModel $m) {
					return diDateTime::format("d.m.Y H:i", $m->getDate());
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
			"#visible" => "",
			"#pic" => "",
			"#video" => "",
			"#up" => "",
			"#down" => "",
		]);
	}

	public function renderForm()
	{
		if (!$this->getId())
		{
			$this->getForm()
				->setHiddenInput([
					"token",
					"cover_photo_id",
					"date",
					"comments_last_date",
					"photos_count",
					"videos_count",
					"comments_count",
					"force_pic",
				]);
		}
		else
		{
			$this->getForm()
				->setData('token', $this->getForm()->getModel()->getToken());
		}
	}

	public function submitForm()
	{
		$this->getSubmit()
			->makeSlug()
			->storeImage("pic", [
				[
					"type" => diAdminSubmit::IMAGE_TYPE_MAIN,
					"resize" => diImage::DI_THUMB_FIT,
				],
				[
					"type" => diAdminSubmit::IMAGE_TYPE_PREVIEW,
					"resize" => diImage::DI_THUMB_FIT,
				],
			]);
	}

	protected function afterSubmitForm()
	{
		parent::afterSubmitForm();

		if ($this->getSubmit()->getData("force_pic"))
		{
			/** @var diAlbumModel $album */
			$album = diModel::create(diTypes::album);
			$album
				->initFrom($this->getSubmit()->getData())
				->setId($this->getSubmit()->getId());

			$album->generateThumbnail();
		}
	}

	public function getFormTabs()
	{
		return [
		];
	}

	public function getFormFields()
	{
		return [
			"token" => [
				"type" => "string",
				"title" => "Токен",
				"default" => "",
				"flags" => ["static", "virtual"],
			],

			"title" => [
				"type" => "string",
				"title" => "Название",
				"default" => "",
			],

			"content" => [
				"type" => "wysiwyg",
				"title" => "Описание",
				"default" => "",
			],

			"slug_source" => [
				"type" => "string",
				"title" => "Название для URL",
				"default" => "",
			],

			"cover_photo_id" => [
				"type" => "int",
				"title" => "Заглавное фото",
				"default" => 0,
			],

			"pic" => [
				"type" => "pic",
				"title" => "Обложка",
				"default" => "",
			],

			"force_pic" => [
				"type" => "checkbox",
				"title" => "Сгенерировать обложку заново",
				"default" => "",
				"flags" => ["virtual"],
			],

			"visible" => [
				"type" => "checkbox",
				"title" => "Отображать на сайте",
				"default" => 1,
			],

			"date" => [
				"type" => "datetime_str",
				"title" => "Дата создания",
				"default" => "",
				"flags" => ["static"],
			],

			"photos_count" => [
				"type" => "int",
				"title" => "Кол-во фото",
				"default" => 0,
				"flags" => ["static"],
			],

			"videos_count" => [
				"type" => "int",
				"title" => "Кол-во видео",
				"default" => 0,
				"flags" => ["static"],
			],

			"comments_enabled" => [
				"type" => "checkbox",
				"title" => "Разрешено комментировать",
				"default" => 1,
			],

			"comments_count" => [
				"type" => "int",
				"title" => "Кол-во комментариев",
				"default" => 0,
				"flags" => ["static"],
			],

			"comments_last_date" => [
				"type" => "datetime_str",
				"title" => "Дата последнего комментария",
				"default" => "",
				"flags" => ["static"],
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

			"order_num" => [
				"type" => "order_num",
				"title" => "ord",
				"default" => 0,
				"direction" => -1,
			],

			"pic_w" => [
				"type" => "int",
				"title" => "Pic w",
				"default" => 0,
			],

			"pic_h" => [
				"type" => "int",
				"title" => "Pic h",
				"default" => 0,
			],

			"pic_t" => [
				"type" => "int",
				"title" => "Pic h",
				"default" => 0,
			],
		];
	}

	public function getModuleCaption()
	{
		return "Альбомы";
	}
}