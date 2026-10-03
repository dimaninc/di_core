<?php

namespace diCore\Tests\Controller;

use diCore\Controller\Payment;
use diCore\Base\Exception\HttpException;
use PHPUnit\Framework\TestCase;

class TestablePayDraftManual extends Payment
{
    public static $cli = false;
    public $adminAuthorized = false;
    public $draftLookups = [];

    public function __construct()
    {
        // No parent constructor: it checks the payment config of a project.
    }

    public static function isCli()
    {
        return static::$cli;
    }

    protected function initAdmin()
    {
        $authorized = $this->adminAuthorized;

        $this->admin = new class ($authorized) {
            public function __construct(private bool $authorized)
            {
            }

            public function authorized()
            {
                return $this->authorized;
            }
        };

        return $this;
    }

    protected function initDraftOnly($draftId)
    {
        $this->draftLookups[] = $draftId;

        // stop here: what follows is the receipt, not the gate under test
        throw new \LogicException('reached the draft');
    }
}

/**
 * The action turns a draft into a paid receipt with no money involved, so only
 * a logged-in admin may call it – an anonymous GET used to be enough.
 */
class PayDraftManualAuthTest extends TestCase
{
    protected function setUp(): void
    {
        TestablePayDraftManual::$cli = false;
    }

    private function controller(bool $adminAuthorized): TestablePayDraftManual
    {
        $c = new TestablePayDraftManual();
        $c->adminAuthorized = $adminAuthorized;
        $c->setParamsAr([42]);

        return $c;
    }

    public function testAnonymousCallIsRefusedBeforeTheDraftIsTouched(): void
    {
        $c = $this->controller(false);

        try {
            $c->payDraftManualAction();
            $this->fail('An anonymous call must be refused');
        } catch (HttpException $e) {
            $this->assertSame(401, $e->getCode());
        }

        $this->assertSame([], $c->draftLookups);
    }

    public function testLoggedInAdminReachesTheDraft(): void
    {
        $c = $this->controller(true);

        $this->expectException(\LogicException::class);

        try {
            $c->payDraftManualAction();
        } finally {
            $this->assertSame([42], $c->draftLookups);
        }
    }
}
