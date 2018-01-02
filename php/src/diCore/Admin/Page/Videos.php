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
				'title' => 'Альбом',
				'default_value' => static::FILTER_DEFAULT_ALBUM_ID,
			])
			->buildQuery()
			->setSelectFromDbInput('album_id', $this->getDb()->rs('albums', 'ORDER BY order_num ASC'), [0 => 'Все альбомы']);
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
				'title' => 'Дата',
				'value' => function(Model $v) {
					return \diDateTime::format('d.m.Y H:i', $v->getDate());
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
			->setSelectFromDbInput('album_id', $this->getDb()->rs('albums', 'ORDER BY title ASC'))
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

				FileSystemHelper::createTree(\diPaths::fileSystem($this->getSubmit()->getSubmittedModel()),
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
			'video' => 'Видео',
		];
	}

	public function getFormFields()
	{
		return [
			'token' => [
				'type' => 'string',
				'title' => 'Токен',
				'default' => '',
				'flags' => ['static', 'virtual'],
			],

			'album_id' => [
				'type' => 'int',
				'title' => 'Альбом',
				'default' => 0,
			],

			'title' => [
				'type' => 'string',
				'title' => 'Название',
				'default' => '',
			],

			'slug_source' => [
				'type' => 'string',
				'title' => 'Название для URL',
				'default' => '',
			],

			'content' => [
				'type' => 'wysiwyg',
				'title' => 'Описание',
				'default' => '',
			],

			'vendor' => [
				'type' => 'int',
				'title' => 'Видео-хостинг',
				'default' => 0,
				'tab' => 'video',
			],

			'embed' => [
				'type' => 'text',
				'title' => 'EMBED-код видео',
				'default' => '',
				'tab' => 'video',
			],

			'vendor_video_uid' => [
				'type' => 'string',
				'title' => 'UID видео',
				'default' => '',
				'tab' => 'video',
			],

			'pic' => [
				'type' => 'pic',
				'title' => 'Превью',
				'default' => '',
			],

			'vendor_pic' => [
				'type' => 'string',
				'title' => 'Превью с видео-хостинга',
				'default' => '',
				'flags' => ['virtual', 'static'],
			],

			'video_mp4' => [
				'type' => 'file',
				'title' => 'Видео в формате MP4',
				'default' => '',
				'attrs' => ['accept' => '.mp4'],
				'tab' => 'video',
			],

			'video_m4v' => [
				'type' => 'file',
				'title' => 'Видео в формате M4V',
				'default' => '',
				'attrs' => ['accept' => '.m4v'],
				'tab' => 'video',
			],

			'video_ogv' => [
				'type' => 'file',
				'title' => 'Видео в формате OGV',
				'default' => '',
				'attrs' => ['accept' => '.ogv'],
				'tab' => 'video',
			],

			'video_webm' => [
				'type' => 'file',
				'title' => 'Видео в формате WEBM',
				'default' => '',
				'attrs' => ['accept' => '.webm'],
				'tab' => 'video',
			],

			'video_w' => [
				'type' => 'int',
				'title' => 'Ширина видео, px',
				'default' => 0,
				'tab' => 'video',
			],

			'video_h' => [
				'type' => 'int',
				'title' => 'Высота видео, px',
				'default' => 0,
				'tab' => 'video',
			],

			'visible' => [
				'type' => 'checkbox',
				'title' => 'Отображать на сайте',
				'default' => 1,
			],

			'date' => [
				'type' => 'datetime_str',
				'title' => 'Дата добавления',
				'default' => '',
				'flags' => ['static'],
			],

			'comments_enabled' => [
				'type' => 'checkbox',
				'title' => 'Разрешено комментировать',
				'default' => 1,
			],

			'comments_count' => [
				'type' => 'int',
				'title' => 'Кол-во комментариев',
				'default' => 0,
				'flags' => ['static'],
			],

			'comments_last_date' => [
				'type' => 'datetime_str',
				'title' => 'Дата последнего комментария',
				'default' => '',
				'flags' => ['static'],
			],

			'views_count' => [
				'type' => 'int',
				'title' => 'Кол-во просмотров',
				'default' => 0,
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
		return 'Видео';
	}
}