<?php
/**
 * Created by \diAdminPagesManager
 * Date: 04.12.2017
 * Time: 18:35
 */

namespace diCore\Admin\Page;

use diCore\Entity\MailIncut\Model;

class MailIncuts extends \diCore\Admin\BasePage
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
		$this->setTable('mail_incuts');
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
			'type' => [
				'headAttrs' => [
					'width' => '10%',
				],
			],
			'content' => [
				'headAttrs' => [
					'width' => '10%',
				],
			],
			'#edit' => '',
			'#del' => '',
		]);
	}

	public function renderForm()
	{
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
				'title'		=> 'Target Type',
				'default'	=> '',
				'flags'     => ['static'],
			],

			'target_id' => [
				'type'		=> 'int',
				'title'		=> 'Target Id',
				'default'	=> '',
				'flags'     => ['static'],
			],

			'type' => [
				'type'		=> 'int',
				'title'		=> 'Type',
				'default'	=> '',
				'flags'     => ['static'],
			],

			'content' => [
				'type'		=> 'text',
				'title'		=> 'Content',
				'default'	=> '',
				'flags'     => ['static'],
			],
		];
	}

	public function getLocalFields()
	{
		return [];
	}

	public function getModuleCaption()
	{
		return 'Вложения в письмо';
	}
}