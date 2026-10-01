<?php

namespace diCore\Tests\Tool\Auth;

use diCore\Entity\AuthorizationPin\Channel;
use diCore\Tool\Auth\PinCode;
use diCore\Tool\Auth\PinDeliverer;
use diCore\Tool\Auth\PinEmailDeliverer;

class FakePinDeliverer implements PinDeliverer
{
    public $sent = [];

    public function deliver(\diModel $pin, string $plainValue, array $context): void
    {
        $this->sent[] = [$pin, $plainValue, $context];
    }
}

class TestablePinCode extends PinCode
{
    /** @var FakePinDeliverer */
    public $deliverer;
    public $lines = [];

    public function __construct()
    {
        $this->deliverer = new FakePinDeliverer();
    }

    protected function getDeliverer(int $channel): PinDeliverer
    {
        // Email and sms go to the fake; any other channel keeps the core behaviour.
        return in_array($channel, [Channel::email, Channel::sms], true)
            ? $this->deliverer
            : parent::getDeliverer($channel);
    }

    protected function log(string $line): void
    {
        $this->lines[] = $line;
    }
}

class CapturingEmailDeliverer extends PinEmailDeliverer
{
    public $mails = [];

    protected function getSender(\diModel $pin, array $context)
    {
        return 'robot@example.com';
    }

    protected function queue(
        $from,
        string $to,
        string $subject,
        string $html,
        array $context
    ): void {
        $this->mails[] = compact('from', 'to', 'subject', 'html');
    }
}
