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
        $this->setBeforeTableTemplate(null, [
            'total_records' => $this->getPagesNavy()->getTotalRecords(),
            'visible_worker_uri' => \diLib::getAdminWorkerPath('mail', 'set_visible') . '?back=' . urlencode($_SERVER['REQUEST_URI']),
            'send_all_worker_uri' => \diLib::getAdminWorkerPath('mail', 'send_all'),
            'lang' => [
                'total_records' => $this->localized([
                    'ru' => 'Всего записей',
                    'en' => 'Total records',
                ]),
                'send1k' => $this->localized([
                    'ru' => 'Обработать 1000 писем из очереди',
                    'en' => 'Send 1000 messages from queue',
                ]),
                'make_visible' => $this->localized([
                    'ru' => 'Скрытые &raquo; Видимые',
                    'en' => 'Invisible &raquo; Visible',
                ]),
            ],
        ]);

		return $this;
	}

	public function renderList()
	{
		$this->renderControlPanel();

		$this->getList()->addColumns([
			'id' => 'ID',
			'_checkbox' => '',
            'send' => [
                'title' => '✉️',
                'value' => function (Model $m) {
                    return sprintf(
                        '<a href="/api/mail/send/%s" style="color: green; display: block; font-size: 16px" title="Send">⇒</a>',
                        $m->getId()
                    );
                },
            ],
			'sender' => [
				'attrs' => [
					'width' => '20%',
				],
				'value' => function (Model $m) {
					$s = StringHelper::out($m->getSender());

					if ($m->hasReplyTo()) {
						$s .= '<div class="lite">' . StringHelper::out('Reply-To: ' . $m->getReplyTo()) . '</div>';
					}

					return $s;
				},
			],
			'recipient' => [
				'attrs' => [
					'width' => '30%',
				],
				'value' => function (Model $m) {
					return StringHelper::out($m->getRecipient());
				},
			],
			'subject' => [
				'attrs' => [
					'width' => '40%',
				],
			],
            'attachment' => [
                'title' => '&#128206;',
                'value' => function (Model $m) {
		            $att = $m->hasAttachment()
                        ? unserialize($m->getAttachment())
                        : [];

                    return $att ? count($att) : '';
                },
            ],
			'date' => [
				'value' => function (Model $m) {
					return \diDateTime::simpleFormat($m->getDate());
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

		if ($user_r) {
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
				'title' => $this->localized([
                    'ru' => 'Дата',
                    'en' => 'Created at',
                ]),
				'default' => \diDateTime::sqlFormat(),
				'flags' => ['static'],
			],

			'sender' => [
				'type' => 'string',
				'title' => $this->localized([
                    'ru' => 'От',
                    'en' => 'From',
                ]),
				'default' => 'support@' . Config::getMainDomain(),
			],

			'recipient_id' => [
				'type' => 'int',
				'title' => $this->localized([
                    'ru' => 'Кому (Логин)',
                    'en' => 'To (Login)',
                ]),
				'default' => 0,
			],

			'recipient' => [
				'type' => 'string',
				'title' => $this->localized([
                    'ru' => 'Кому (E-mail)',
                    'en' => 'To (E-mail)',
                ]),
				'default' => '',
			],

			'reply_to' => [
				'type' => 'string',
				'title' => 'Reply-To',
				'default' => '',
			],

			'subject' => [
				'type' => 'string',
				'title' => $this->localized([
                    'ru' => 'Тема',
                    'en' => 'Subject',
                ]),
				'default' => '',
			],

			'plain_body' => [
				'type' => 'int',
				'title' => $this->localized([
                    'ru' => 'Формат письма',
                    'en' => 'Format',
                ]),
				'default' => 0,
			],

			'body' => [
				'type' => 'wysiwyg',
				'title' => $this->localized([
                    'ru' => 'Тело письма',
                    'en' => 'Message',
                ]),
				'default' => '',
			],

            /*
			'attachment' => [
				'type' => 'text',
				'title' => $this->localized([
                    'ru' => 'Вложения (serialized)',
                    'en' => 'Attachments (serialized)',
                ]),
				'default' => '',
				'flags' => ['static'],
			],
            */

			'visible' => [
				'type' => 'checkbox',
				'title' => $this->localized([
                    'ru' => 'Активно',
                    'en' => 'Active',
                ]),
				'default' => true,
			],

			'sent' => [
				'type' => 'checkbox',
				'title' => $this->localized([
                    'ru' => 'Отослано',
                    'en' => 'Sent',
                ]),
				'default' => false,
				'flags' => ['static'],
			],

			'settings' => [
				'type' => 'text',
				'title' => $this->localized([
                    'ru' => 'Настройки (serialized)',
                    'en' => 'Settings (serialized)',
                ]),
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