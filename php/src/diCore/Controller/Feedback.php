<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 30.09.15
 * Time: 23:10
 */

namespace diCore\Controller;

use diCore\Data\Config;
use diCore\Data\Types;
use diCore\Tool\Mail\Queue;
use diCore\Entity\Feedback\Model;

class Feedback extends \diBaseController
{
	const MODEL_TYPE = Types::feedback;

	protected $sendEmail = true;
	protected $useTwig = true;
	protected $mailBodyTemplateFolder = '`emails/feedback'; //fasttemplate
	protected $mailBodyTemplate = 'emails/feedback/admin'; //twig
	protected $mailSubject = 'Новое сообщение обратной связи';
	protected $instantSend = true;

	/** @var Model */
	private $feedback;

	public function sendAction()
	{
		$ar = [
			'ok' => true,
			'message' => '',
		];

		try {
			$this->gatherData();

			$this->getModel()
				->save();
		} catch (\Exception $e) {
			$ar['ok'] = false;
			$ar['message'] = $e->getMessage();
		}

		if ($ar['ok'] && $this->sendEmail)
		{
			$this->sendEmailNotification();
		}

		return $ar;
	}

	protected function initModel()
	{
		$this->feedback = \diModel::create(static::MODEL_TYPE);

		return $this;
	}

	protected function getModel()
	{
		if (!$this->feedback)
		{
			$this->initModel();
		}

		return $this->feedback;
	}

	protected function gatherData()
	{
		$this->getModel()
			->initFromRequest('post')
			->killId()
			->setIp(ip2bin());

		return $this;
	}

	protected function getSender()
	{
		return \diConfiguration::get('sender_email');
	}

	protected function getRecipientsString()
	{
		return \diConfiguration::get('feedback_email');
	}
	
	protected function getRecipients()
	{
		return preg_split("/[,;\r\n\s]+/", $this->getRecipientsString());
	}

	protected function getMailSubject()
	{
		return $this->mailSubject;
	}

	protected function getMailBody()
	{
		if (!$this->useTwig)
		{
			return $this->initWebTpl()->getTpl()
				->define($this->mailBodyTemplateFolder, [
					'body',
				])
				->assign($this->getModel()->getTemplateVars())
				->parse('body');
		}

		$body = $this->getTwig()
			->parse($this->mailBodyTemplate, [
				'feedback' => $this->getModel(),
				'title' => Config::getSiteTitle(),
				'domain' => Config::getMainDomain(),
			]);

		$html = $this->getTwig()
			->parse('emails/email_html_base', [
				'body' => $body,
				'title' => Config::getSiteTitle(),
				'domain' => Config::getMainDomain(),
			]);

		return $html;
	}

	protected function sendEmail($from, $to, $subj, $body)
	{
		return $this->instantSend
			? Queue::basicCreate()->addAndSend($from, $to, $subj, $body)
			: Queue::basicCreate()->add($from, $to, $subj, $body);
	}

	protected function sendEmailNotification()
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