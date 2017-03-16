<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 11.10.15
 * Time: 10:22
 */

class diPhotosPage extends diAdminBasePage
{
	const FILTER_DEFAULT_ALBUM_ID = null;

	const TOKEN_MODE_SINGLE = 1;
	const TOKEN_MODE_MULTI = 2;

	protected $tokenMode = self::TOKEN_MODE_MULTI;

	protected $listMode = self::LIST_GRID;

	protected $options = [
		"filters" => [
			"defaultSorter" => [
				"sortBy" => "order_num",
				"dir" => "ASC",
			],
		],
	];

	protected $picStoreOptions = [
		[
			"type" => diAdminSubmit::IMAGE_TYPE_MAIN,
			"resize" => diImage::DI_THUMB_FIT,
		],
		[
			"type" => diAdminSubmit::IMAGE_TYPE_PREVIEW,
			"resize" => diImage::DI_THUMB_FIT,
		],
	];

	protected $filters = [
		"album_id" => [
			"field" => "album_id",
			"type" => "int",
			"title" => "Альбом",
			"showAllOption" => true,
			"default_value" => self::FILTER_DEFAULT_ALBUM_ID,
		],
	];

	protected function initTable()
	{
		$this->setTable("photos");
	}

	protected function setupFilters()
	{
		foreach ($this->filters as $field => $filter)
		{
			$this->getFilters()
				->addFilter($filter);
		}

		$this->getFilters()
			->buildQuery()
			->setSelectFromDbInput(
				"album_id",
				$this->getDb()->rs("albums", "ORDER BY order_num ASC"),
				!empty($this->filters["album_id"]["showAllOption"]) ? [0 => "Все альбомы"] : []
			);
	}

	public function renderList()
	{
		$orderAllowed = function(diModel $m, $action) {
			if (!$this->getFilters()->getData("album_id"))
			{
				return false;
			}

			return true;
		};

		$this->getGrid()
			->addButtons([
				"up" => [
					"allowed" => $orderAllowed,
				],
				"edit" => "",
				"del" => "",
				"visible" => "",
				"down" => [
					"allowed" => $orderAllowed,
				],
			]);
	}

	public function renderForm()
	{
		/** @var diPhotoModel $photo */
		$photo = $this->getForm()->getModel();

		if (!$this->getId())
		{
			$this->getForm()
				->setHiddenInput([
					"token",
					"date",
					"comments_last_date",
					"comments_count",
				]);
		}
		else
		{
			$tokens = '';

			switch ($this->tokenMode)
			{
				case self::TOKEN_MODE_SINGLE:
					$tokens = $photo->getToken();
					break;

				case self::TOKEN_MODE_MULTI:
					$tokens = join("\n", $photo->getAllTokens());
					break;
			}

			$this->getForm()
				->setData('token', $tokens);
		}

		$this->getForm()
			->setSelectFromDbInput("album_id", $this->getDb()->rs("albums", "ORDER BY title ASC"));
	}

	protected function getPicStoreOptions()
	{
		return $this->picStoreOptions;
	}

	public function submitForm()
	{
		$this->getSubmit()
			->makeSlug()
			->storeImage("pic", $this->getPicStoreOptions());
	}

	public function getFormTabs()
	{
		return [];
	}

	public function getFormFields()
	{
		return [
			"token" => [
				"type" => "text",
				"title" => "Токен для вставки",
				"default" => "",
				"flags" => ["static", "virtual"],
			],

			"album_id" => [
				"type" => "int",
				"title" => "Альбом",
				"default" => 0,
			],

			"title" => [
				"type" => "string",
				"title" => "Название",
				"default" => "",
			],

			"slug_source" => [
				"type" => "string",
				"title" => "Название для URL",
				"default" => "",
			],

			"content" => [
				"type" => "wysiwyg",
				"title" => "Описание",
				"default" => "",
			],

			"pic" => [
				"type" => "pic",
				"title" => "Фото",
				"default" => "",
			],

			"visible" => [
				"type" => "checkbox",
				"title" => "Отображать на сайте",
				"default" => 1,
			],

			"date" => [
				"type" => "datetime_str",
				"title" => "Дата добавления",
				"default" => "",
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

			"pic_tn_w" => [
				"type" => "int",
				"title" => "Pic w",
				"default" => 0,
			],

			"pic_tn_h" => [
				"type" => "int",
				"title" => "Pic h",
				"default" => 0,
			],
		];
	}

	public function getModuleCaption()
	{
		return "Фото";
	}
}