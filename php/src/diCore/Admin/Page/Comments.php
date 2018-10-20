<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.10.2017
 * Time: 22:45
 */

namespace diCore\Admin\Page;

use diCore\Data\Types;
use diCore\Entity\Comment\Model;
use diCore\Helper\ArrayHelper;

class Comments extends \diCore\Admin\BasePage
{
	protected $options = [
		'filters' => [
			'defaultSorter' => [
				'sortBy' => 'id',
				'dir' => 'DESC',
			],
		],
	];

	protected function initTable()
	{
		$this->setTable('comments');
	}

	public static function getUsedTargetTypes()
	{
		return array_keys(Types::titles());
	}

	protected function getUsedTargetTypesTitles()
	{
		return ArrayHelper::filterByKey(\diTypes::titles(), $this->getUsedTargetTypes());
	}

	protected function getUsedUserFields()
	{
		return [
			'name',
			'login',
			'email',
		];
	}

	protected function setupFilters()
	{
		$this->getFilters()
			->addFilter([
				'field' => 'target_type',
				'type' => 'int',
				'title' => 'Тип объекта',
			])
			->addFilter([
				'field' => 'target_id',
				'type' => 'int',
				'title' => 'ID объекта',
			])
			->addFilter([
				'field' => 'user_id',
				'type' => 'string',
				'title' => 'Автор',
				'where_tpl' => \diAdminFilters::get_user_id_where($this->getUsedUserFields()),
			])
			->buildQuery()
			->setSelectFromArrayInput('target_type', $this->getUsedTargetTypesTitles(), [0 => 'Все типы']);
	}

	public function renderList()
	{
		$this->getList()->addColumns([
			'id' => 'ID',
			'#href' => [
				'href' => function (Model $m) {
					return $m->getTargetModel()->getHref();
				},
			],
			'target_id' => [
				'headAttrs' => [
					'width' => '30%',
				],
				'value' => function (Model $m) {
					return $m->getDescriptionForAdmin();
				},
			],
			'user_id' => [
				'headAttrs' => [
					'width' => '20%',
				],
				'value' => function (Model $m) {
					return $m->getUserAppearance();
				},
			],
			'content' => [
				'headAttrs' => [
					'width' => '40%',
				],
				'bodyAttrs' => [
					'class' => 'lite',
				],
			],
			'date' => [
				'title' => 'Дата',
				'value' => function (Model $m) {
					return \diDateTime::format('d.m.Y H:i', strtotime($m->getDate()));
				},
				'headAttrs' => [
					'width' => '10%',
				],
				'bodyAttrs' => [
					'class' => 'dt',
				],
			],
			'#edit' => '',
			'#del' => '',
			'#visible' => '',
		]);
	}

	public function renderForm()
	{
		/** @var Model $comment */
		$comment = $this->getForm()->getModel();
		$user = $comment->getUserModel();

		$this->getForm()
			->setInput(
				'target_id',
				$comment->getDescriptionForAdmin() . " [<a href=\"{$comment->getTargetModel()->getHref()}\" target=\"_blank\">ссылка</a>]"
			)
			->setInput('user_id', $comment->getUserAppearance($user) . " [<a href=\"{$user->getTable()}/form/{$user->getId()}/\" target=\"_blank\">ссылка</a>]");
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
			'target_type' => [
				'type' => 'int',
				'title' => 'Тип записи',
				'default' => '',
				'flags' => ['hidden'],
			],

			'target_id' => [
				'type' => 'int',
				'title' => 'Комментарий к',
				'default' => 0,
				'flags' => ['static'],
			],

			'user_id' => [
				'type' => 'int',
				'title' => 'Автор',
				'default' => 0,
				'flags' => ['static'],
			],

			'content' => [
				'type' => 'text',
				'title' => 'Комментарий',
				'default' => '',
			],

			'ip' => [
				'type' => 'ip',
				'title' => 'IP/Host',
				'default' => '',
				'flags' => ['static'],
			],

			'date' => [
				'type' => 'datetime_str',
				'title' => 'Дата публикации',
				'default' => time(),
				'flags' => ['static'],
			],

			'karma' => [
				'type' => 'int',
				'title' => 'Карма',
				'default' => 0,
				'flags' => ['static'],
			],

			'evil_score' => [
				'type' => 'int',
				'title' => 'Уровень зла (модерация)',
				'default' => 0,
				'flags' => ['static'],
			],
		];
	}

	public function getLocalFields()
	{
		return [
			'parent' => [
				'type' => 'int',
				'title' => 'Parent',
				'default' => 0,
			],

			'level_num' => [
				'type' => 'int',
				'title' => 'Level num',
				'default' => 0,
			],
		];
	}

	public function getModuleCaption()
	{
		return 'Комментарии';
	}

	public function addButtonNeededInCaption()
	{
		return false;
	}
}