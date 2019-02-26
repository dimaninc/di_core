<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.10.2017
 * Time: 9:21
 */

namespace diCore\Admin\Page;

use diCore\Admin\Submit;
use diCore\Helper\FileSystemHelper;
use diCore\Entity\Video\Model;

class Videos extends \diCore\Admin\BasePage
{
	const FILTER_DEFAULT_ALBUM_ID = null;

	protected $listMode = self::LIST_GRID;

	protected $picFields = ['pic'];
	protected $videoFields = ['video_mp4', 'video_m4v', 'video_ogv', 'video_webm'];

	protected $options = [
		'filters' => [
			'defaultSorter' => [
				'sortBy' => 'order_num',
				'dir' => 'ASC',
			],
		],
	];

	protected function initTable()
	{
		$this->setTable('videos');
	}

	protected function setupFilters()
	{
		$this->getFilters()
			->addFilter([
				'field' => 'album_id',
				'type' => 'int',
				'title' => [
				    'ru' => 'Альбом',
                    'en' => 'Album',
				],
				'default_value' => static::FILTER_DEFAULT_ALBUM_ID,
			])
			->buildQuery()
			->setSelectFromCollectionInput('album_id',
                \diCollection::create(\diTypes::album)->orderByTitle(),
                [
                    0 => $this->getLanguage() == 'ru' ? 'Все альбомы' : 'All albums',
                ]
            );
	}

	public function orderChangeAllowed()
	{
		return !!$this->getFilters()->getData('album_id');
	}

	public function renderList()
	{
		switch ($this->listMode)
		{
			case self::LIST_LIST:
				$this->renderListTable();
				break;

			case self::LIST_GRID:
				$this->renderListGrid();
				break;

			default:
				throw new \Exception('Unknown list mode ' . $this->listMode);
				break;
		}
	}

	protected function renderListGrid()
	{
		$that = $this;

		$orderAllowed = function(Model $m, $action) use($that) {
			return $that->orderChangeAllowed();
		};

		$this->getGrid()
			->addButtons([
				'up' => [
					'allowed' => $orderAllowed,
				],
				'edit' => '',
				'del' => '',
				'visible' => '',
				'top' => '',
				'down' => [
					'allowed' => $orderAllowed,
				],
			]);
	}

	protected function renderListTable()
	{
		$this->getList()->addColumns([
			'id' => 'ID',
			'#href' => '',
			'pic' => [
				'bodyAttrs' => [
					'class' => 'no-padding',
				],
				'value' => function(Model $v) {
					if ($v->hasPic())
					{
						$pic = '/' . $v->getPicsFolder() . $v->getPic();
					}
					else
					{
						$pic = $v->getVideoVendorPreview() ?: '';
					}

					return sprintf('<img src="%s" width="200">', $pic);
				},
			],
			'date' => [
				'value' => function(Model $v) {
					return \diDateTime::simpleFormat($v->getDate());
				},
				'attrs' => [
					'width' => '10%',
				],
				'headAttrs' => [],
				'bodyAttrs' => [
					'class' => 'dt',
				],
			],
			'title' => [
				'attrs' => [
					'width' => '90%',
				],
			],
			'#edit' => '',
			'#del' => '',
			'#visible' => '',
			//'#up' => '',
			//'#down' => '',
		]);
	}

	/**
	 * Prefix added before IMG urls. If it is an external URL, this should return empty string
	 *
	 * @param Model $model
	 * @return string
	 */
	public function getImgUrlPrefix(\diModel $model)
	{
		if ($model->getVendor() != \diVideoVendors::Own)
		{
			return '';
		}

		return parent::getImgUrlPrefix($model);
	}

	protected function getFilesFolder()
	{
		return get_files_folder($this->getTable());
	}

	public function renderForm()
	{
		$this->getTpl()
			->define('`videos/form', [
				'after_form',
			]);

		$filesFolder = '/' . $this->getFilesFolder();

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
			/** @var Model $m */
			$m = $this->getForm()->getModel();

			if ($m->getVendor() != \diVideoVendors::Own)
			{
				$this->getForm()
					->setInput('vendor_pic', sprintf('<img src="%s">',
						\diVideoVendors::getPoster($m->getVendor(), $m->getVendorVideoUid())));
			}
		}

		foreach ($this->videoFields as $field)
		{
			$this->getForm()->setFileInput($field, $filesFolder);
		}

		$this->getForm()
			->setSelectFromCollectionInput('album_id', \diCollection::create(\diTypes::album)->orderByTitle())
			->setSelectFromArrayInput('vendor', \diVideoVendors::$titles);
	}

	public function submitForm()
	{
		$this->getSubmit()
			->makeSlug()
			->storeImage($this->picFields, [
				[
					'type' => Submit::IMAGE_TYPE_MAIN,
					//'resize' => diImage::DI_THUMB_FIT,
				],
				[
					'type' => Submit::IMAGE_TYPE_PREVIEW,
					'resize' => \diImage::DI_THUMB_FIT,
				],
			])
			->storeFile($this->videoFields, function($F, $field, $folder, $fn, Submit &$obj) {
				$folder = get_files_folder($obj->getTable());

				FileSystemHelper::createTree(\diPaths::fileSystem($this->getSubmit()->getModel()),
					$folder, Submit::DIR_CHMOD);

				Submit::storeFileCallback($obj, $field, [
					'folder' => $folder,
					'filename' => $fn,
				], $F);
			});

		if ($this->getSubmit()->getData('vendor') != \diVideoVendors::Own)
		{
			$videoInfo = \diVideoVendors::extractInfoFromEmbed(stripslashes($this->getSubmit()->getData('embed')));

			if (!$videoInfo['video_uid'])
			{
				$videoInfo = \diVideoVendors::extractInfoFromEmbed(stripslashes($this->getSubmit()->getData('vendor_video_uid')));
			}

			if ($videoInfo['video_uid'])
			{
				$this->getSubmit()
					->setData('vendor_video_uid', $videoInfo['video_uid']);
			}

			if ($videoInfo['vendor'])
			{
				$this->getSubmit()
					->setData('vendor', $videoInfo['vendor']);
			}

			if (
				!$this->getSubmit()->getData('title') &&
				$this->getSubmit()->getData('vendor') &&
				$this->getSubmit()->getData('vendor_video_uid')
			   )
			{
				$this->getSubmit()->setData('title', \diVideoVendors::getTitle(
					$this->getSubmit()->getData('vendor'),
					$this->getSubmit()->getData('vendor_video_uid')
				));
			}
		}
	}

	public function getFormTabs()
	{
		return [
			'video' => [
			    'ru' => 'Видео',
                'en' => 'Video',
            ],
		];
	}

	public function getFormFields()
	{
		return [
			'token' => [
				'type' => 'string',
				'title' => $this->localized([
				    'ru' => 'Токен',
                    'en' => 'Token',
				]),
				'default' => '',
				'flags' => ['static', 'virtual'],
			],

			'album_id' => [
				'type' => 'int',
				'title' => $this->localized([
                    'ru' => 'Альбом',
                    'en' => 'Album',
                ]),
				'default' => 0,
			],

			'title' => [
				'type' => 'string',
				'title' => $this->localized([
                    'ru' => 'Название',
                    'en' => 'Title',
                ]),
				'default' => '',
			],

			'slug_source' => [
				'type' => 'string',
				'title' => $this->localized([
                    'ru' => 'Название для URL',
                    'en' => 'Slug source',
                ]),
				'default' => '',
			],

			'content' => [
				'type' => 'wysiwyg',
				'title' => $this->localized([
                    'ru' => 'Описание',
                    'en' => 'Description',
                ]),
				'default' => '',
			],

			'vendor' => [
				'type' => 'int',
				'title' => $this->localized([
                    'ru' => 'Видео-хостинг',
                    'en' => 'Video-hosting',
                ]),
				'default' => 0,
				'tab' => 'video',
			],

			'embed' => [
				'type' => 'text',
				'title' => $this->localized([
                    'ru' => 'EMBED-код видео',
                    'en' => 'Embed code for video',
                ]),
				'default' => '',
				'tab' => 'video',
			],

			'vendor_video_uid' => [
				'type' => 'string',
				'title' => $this->localized([
                    'ru' => 'UID видео',
                    'en' => 'UID of video',
                ]),
				'default' => '',
				'tab' => 'video',
			],

			'pic' => [
				'type' => 'pic',
				'title' => $this->localized([
                    'ru' => 'Превью',
                    'en' => 'Preview',
                ]),
				'default' => '',
			],

			'vendor_pic' => [
				'type' => 'string',
				'title' => $this->localized([
                    'ru' => 'Превью с видео-хостинга',
                    'en' => 'Preview from video-hosting',
                ]),
				'default' => '',
				'flags' => ['virtual', 'static'],
			],

			'video_mp4' => [
				'type' => 'file',
				'title' => $this->localized([
                    'ru' => 'Видео в формате MP4',
                    'en' => 'Video, MP4',
                ]),
				'default' => '',
				'attrs' => ['accept' => '.mp4'],
				'tab' => 'video',
			],

			'video_m4v' => [
				'type' => 'file',
				'title' => $this->localized([
                    'ru' => 'Видео в формате M4V',
                    'en' => 'Video, M4V',
                ]),
				'default' => '',
				'attrs' => ['accept' => '.m4v'],
				'tab' => 'video',
			],

			'video_ogv' => [
				'type' => 'file',
				'title' => $this->localized([
                    'ru' => 'Видео в формате OGV',
                    'en' => 'Video, OGV',
                ]),
				'default' => '',
				'attrs' => ['accept' => '.ogv'],
				'tab' => 'video',
			],

			'video_webm' => [
				'type' => 'file',
				'title' => $this->localized([
                    'ru' => 'Видео в формате WEBM',
                    'en' => 'Video, WEBM',
                ]),
				'default' => '',
				'attrs' => ['accept' => '.webm'],
				'tab' => 'video',
			],

			'video_w' => [
				'type' => 'int',
				'title' => $this->localized([
                    'ru' => 'Ширина видео, px',
                    'en' => 'Video width, px',
                ]),
				'default' => 0,
				'tab' => 'video',
			],

			'video_h' => [
				'type' => 'int',
				'title' => $this->localized([
                    'ru' => 'Высота видео, px',
                    'en' => 'Video height, px',
                ]),
				'default' => 0,
				'tab' => 'video',
			],

			'visible' => [
				'type' => 'checkbox',
				'title' => $this->localized([
                    'ru' => 'Отображать на сайте',
                    'en' => 'Visible',
                ]),
				'default' => 1,
			],

			'date' => [
				'type' => 'datetime_str',
				'title' => $this->localized([
                    'ru' => 'Дата добавления',
                    'en' => 'Created at',
                ]),
				'default' => \diDateTime::sqlFormat(),
				'flags' => ['static'],
			],

			'comments_enabled' => [
				'type' => 'checkbox',
				'title' => $this->localized([
                    'ru' => 'Разрешено комментировать',
                    'en' => 'Comments enabled',
                ]),
				'default' => 1,
			],

			'comments_count' => [
				'type' => 'int',
				'title' => $this->localized([
                    'ru' => 'Кол-во комментариев',
                    'en' => 'Comments count',
                ]),
				'default' => 0,
                'flags'		=> ['static', 'untouchable', 'initially_hidden'],
			],

			'comments_last_date' => [
				'type' => 'datetime_str',
				'title' => $this->localized([
                    'ru' => 'Дата последнего комментария',
                    'en' => 'Last comment date',
                ]),
				'default' => null,
                'flags'		=> ['static', 'untouchable', 'initially_hidden'],
			],

			'views_count' => [
				'type' => 'int',
				'title' => $this->localized([
                    'ru' => 'Кол-во просмотров',
                    'en' => 'Views count',
                ]),
				'default' => 0,
                'flags'		=> ['static', 'untouchable', 'initially_hidden'],
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
			'ru' => 'Видео',
			'en' => 'Videos',
		];
	}
}