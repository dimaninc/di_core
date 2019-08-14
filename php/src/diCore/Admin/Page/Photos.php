<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.10.2017
 * Time: 9:15
 */

namespace diCore\Admin\Page;

use diCore\Admin\Submit;
use diCore\Data\Types;
use diCore\Entity\Album\Collection as Albums;
use diCore\Entity\Photo\Model;

class Photos extends \diCore\Admin\BasePage
{
	const FILTER_DEFAULT_ALBUM_ID = null;

	const TOKEN_MODE_SINGLE = 1;
	const TOKEN_MODE_MULTI = 2;

	protected $tokenMode = self::TOKEN_MODE_MULTI;

	protected $listMode = self::LIST_GRID;

	protected $options = [
		'filters' => [
			'defaultSorter' => [
				'sortBy' => 'order_num',
				'dir' => 'ASC',
			],
		],
	];

	protected $picStoreOptions = [
		[
			'type' => Submit::IMAGE_TYPE_MAIN,
			'resize' => \diImage::DI_THUMB_FIT,
		],
		[
			'type' => Submit::IMAGE_TYPE_PREVIEW,
			'resize' => \diImage::DI_THUMB_FIT,
		],
	];

	protected $filters = [
		'album_id' => [
			'field' => 'album_id',
			'type' => 'int',
			'title' => 'Альбом',
			'showAllOption' => true,
			'default_value' => self::FILTER_DEFAULT_ALBUM_ID,
		],
	];

	protected function initTable()
	{
		$this->setTable('photos');
	}

	protected function getFilterSettings($field = null)
	{
		if ($field === null)
		{
			return $this->filters;
		}
		else
		{
			if (isset($this->filters[$field]))
			{
				return $this->filters[$field];
			}
			else
			{
				throw new \Exception("No filter for '$field' specified");
			}
		}
	}

	protected function getAlbumsForFilter()
	{
		/** @var Albums $albums */
		$albums = \diCollection::create(Types::album);
		$albums
			->orderByOrderNum();

		return $albums;
	}

	protected function getAlbumsForForm()
	{
		/** @var Albums $albums */
		$albums = \diCollection::create(Types::album);
		$albums
			->orderByTitle();

		return $albums;
	}

	protected function setupFilters()
	{
		foreach ($this->getFilterSettings() as $field => $filter)
		{
			$this->getFilters()
				->addFilter($filter);
		}

		$this->getFilters()
			->buildQuery()
			->setSelectFromCollectionInput('album_id', $this->getAlbumsForFilter(),
				!empty($this->getFilterSettings('album_id')['showAllOption']) ? [0 => 'Все альбомы'] : []
			);
	}

	public function renderList()
	{
		$orderAllowed = function(Model $m, $action) {
			if (!$this->getFilters()->getData('album_id'))
			{
				return false;
			}

			return true;
		};

		$this->getGrid()
			->addButtons([
				'up' => [
					'allowed' => $orderAllowed,
				],
				'edit' => [],
				'del' => [],
				'visible' => [],
				'down' => [
					'allowed' => $orderAllowed,
				],
			]);
	}

	public function renderForm()
	{
		/** @var \diPhotoModel $photo */
		$photo = $this->getForm()->getModel();

		if (!$this->getId())
		{
			$this->getForm()
				->setHiddenInput([
					'token',
					'date',
					'comments_last_date',
					'comments_count',
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
			->setSelectFromCollectionInput('album_id', $this->getAlbumsForForm());
	}

	protected function getPicStoreOptions()
	{
		return $this->picStoreOptions;
	}

	public function submitForm()
	{
		$this->getSubmit()
			->makeSlug()
			->storeImage('pic', $this->getPicStoreOptions());
	}

	public function getFormTabs()
	{
		return [];
	}

	public function getFormFields()
	{
		return [
			'token' => [
				'type' => 'text',
                'title' => $this->localized([
                    'en' => 'Token',
                    'ru' => 'Токен для вставки',
                ]),
				'default' => '',
				'flags' => ['static', 'virtual'],
			],

			'album_id' => [
				'type' => 'int',
                'title' => $this->localized([
                    'en' => 'Album',
                    'ru' => 'Альбом',
                ]),
				'default' => 0,
			],

			'title' => [
				'type' => 'string',
                'title' => $this->localized([
                    'en' => 'Title',
                    'ru' => 'Название',
                ]),
				'default' => '',
			],

			'slug_source' => [
				'type' => 'string',
                'title' => $this->localized([
                    'en' => 'Slug source',
                    'ru' => 'Название для URL',
                ]),
				'default' => '',
			],

			'content' => [
				'type' => 'wysiwyg',
                'title' => $this->localized([
                    'en' => 'Description',
                    'ru' => 'Описание',
                ]),
				'default' => '',
			],

			'pic' => [
				'type' => 'pic',
                'title' => $this->localized([
                    'en' => 'Pic',
                    'ru' => 'Фото',
                ]),
				'default' => '',
			],

			'visible' => [
				'type' => 'checkbox',
                'title' => $this->localized([
                    'en' => 'Visible',
                    'ru' => 'Отображать на сайте',
                ]),
				'default' => 1,
			],

			'date' => [
				'type' => 'datetime_str',
                'title' => $this->localized([
                    'en' => 'Created at',
                    'ru' => 'Дата создания',
                ]),
				'default' => '',
                'flags' => ['static', 'untouchable', 'initially_hidden'],
			],

            'comments_enabled' => [
                'type' => 'checkbox',
                'title' => $this->localized([
                    'en' => 'Comments enabled',
                    'ru' => 'Разрешено комментировать',
                ]),
                'default' => 1,
            ],

            'comments_count' => [
                'type' => 'int',
                'title' => $this->localized([
                    'en' => 'Comments count',
                    'ru' => 'Кол-во комментариев',
                ]),
                'default' => 0,
                'flags' => ['static'],
            ],

            'comments_last_date' => [
                'type' => 'datetime_str',
                'title' => $this->localized([
                    'en' => 'Last comment date',
                    'ru' => 'Дата последнего комментария',
                ]),
                'default' => '',
                'flags' => ['static'],
            ],
		];
	}

	public function getLocalFields()
	{
		return [
			'slug' => [
				'type' => 'string',
				'title' => 'Slug',
				'default' => '',
			],

			'order_num' => [
				'type' => 'order_num',
				'title' => 'ord',
				'default' => 0,
				'direction' => -1,
			],

			'pic_w' => [
				'type' => 'int',
				'title' => 'Pic w',
				'default' => 0,
			],

			'pic_h' => [
				'type' => 'int',
				'title' => 'Pic h',
				'default' => 0,
			],

			'pic_t' => [
				'type' => 'int',
				'title' => 'Pic h',
				'default' => 0,
			],

			'pic_tn_w' => [
				'type' => 'int',
				'title' => 'Pic w',
				'default' => 0,
			],

			'pic_tn_h' => [
				'type' => 'int',
				'title' => 'Pic h',
				'default' => 0,
			],
		];
	}

	public function getModuleCaption()
	{
		return [
			'ru' => 'Фото',
			'en' => 'Photos',
		];
	}
}