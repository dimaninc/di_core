<?php
/**
 * Created by \diAdminPagesManager
 * Date: 11.12.2017
 * Time: 15:59
 */

namespace diCore\Admin\Page;

use diCore\Entity\MailPlan\Mode;
use diCore\Entity\MailPlan\Model;

class MailPlans extends \diCore\Admin\BasePage
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
		$this->setTable('mail_plans');
	}

	public function renderList()
	{
		$this->getList()->addColumns([
			'id' => 'ID',
			'target_type' => [
				'headAttrs' => [
					'width' => '10%',
				],
			],
			'target_id' => [
				'headAttrs' => [
					'width' => '10%',
				],
			],
			'mode' => [
				'headAttrs' => [
					'width' => '10%',
				],
				'value' => function(Model $m) {
					return $m->getModeTitle();
				},
			],
			'conditions' => [
				'headAttrs' => [
					'width' => '50%',
				],
			],
			'created_at' => [
				'value' => function(Model $m) {
					return \diDateTime::format('d.m.Y H:i', $m->getCreatedAt());
				},
				'headAttrs' => [
					'width' => '10%',
				],
				'bodyAttrs' => [
					'class' => 'dt',
				],
			],
			'processed_at' => [
				'value' => function(Model $m) {
					return $m->hasProcessedAt() ? \diDateTime::format('d.m.Y H:i', $m->getProcessedAt()) : '&mdash;';
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
		]);
	}

	public function renderForm()
	{
		$this->getForm()
			->setSelectFromArrayInput('mode', Mode::$titles);
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
				'type'		=> 'int',
				'title'		=> 'Target type',
				'default'	=> '',
			],

			'target_id' => [
				'type'		=> 'int',
				'title'		=> 'Target id',
				'default'	=> '',
			],

			'mode' => [
				'type'		=> 'int',
				'title'		=> 'Mode',
				'default'	=> '',
			],

			'conditions' => [
				'type'		=> 'text',
				'title'		=> 'Conditions',
				'default'	=> '',
			],

			'created_at' => [
				'type'		=> 'datetime_str',
				'title'		=> 'Created at',
				'default'	=> '',
				'flags'		=> ['static', 'untouchable', 'initially_hidden'],
			],

			'processed_at' => [
				'type'		=> 'datetime_str',
				'title'		=> 'Processed at',
				'default'	=> '',
				'flags'		=> ['static', 'untouchable', 'initially_hidden'],
			],
		];
	}

	public function getLocalFields()
	{
		return [];
	}

	public function getModuleCaption()
	{
		return 'План рассылки';
	}
}