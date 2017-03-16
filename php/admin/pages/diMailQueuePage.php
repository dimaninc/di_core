<?php

class diMailQueuePage extends diAdminBasePage
{
	protected $options = array(
		"filters" => array(
			"defaultSorter" => array(
				"sortBy" => "id",
				"dir" => "DESC",
			),
		),
	);

	protected function initTable()
	{
		$this->setTable("mail_queue");
	}

	public function renderList()
	{
	    $this->getTpl()->define("`mail_queue/list", array(
	    	"before_table",
	    ))->assign(array(
	    	"TOTAL_RECORDS" => $this->getPagesNavy()->getTotalRecords(),
		    "VISIBLE_WORKER_URI" => diLib::getAdminWorkerPath("mail", "set_visible")."?back=".urlencode($_SERVER["REQUEST_URI"]),
		    "SEND_ALL_WORKER_URI" => diLib::getAdminWorkerPath("mail", "send_all"),
	    ));

		$this->getList()->addColumns(array(
			"id" => "ID",
			"sender" => array(
				"title" => "Отправитель",
				"attrs" => array(
					"width" => "20%",
				),
			),
			"recipient" => array(
				"title" => "Получатель",
				"attrs" => array(
					"width" => "30%",
				),
			),
			"subject" => array(
				"title" => "Тема",
				"attrs" => array(
					"width" => "40%",
				),
			),
			"date" => array(
				"title" => "Дата",
				"value" => function(diMailQueueModel $m) {
					return date("d.m.Y H:i", strtotime($m->getDate()));
				},
				"attrs" => array(
					"width" => "10%",
				),
				"headAttrs" => array(),
				"bodyAttrs" => array(
					"class" => "dt",
				),
			),
			"#edit" => "",
			"#del" => "",
			"#visible" => "",
		));
	}

	public function renderForm()
	{
		global $plain_body_ar;

		$user_id = diRequest::get("user_id", 0);
		$user_r = !$this->getForm()->getId() && $user_id ? $this->getDb()->r("users", $user_id) : false;

		if ($user_r)
		{
			$this->getForm()->setData("recipient", str_out($user_r->email));
		}

		$this->getForm()
			->setSelectFromArrayInput("plain_body", $plain_body_ar)
			->setHiddenInput("recipient_id");
	}

	public function submitForm()
	{
	}

	public function getFormFields()
	{
		global $mail_domain;

		return array(
			"date" => array(
				"type"		=> "datetime_str",
				"title"		=> "Дата",
				"default"	=> date("Y-m-d H:i:s"),
				"flags"		=> array("static"),
			),

			"sender" => array(
				"type"		=> "string",
				"title"		=> "От",
				"default"	=> "support@{$mail_domain}",
			),

			"recipient_id" => array(
				"type"		=> "int",
				"title"		=> "Кому (Логин)",
				"default"	=> 0,
			),

			"recipient" => array(
				"type"		=> "string",
				"title"		=> "Кому (E-mail)",
				"default"	=> "",
			),

			"subject" => array(
				"type"		=> "string",
				"title"		=> "Тема",
				"default"	=> "",
			),

			"plain_body" => array(
				"type"		=> "int",
				"title"		=> "Формат письма",
				"default"	=> 0,
			),

			"body" => array(
				"type"		=> "wysiwyg",
				"title"		=> "Тело письма",
				//"default"	=> "<div></div>\n<br />\n---<br />\nВсего наилучшего!",
				"default"	=> "",
			),

			"visible" => array(
				"type"		=> "checkbox",
				"title"		=> "Активно",
				"default"	=> true,
			),

			"sent" => array(
				"type"		=> "checkbox",
				"title"		=> "Отослано",
				"default"	=> false,
				"flags"		=> array("static"),
			),
		);
	}

	public function getLocalFields()
	{
		return array();
	}

	public function getModuleCaption()
	{
		return "Почтовая очередь";
	}
}