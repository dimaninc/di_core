<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 30.09.15
 * Time: 23:10
 */

use diCore\Tool\Mail\Queue;

class diFeedbackController extends diBaseController
{
	protected $sendEmail = true;
	protected $mailBodyTemplateFolder = "`emails/feedback";
	protected $mailSubject = "Новое сообщение обратной связи";

	/** @var  diFeedbackModel */
	private $feedback;

	public function sendAction()
	{
		$ar = [
			"ok" => true,
			"message" => "",
		];

		$this->feedback = diModel::create(diTypes::feedback);

		$this->gatherData();

		try {
			$this->feedback->save();
		} catch (Exception $e) {
			$ar["ok"] = false;
			$ar["message"] = $e->getMessage();
		}

		if ($ar["ok"] && $this->sendEmail)
		{
			$this->sendEmailNotification();
		}

		$this->defaultResponse($ar);
	}

	protected function getModel()
	{
		return $this->feedback;
	}

	protected function gatherData()
	{
		$this->feedback
			->initFromRequest("post")
			->killId()
			->setIp(ip2bin());

		return $this;
	}

	protected function getSender()
	{
		return diConfiguration::get("sender_email");
	}

	protected function getRecipients()
	{
		return preg_split("/[,;\r\n\s]+/", diConfiguration::get("feedback_email"));
	}

	protected function getMailSubject()
	{
		return $this->mailSubject;
	}

	protected function getMailBody()
	{
		$body = $this
			->initWebTpl()
			->getTpl()
				->define($this->mailBodyTemplateFolder, [
					"body",
				])
				->assign($this->feedback->getTemplateVars())
				->parse("body");

		return $body;
	}

	protected function sendEmail($from, $to, $subj, $body)
	{
		return Queue::basicCreate()->addAndSend($from, $to, $subj, $body);
	}

	private function sendEmailNotification()
	{
		foreach ($this->getRecipients() as $recipient)
		{
			if ($recipient = trim($recipient))
			{
				$this->sendEmail($this->getSender(), $recipient, $this->getMailSubject(), $this->getMailBody());
			}
		}

		return $this;
	}
}