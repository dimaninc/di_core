<?php

namespace diCore\Admin\Page;

use diCore\Entity\MailQueue\Model;
use diCore\Helper\StringHelper;

class MailQueue extends \diAdminBasePage
{
	protected $options = [
		"filters" => [
			"defaultSorter" => [
				"sortBy" => "id",
				"dir" => "DESC",
			],
		],
	];

	protected function initTable()
	{
		$this->setTable("mail_queue");
	}

	protected function renderControlPanel()
	{
		$this->getTpl()->define("`mail_queue/list", [
			"before_table",
		])->assign([
			"TOTAL_RECORDS" => $this->getPagesNavy()->getTotalRecords(),
			"VISIBLE_WORKER_URI" => \diLib::getAdminWorkerPath("mail", "set_visible") . "?back=" . urlencode($_SERVER["REQUEST_URI"]),
			"SEND_ALL_WORKER_URI" => \diLib::getAdminWorkerPath("mail", "send_all"),
		]);

		return $this;
	}

	public function renderList()
	{
		$this->renderControlPanel();

		$this->getList()->addColumns([
			"id" => "ID",
			"sender" => [
				"title" => "Отправитель",
				"attrs" => [
					"width" => "20%",
				],
			],
			"recipient" => [
				"title" => "Получатель",
				"attrs" => [
					"width" => "30%",
				],
			],
			"subject" => [
				"title" => "Тема",
				"attrs" => [
					"width" => "40%",
				],
			],
			"date" => [
				"title" => "Дата",
				"value" => function (Model $m) {
					return date("d.m.Y H:i", strtotime($m->getDate()));
				},
				"attrs" => [
					"width" => "10%",
				],
				"headAttrs" => [],
				"bodyAttrs" => [
					"class" => "dt",
				],
			],
			"#edit" => "",
			"#del" => "",
			"#visible" => "",
		]);
	}

	public function renderForm()
	{
		$user_id = \diRequest::get("user_id", 0);
		$user_r = !$this->getForm()->getId() && $user_id ? $this->getDb()->r("users", $user_id) : false;

		if ($user_r)
		{
			$this->getForm()->setData("recipient", StringHelper::out($user_r->email));
		}

		$this->getForm()
			->setSelectFromArrayInput("plain_body", Model::$bodyTypes)
			->setHiddenInput("recipient_id");
	}

	public function submitForm()
	{
	}

	public function getFormFields()
	{
		global $mail_domain;

		return [
			"date" => [
				"type" => "datetime_str",
				"title" => "Дата",
				"default" => date("Y-m-d H:i:s"),
				"flags" => ["static"],
			],

			"sender" => [
				"type" => "string",
				"title" => "От",
				"default" => "support@{$mail_domain}",
			],

			"recipient_id" => [
				"type" => "int",
				"title" => "Кому (Логин)",
				"default" => 0,
			],

			"recipient" => [
				"type" => "string",
				"title" => "Кому (E-mail)",
				"default" => "",
			],

			"subject" => [
				"type" => "string",
				"title" => "Тема",
				"default" => "",
			],

			"plain_body" => [
				"type" => "int",
				"title" => "Формат письма",
				"default" => 0,
			],

			"body" => [
				"type" => "wysiwyg",
				"title" => "Тело письма",
				//"default"	=> "<div></div>\n<br />\n---<br />\nВсего наилучшего!",
				"default" => "",
			],

			"attachment" => [
				"type" => "text",
				"title" => "Attachments (serialized)",
				"default" => "",
				"flags" => ["static"],
			],

			"visible" => [
				"type" => "checkbox",
				"title" => "Активно",
				"default" => true,
			],

			"sent" => [
				"type" => "checkbox",
				"title" => "Отослано",
				"default" => false,
				"flags" => ["static"],
			],
		];
	}

	public function getLocalFields()
	{
		return [];
	}

	public function getModuleCaption()
	{
		return "Почтовая очередь";
	}
}