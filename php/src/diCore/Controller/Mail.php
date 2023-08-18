<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.08.2017
 * Time: 10:57
 */

namespace diCore\Controller;

use diCore\Entity\MailPlan\Collection;
use diCore\Entity\MailPlan\Model;
use diCore\Tool\Logger;
use diCore\Tool\Mail\Queue;

class Mail extends \diBaseAdminController
{
    protected $sendPerAttempt = 1000;

    public function sendAllAction()
    {
        $mq = Queue::basicCreate();

        $cc = $mq->sendAllSafe($this->sendPerAttempt);

        return "$cc email(s) sent";
    }

    public function sendAction()
    {
        $mq = Queue::basicCreate();
        $id = $this->param(0, 0);

        $mq->send($id);

        $this->redirect();
    }

    public function setVisibleAction()
    {
        $mq = Queue::basicCreate();
        $mq->setVisible();

        $this->redirect();
    }

    public function processPlanAction()
    {
        $plans = Collection::create()->filterByStartedAt(null, '=');

        $sent = 0;

        /** @var Model $plan */
        foreach ($plans as $plan) {
            $plan->process();
            $sent += $plan->getSentMailsCount();

            Logger::getInstance()->log(
                $plan->getSentMailsCount() .
                    ' emails sent for plan#' .
                    $plan->getId(),
                'mail::processPlanAction'
            );
        }

        return [
            'processed' => $plans->count(),
            'sent' => $sent,
        ];
    }
}
