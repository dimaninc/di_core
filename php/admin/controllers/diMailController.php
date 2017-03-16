<?php

class diMailController extends diBaseAdminController
{
	private $sendPerAttempt = 1000;

	public function sendAllAction()
	{
		$mq = diMailQueue::create();

		$cc = $mq->send_all_safe($this->sendPerAttempt);

		echo "$cc email(s) sent";

		//$this->redirect();
	}
	public function setVisibleAction()
	{
		$mq = diMailQueue::create();
		$mq->setVisible();

		$this->redirect();
	}
}
