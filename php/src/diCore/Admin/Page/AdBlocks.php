<?php
namespace diCore\Admin\Page;

use diCore\Admin\BasePage;
use diCore\Entity\Ad\Helper;
use diCore\Entity\AdBlock\Model;

class AdBlocks extends BasePage
{
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
		$this->setTable('ad_blocks');
	}

	public function renderList()
	{
		$this->getTpl()->define('`ad_blocks/list', [
			'page',
		]);

		$this->getList()->addColumns([
			'id' => 'ID',
			'token' => [
				'title' => 'Токен',
				'value' => Model::INCUT_TEMPLATE_FOR_ADMIN,
				'attrs' => [
					'width' => '10%',
				],
			],
			'title' => [
				'title' => 'Название',
				'attrs' => [
					'width' => '90%',
				],
			],
			'#manage' => [
				'href' => [
					'module' => 'ads',
					'params' => 'block_id=%id%',
				],
				'icon' => 'img',
			],
			'#edit' => '',
			'#del' => '',
			'#visible' => '',
			'#up' => '',
			'#down' => '',
		]);
	}

	public function renderForm()
	{
		$ad_transitions_ar2 = Helper::$adTransitionsAr;
		unset($ad_transitions_ar2[0]);

		$ad_transition_styles_ar2 = Helper::$adTransitionStylesAr;
		unset($ad_transition_styles_ar2[0]);

		$this->getForm()
			->setSelectFromArrayInput('transition', $ad_transitions_ar2)
			->setSelectFromArrayInput('transition_style', Helper::$adTransitionStylesAr)
			->setSelectFromArrayInput('slides_order', Helper::$adSlidesOrdersAr);

		if ($this->getId())
		{
			$this->getForm()->setInput('token', $this->getForm()->getModel()->getToken());
		}
		else
		{
			$this->getForm()->setHiddenInput('token');
		}
	}

	public function submitForm()
	{
	}

	public function getFormFields()
	{
		return [
			'title' => [
				'type' => 'string',
				'title' => 'Название блока',
				'default' => '',
			],

			'default_slide_title' => [
				'type' => 'string',
				'title' => 'Заголовок слайдов по умолчанию',
				'default' => '',
			],

			'default_slide_content' => [
				'type' => 'text',
				'title' => 'Описание слайдов по умолчанию',
				'default' => '',
			],

			'token' => [
				'type' => 'string',
				'title' => 'Токен',
				'default' => '',
				'flags' => ['virtual'],
			],

			'transition' => [
				'type' => 'int',
				'title' => 'Переход',
				'default' => 0,
			],

			'transition_style' => [
				'type' => 'int',
				'title' => 'Стиль перехода (только для скроллинга)',
				'default' => 0,
			],

			'duration_of_show' => [
				'type' => 'int',
				'title' => 'Время показа слайда, мс',
				'default' => 10000,
			],

			'duration_of_change' => [
				'type' => 'int',
				'title' => 'Время смены слайда, мс',
				'default' => 800,
			],

			'slides_order' => [
				'type' => 'int',
				'title' => 'Порядок смены слайдов',
				'default' => 0,
			],

			'ignore_hover_hold' => [
				'type' => 'checkbox',
				'title' => 'Игнорировать наведение мыши на слайды (продолжать перелистывать)',
				'default' => 0,
			],

			'date' => [
				'type' => 'datetime_str',
				'title' => 'Дата создания',
                'default' => \diDateTime::sqlFormat(),
                'flags' => ['static', 'untouchable', 'initially_hidden'],
			],
		];
	}

	public function getLocalFields()
	{
		return [
			'order_num' => [
				'type' => 'order_num',
				'title' => 'Order num',
				'default' => 0,
				'direction' => 1,
			],
		];
	}

	public function getModuleCaption()
	{
		return 'Рекламные блоки';
	}
}