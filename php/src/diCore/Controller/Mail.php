<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.08.2017
 * Time: 10:57
 */

namespace diCore\Controller;

use diCore\Data\Types;
use diCore\Entity\MailPlan\Collection;
use diCore\Entity\MailPlan\Model;
use diCore\Tool\Mail\Queue;

class Mail extends \diBaseAdminController
{
	private $sendPerAttempt = 1000;

	public function sendAllAction()
	{
		$mq = Queue::basicCreate();

		$cc = $mq->sendAllSafe($this->sendPerAttempt);

		return "$cc email(s) sent";
	}

	public function setVisibleAction()
	{
		$mq = Queue::basicCreate();
		$mq->setVisible();

		$this->redirect();
	}

	public function processPlanAction()
	{
		/** @var Collection $plans */
		$plans = \diCollection::create(Types::mail_plan);
		$plans
			->filterByProcessedAt(null, '=');

		/** @var Model $plan */
		foreach ($plans as $plan)
		{
			$plan->process();
		}

		return [
			'processed' => $plans->count(),
		];
	}
}