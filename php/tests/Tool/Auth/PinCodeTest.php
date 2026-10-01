<?php

namespace diCore\Tests\Tool\Auth;

use diCore\Entity\AuthorizationPin\Channel;
use diCore\Entity\AuthorizationPin\FailureLog;
use diCore\Entity\AuthorizationPin\Model;
use diCore\Entity\AuthorizationPin\Purpose;
use diCore\Entity\AuthorizationPin\Verdict;
use diCore\Tests\Entity\AuthorizationPinTestDb;
use diCore\Tool\Auth\PinCode;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../Entity/AuthorizationPinTestDb.php';
require_once __DIR__ . '/PinCodeFakes.php';

/**
 * The service around the pin model: delivery, limits in the log, the failed-check
 * budget. Live tests: see AuthorizationPinTestDb.
 */
class PinCodeTest extends TestCase
{
    const IP = '203.0.113.7';
    const TARGET = 'user@example.com';
    const PURPOSE = Purpose::authentication;

    private $server = [];

    public static function setUpBeforeClass(): void
    {
        AuthorizationPinTestDb::open();
    }

    public static function tearDownAfterClass(): void
    {
        AuthorizationPinTestDb::close();
    }

    protected function setUp(): void
    {
        AuthorizationPinTestDb::clear();
        $this->server = $_SERVER;
        $_SERVER['REMOTE_ADDR'] = self::IP;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    public function testClientIpIgnoresForwardedHeaders(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
        $_SERVER['HTTP_CLIENT_IP'] = '198.51.100.2';

        $this->assertSame(self::IP, PinCode::clientIp());
    }

    public function testEmailIsLowercasedAndTrimmedOtherTargetsOnlyTrimmed(): void
    {
        $this->assertSame(
            self::TARGET,
            PinCode::normalizeTarget(Channel::email, " User@Example.COM\n")
        );
        $this->assertSame(
            '+79991234567',
            PinCode::normalizeTarget(Channel::sms, ' +79991234567 ')
        );
        $this->assertSame('AbC', PinCode::normalizeTarget(Channel::telegram, 'AbC'));
    }

    public function testTargetHashIgnoresCaseAndSpaces(): void
    {
        $this->assertSame(
            hash('sha256', self::TARGET),
            PinCode::targetHash(' USER@example.com ')
        );
    }

    public function testDefaultEmailLetterCarriesTheCode(): void
    {
        $deliverer = new CapturingEmailDeliverer();
        $pin = Model::create()->setPurpose(self::PURPOSE)->setTarget(self::TARGET);

        $deliverer->deliver($pin, '012345', [
            'purpose_name' => 'authentication',
            'ttl' => 600,
            'language' => 'en',
            'twig' => \diTwig::create(['cache' => false]),
        ]);

        $this->assertCount(1, $deliverer->mails);
        $mail = $deliverer->mails[0];
        $this->assertSame(self::TARGET, $mail['to']);
        $this->assertSame('012345 is your sign-in code', $mail['subject']);
        $this->assertStringContainsString('012345', $mail['html']);
        $this->assertStringContainsString('10 min', $mail['html']);
        $this->assertStringContainsString(
            '<html>',
            $mail['html'],
            'wrapped into the base template'
        );
    }

    public function testUnknownPurposeAndLanguageFallBack(): void
    {
        $deliverer = new CapturingEmailDeliverer();

        $deliverer->deliver(
            Model::create()->setPurpose(99)->setTarget(self::TARGET),
            '777777',
            [
                'purpose_name' => 'something_else',
                'language' => 'de',
                'twig' => \diTwig::create(['cache' => false]),
            ]
        );

        $this->assertSame(
            'Confirmation code: 777777',
            $deliverer->mails[0]['subject']
        );
        $this->assertStringContainsString('777777', $deliverer->mails[0]['html']);
    }

    public function testLiveSendDeliversToTheNormalizedTarget(): void
    {
        AuthorizationPinTestDb::need($this);

        $service = new TestablePinCode();

        $this->assertTrue(
            $service->send(self::PURPOSE, Channel::email, ' User@Example.com ', [
                'user_id' => 7,
                'context' => ['language' => 'ru'],
            ])
        );

        $this->assertCount(1, $service->deliverer->sent);
        [$pin, $code, $context] = $service->deliverer->sent[0];
        $this->assertSame(
            self::TARGET,
            Model::createById($pin->getId())->getTarget()
        );
        $this->assertSame(self::IP, Model::createById($pin->getId())->getIp());
        $this->assertEquals(7, $pin->getUserId());
        $this->assertSame('authentication', $context['purpose_name']);
        $this->assertSame(Model::CODE_TTL, $context['ttl']);
        $this->assertSame('ru', $context['language']);

        $this->assertTrue(
            $service->check(self::PURPOSE, 'USER@example.com', $code)->isOk()
        );
    }

    public function testLiveRateLimitSendsNothingAndLogsNoAddress(): void
    {
        AuthorizationPinTestDb::need($this);

        $service = new TestablePinCode();

        for ($i = 0; $i < Model::MAX_PER_TARGET; $i++) {
            $this->assertTrue(
                $service->send(self::PURPOSE, Channel::email, self::TARGET)
            );
        }

        $this->assertFalse(
            $service->send(self::PURPOSE, Channel::email, self::TARGET)
        );
        $this->assertCount(Model::MAX_PER_TARGET, $service->deliverer->sent);
        $this->assertCount(1, $service->lines);
        $this->assertSame(
            'rate limit: limit=target purpose=authentication target_sha256=' .
                PinCode::targetHash(self::TARGET) .
                ' ip=' .
                self::IP,
            $service->lines[0]
        );
        $this->assertStringNotContainsString('example.com', $service->lines[0]);
    }

    public function testLiveChannelWithoutDelivererStoresNothing(): void
    {
        AuthorizationPinTestDb::need($this);

        try {
            (new TestablePinCode())->send(
                self::PURPOSE,
                Channel::call,
                '+79991234567'
            );
            $this->fail('No exception for a channel without a deliverer');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('call', $e->getMessage());
        }

        $this->assertSame(0, AuthorizationPinTestDb::count('authorization_pin'));
    }

    public function testLiveMalformedCodeSpendsNothing(): void
    {
        AuthorizationPinTestDb::need($this);

        $service = new TestablePinCode();
        $service->send(self::PURPOSE, Channel::email, self::TARGET);

        foreach (['', '12', 'abcdef', '1234567'] as $junk) {
            $result = $service->check(self::PURPOSE, self::TARGET, $junk);
            $this->assertSame(Verdict::malformed, $result->getVerdict());
            $this->assertFalse($result->getPin()->exists());
        }

        $this->assertSame(0, AuthorizationPinTestDb::count(FailureLog::TABLE));
        $this->assertSame(
            0,
            AuthorizationPinTestDb::count('authorization_pin', 'attempts > 0')
        );
    }

    public function testLiveBlockedCheckBurnsNoAttemptAndIsNotRecorded(): void
    {
        AuthorizationPinTestDb::need($this);

        $service = new TestablePinCode();
        $service->send(self::PURPOSE, Channel::email, self::TARGET);
        $code = $service->deliverer->sent[0][1];

        for ($i = 0; $i < FailureLog::MAX_FAILURES; $i++) {
            FailureLog::record(self::IP, self::PURPOSE, self::TARGET);
        }

        $result = $service->check(self::PURPOSE, self::TARGET, $code);

        $this->assertSame(Verdict::blocked, $result->getVerdict());
        $this->assertSame(
            0,
            AuthorizationPinTestDb::count('authorization_pin', 'attempts > 0')
        );
        $this->assertSame(
            FailureLog::MAX_FAILURES,
            AuthorizationPinTestDb::count(FailureLog::TABLE)
        );
        $this->assertStringContainsString('limit=failures', end($service->lines));

        // Another ip has its own budget and gets in.
        $this->assertTrue(
            $service
                ->check(self::PURPOSE, self::TARGET, $code, ['ip' => '203.0.113.99'])
                ->isOk()
        );
    }

    public function testLiveEveryRefusalIsRecordedAndSuccessIsNot(): void
    {
        AuthorizationPinTestDb::need($this);

        $service = new TestablePinCode();

        $this->assertSame(
            Verdict::missing,
            $service
                ->check(self::PURPOSE, 'nobody@example.com', '123456')
                ->getVerdict()
        );

        $service->send(self::PURPOSE, Channel::email, self::TARGET, [
            'user_id' => 7,
        ]);
        $code = $service->deliverer->sent[0][1];
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->assertSame(
            Verdict::mismatch,
            $service
                ->check(self::PURPOSE, self::TARGET, $wrong, ['user_id' => 7])
                ->getVerdict()
        );
        $this->assertTrue(
            $service->check(self::PURPOSE, self::TARGET, $code)->isOk()
        );

        $this->assertSame(2, AuthorizationPinTestDb::count(FailureLog::TABLE));
        $this->assertSame(
            1,
            AuthorizationPinTestDb::count(FailureLog::TABLE, 'user_id = 7')
        );
    }
}
