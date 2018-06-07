<?php

namespace diCore\Admin\Page;

use diCore\Data\Config;
use diCore\Entity\MailQueue\Model;
use diCore\Helper\StringHelper;

class MailQueue extends \diCore\Admin\BasePage
{
	protected $options = [
		'showControlPanel' => true,
		'filters' => [
			'defaultSorter' => [
				'sortBy' => 'id',
				'dir' => 'DESC',
			],
		],
	];

	protected function initTable()
	{
		$this->setTable('mail_queue');
	}

	protected function renderControlPanel()
	{
		$this->getTpl()->define('`mail_queue/list', [
			'before_table',
		])->assign([
			'TOTAL_RECORDS' => $this->getPagesNavy()->getTotalRecords(),
			'VISIBLE_WORKER_URI' => \diLib::getAdminWorkerPath('mail', 'set_visible') . '?back=' . urlencode($_SERVER['REQUEST_URI']),
			'SEND_ALL_WORKER_URI' => \diLib::getAdminWorkerPath('mail', 'send_all'),
		]);

		return $this;
	}

	public function renderList()
	{
		$this->renderControlPanel();

		$this->getList()->addColumns([
			'id' => 'ID',
			'_checkbox' => '',
			'sender' => [
				'title' => 'Отправитель',
				'attrs' => [
					'width' => '20%',
				],
				'value' => function (Model $m) {
					$s = StringHelper::out($m->getSender());

					if ($m->hasReplyTo())
					{
						$s .= '<div class="lite">' . StringHelper::out('Reply-To: ' . $m->getReplyTo()) . '</div>';
					}

					return $s;
				},
			],
			'recipient' => [
				'title' => 'Получатель',
				'attrs' => [
					'width' => '30%',
				],
				'value' => function (Model $m) {
					return StringHelper::out($m->getRecipient());
				},
			],
			'subject' => [
				'title' => 'Тема',
				'attrs' => [
					'width' => '40%',
				],
			],
			'date' => [
				'title' => 'Дата',
				'value' => function (Model $m) {
					return date('d.m.Y H:i', strtotime($m->getDate()));
				},
				'attrs' => [
					'width' => '10%',
				],
				'headAttrs' => [],
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
		$user_id = \diRequest::get('user_id', 0);
		$user_r = !$this->getForm()->getId() && $user_id ? $this->getDb()->r('users', $user_id) : false;

		if ($user_r)
		{
			$this->getForm()->setData('recipient', StringHelper::out($user_r->email));
		}

		$this->getForm()
			->setSelectFromArrayInput('plain_body', Model::$bodyTypes)
			->setHiddenInput('recipient_id');
	}

	public function submitForm()
	{
	}

	public function getFormFields()
	{
		return [
			'date' => [
				'type' => 'datetime_str',
				'title' => 'Дата',
				'default' => \diDateTime::sqlFormat(),
				'flags' => ['static'],
			],

			'sender' => [
				'type' => 'string',
				'title' => 'От',
				'default' => 'support@' . Config::getMainDomain(),
			],

			'recipient_id' => [
				'type' => 'int',
				'title' => 'Кому (Логин)',
				'default' => 0,
			],

			'recipient' => [
				'type' => 'string',
				'title' => 'Кому (E-mail)',
				'default' => '',
			],

			'reply_to' => [
				'type' => 'string',
				'title' => 'Reply-To',
				'default' => '',
			],

			'subject' => [
				'type' => 'string',
				'title' => 'Тема',
				'default' => '',
			],

			'plain_body' => [
				'type' => 'int',
				'title' => 'Формат письма',
				'default' => 0,
			],

			'body' => [
				'type' => 'wysiwyg',
				'title' => 'Тело письма',
				'default' => '',
			],

			'attachment' => [
				'type' => 'text',
				'title' => 'Вложения (serialized)',
				'default' => '',
				'flags' => ['static'],
			],

			'visible' => [
				'type' => 'checkbox',
				'title' => 'Активно',
				'default' => true,
			],

			'sent' => [
				'type' => 'checkbox',
				'title' => 'Отослано',
				'default' => false,
				'flags' => ['static'],
			],

			'settings' => [
				'type' => 'text',
				'title' => 'Настройки (serialized)',
				'default' => '',
				'flags' => ['static'],
			],
		];
	}

	public function getLocalFields()
	{
		return [];
	}

	public function getModuleCaption()
	{
		return [
			'ru' => 'Почтовая очередь',
			'en' => 'Mail queue',
		];
	}
}