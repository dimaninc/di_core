<?php

namespace diCore\Tests\Tool;

use diCore\Base\Exception\HttpException;
use PHPUnit\Framework\TestCase;

class TestableOAuth2 extends \diOAuth2
{
    const loginUrlBase = 'https://provider.example/authorize';

    protected $vendorId = \diOAuth2Vendors::google;

    public bool $profileRequested = false;

    protected function getBackUrlParts()
    {
        return ['scheme' => 'https', 'host' => 'site.example', 'path' => '/cb/'];
    }

    protected function retrieveProfile()
    {
        $this->profileRequested = true;

        // stop here: what follows is the provider round-trip, not the gate
        throw new \LogicException('reached the provider');
    }
}

/**
 * A callback carrying someone else's `code` used to be accepted, signing the
 * visitor into a foreign account or linking a foreign profile to theirs.
 */
class OAuth2StateTest extends TestCase
{
    private array $get;

    protected function setUp(): void
    {
        $this->get = $_GET;
        // a state left by an earlier test would turn "no login started" into
        // a plain mismatch, and hide a missing `$expected === ''` check
        unset($_SESSION[\diSession::VALUES_SESSION_KEY]);
    }

    protected function tearDown(): void
    {
        $_GET = $this->get;
    }

    private function issuedState(TestableOAuth2 $oauth): string
    {
        parse_str((string) parse_url($oauth->getLoginUrl(), PHP_URL_QUERY), $query);

        $this->assertNotEmpty($query['state'] ?? '');

        return $query['state'];
    }

    public function testCallbackWithoutOurStateIsRejected(): void
    {
        $oauth = new TestableOAuth2();
        $this->issuedState($oauth);
        $_GET = ['code' => 'attacker-code', 'state' => 'forged'];

        try {
            $oauth->processReturn();
            $this->fail('a foreign callback was accepted');
        } catch (HttpException $e) {
            $this->assertSame(400, $e->getCode());
        }

        $this->assertFalse($oauth->profileRequested);
    }

    public function testForgedCallbackDoesNotCancelTheStartedLogin(): void
    {
        $oauth = new TestableOAuth2();
        $state = $this->issuedState($oauth);
        $_GET = ['code' => 'attacker-code', 'state' => 'forged'];

        try {
            $oauth->processReturn();
        } catch (HttpException) {
        }

        $_GET = ['code' => 'own-code', 'state' => $state];

        try {
            $oauth->processReturn();
        } catch (\LogicException) {
        }

        $this->assertTrue($oauth->profileRequested);
    }

    public function testArrayStateIsBadRequestNotWarning(): void
    {
        $oauth = new TestableOAuth2();
        $this->issuedState($oauth);
        $_GET = ['code' => 'attacker-code', 'state' => ['x']];

        // as a project that turns warnings into exceptions (→ 500)
        set_error_handler(static function (int $no, string $message) {
            throw new \ErrorException($message, 0, $no);
        });

        try {
            $oauth->processReturn();
            $this->fail('an array state was accepted');
        } catch (HttpException $e) {
            $this->assertSame(400, $e->getCode());
        } finally {
            restore_error_handler();
        }

        $this->assertFalse($oauth->profileRequested);
    }

    public function testCallbackWithNoLoginStartedIsRejected(): void
    {
        $oauth = new TestableOAuth2();
        $_GET = ['code' => 'attacker-code', 'state' => ''];

        $this->expectException(HttpException::class);

        $oauth->processReturn();
    }

    public function testOwnCallbackPassesOnceOnly(): void
    {
        $oauth = new TestableOAuth2();
        $_GET = ['code' => 'own-code', 'state' => $this->issuedState($oauth)];

        try {
            $oauth->processReturn();
        } catch (\LogicException) {
        }

        $this->assertTrue($oauth->profileRequested);

        $replay = new TestableOAuth2();
        $this->expectException(HttpException::class);

        $replay->processReturn();
    }
}
