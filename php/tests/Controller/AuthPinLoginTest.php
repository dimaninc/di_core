<?php

namespace diCore\Tests\Controller;

use diCore\Controller\Auth;
use diCore\Entity\AuthorizationPin\Channel;
use diCore\Entity\AuthorizationPin\FailureLog;
use diCore\Entity\AuthorizationPin\Model as Pin;
use diCore\Entity\AuthorizationPin\Purpose;
use diCore\Tests\Entity\AuthorizationPinTestDb;
use diCore\Tests\Tool\Auth\TestablePinCode;
use diCore\Tool\Auth\PinCode;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Entity/AuthorizationPinTestDb.php';
require_once __DIR__ . '/../Tool/Auth/PinCodeFakes.php';

/**
 * Every seam that needs a project (users table, session, CSRF context) is replaced;
 * what is left is the order of the checks and the shape of the answers.
 */
class TestablePinAuth extends Auth
{
    public $enabled = true;
    public $safe = true;
    public $sessionWorks = true;
    /** @var \diModel[] by email */
    public $users = [];
    public $lookups = 0;
    public $calls = [];
    public $deliveryFailures = [];
    /** @var TestablePinCode */
    public $service;

    protected static $customLanguage = [
        'en' => ['pin.signed_in' => 'Welcome'],
        'ru' => [],
    ];

    public function __construct()
    {
        // No parent constructor: it starts a session and builds the auth tool.
        $this->service = new TestablePinCode();
    }

    protected function isPinLoginEnabled(): bool
    {
        return $this->enabled;
    }

    protected function isPinRequestSafe(): bool
    {
        return $this->safe;
    }

    protected function pinService(): PinCode
    {
        return $this->service;
    }

    protected function findUserForPinLogin(string $email): \diModel
    {
        $this->lookups++;

        return $this->users[$email] ?? \diModel::create(\diTypes::user);
    }

    protected function beforePinAuthorize(\diModel $user): void
    {
        $this->calls[] = 'before';
    }

    protected function authorizeByPin(\diModel $user): bool
    {
        $this->calls[] = 'authorize:' . $user->getId();

        return $this->sessionWorks;
    }

    protected function afterPinAuthorize(\diModel $user, array &$response): void
    {
        $this->calls[] = 'after';
        $response['extra'] = true;
    }

    protected function onPinDeliveryFailure(\Exception $e): void
    {
        $this->deliveryFailures[] = $e->getMessage();
    }
}

class ThrowingPinCode extends TestablePinCode
{
    public function send(
        int $purpose,
        int $channel,
        string $target,
        array $options = []
    ): bool {
        throw new \RuntimeException('smtp is down');
    }
}

class AuthPinLoginTest extends TestCase
{
    const IP = '203.0.113.7';
    const EMAIL = 'pintest@example.com';
    const PURPOSE = Purpose::authentication;

    private $server = [];
    private $post = [];

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
        $this->post = $_POST;
        $_SERVER['REMOTE_ADDR'] = self::IP;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_POST = $this->post;
    }

    public function testReasonsAndStringsAreFixed(): void
    {
        $this->assertSame(
            [
                'pin.refused' => 'code',
                'pin.too_many' => 'limit',
                'pin.session_failed' => 'session',
                'pin.unavailable' => 'unavailable',
                'pin.rejected' => 'rejected',
                'pin.invalid_email' => 'email',
            ],
            Auth::PIN_REASONS
        );

        foreach (
            array_merge(array_keys(Auth::PIN_REASONS), ['pin.sent', 'pin.signed_in'])
            as $key
        ) {
            $this->assertNotSame($key, Auth::L($key, 'ru'), $key);
            $this->assertNotSame($key, Auth::L($key, 'en'), $key);
        }
    }

    public function testStringsFallBackToEnglishAndSubclassOverridesOneString(): void
    {
        $this->assertSame(
            Auth::L('pin.refused', 'en'),
            Auth::L('pin.refused', 'de')
        );
        $this->assertSame('no.such.key', Auth::L('no.such.key', 'de'));

        $this->assertSame('Welcome', TestablePinAuth::L('pin.signed_in', 'en'));
        $this->assertSame(
            Auth::L('pin.refused', 'en'),
            TestablePinAuth::L('pin.refused', 'en')
        );
        $this->assertSame(
            Auth::L('sign_in.unsuccessful', 'ru'),
            TestablePinAuth::L('sign_in.unsuccessful', 'ru')
        );
    }

    /**
     * The router tries `_post<Action>` first, then `<action>Action` for any method:
     * a second name would open the action on GET.
     */
    public function testPinActionsAnswerOnlyToPost(): void
    {
        $this->assertTrue(method_exists(Auth::class, '_postPinSendAction'));
        $this->assertTrue(method_exists(Auth::class, '_postPinLoginAction'));
        $this->assertFalse(method_exists(Auth::class, 'pinSendAction'));
        $this->assertFalse(method_exists(Auth::class, 'pinLoginAction'));
    }

    public function testDisabledAndForeignRequestsAreRefusedBeforeAnything(): void
    {
        $c = $this->controller();
        $c->enabled = false;

        $this->assertSame(
            $this->failure('pin.unavailable'),
            $this->send($c, self::EMAIL)
        );
        $this->assertSame(
            $this->failure('pin.unavailable'),
            $this->login($c, self::EMAIL, '123456')
        );

        $c->enabled = true;
        $c->safe = false;

        $this->assertSame(
            $this->failure('pin.rejected'),
            $this->send($c, self::EMAIL)
        );
        $this->assertSame(
            $this->failure('pin.rejected'),
            $this->login($c, self::EMAIL, '123456')
        );

        $this->assertSame(0, $c->lookups);
        $this->assertCount(0, $c->service->deliverer->sent);
    }

    public function testInvalidAddressIsRefusedOnSend(): void
    {
        $c = $this->controller();

        $this->assertSame(
            $this->failure('pin.invalid_email'),
            $this->send($c, 'not-an-email')
        );
        $this->assertSame(0, $c->lookups);
    }

    public function testLiveRightCodeSignsIn(): void
    {
        AuthorizationPinTestDb::need($this);

        $c = $this->controller();
        $c->users[self::EMAIL] = $this->user(5);

        $sent = $this->send($c, ' PinTest@Example.com ');
        $this->assertSame(['ok' => true, 'message' => Auth::L('pin.sent')], $sent);
        $this->assertCount(1, $c->service->deliverer->sent);

        $res = $this->login($c, strtoupper(self::EMAIL), $this->code($c));

        $this->assertSame(
            [
                'ok' => true,
                'message' => TestablePinAuth::L('pin.signed_in'),
                'extra' => true,
            ],
            $res
        );
        $this->assertSame(['before', 'authorize:5', 'after'], $c->calls);
        $this->assertArrayNotHasKey('reason', $res);
    }

    public function testLiveUnknownOrNotAllowedAddressGetsTheSameAnswerAndNoLetter(): void
    {
        AuthorizationPinTestDb::need($this);

        $c = $this->controller();
        $c->users['disabled@example.com'] = $this->user(6, 0);
        $expected = ['ok' => true, 'message' => Auth::L('pin.sent')];

        $this->assertSame($expected, $this->send($c, 'nobody@example.com'));
        $this->assertSame($expected, $this->send($c, 'disabled@example.com'));
        $this->assertCount(0, $c->service->deliverer->sent);
    }

    public function testLiveRateLimitedSendLooksLikeSuccess(): void
    {
        AuthorizationPinTestDb::need($this);

        $c = $this->controller();
        $c->users[self::EMAIL] = $this->user(5);

        for ($i = 0; $i <= Pin::MAX_PER_TARGET; $i++) {
            $this->assertSame(
                ['ok' => true, 'message' => Auth::L('pin.sent')],
                $this->send($c, self::EMAIL)
            );
        }

        $this->assertCount(Pin::MAX_PER_TARGET, $c->service->deliverer->sent);
    }

    public function testLiveDeliveryFailureKeepsTheAnswer(): void
    {
        AuthorizationPinTestDb::need($this);

        $c = $this->controller();
        $c->service = new ThrowingPinCode();
        $c->users[self::EMAIL] = $this->user(5);

        $this->assertSame(
            ['ok' => true, 'message' => Auth::L('pin.sent')],
            $this->send($c, self::EMAIL)
        );
        $this->assertSame(['smtp is down'], $c->deliveryFailures);
    }

    public function testLiveNotAllowedUserIsRefusedWithoutTouchingTheCode(): void
    {
        AuthorizationPinTestDb::need($this);

        $c = $this->controller();
        $c->users[self::EMAIL] = $this->user(6, 0);
        // Issued while the account was still active.
        [, $code] = Pin::issueCode(self::PURPOSE, Channel::email, self::EMAIL, [
            'user_id' => 6,
        ]);

        $this->assertSame(
            $this->failure('pin.refused'),
            $this->login($c, self::EMAIL, $code)
        );
        $this->assertSame(
            0,
            AuthorizationPinTestDb::count('authorization_pin', 'attempts > 0')
        );
        $this->assertSame(
            1,
            AuthorizationPinTestDb::count(FailureLog::TABLE, 'user_id = 6')
        );
        $this->assertSame([], $c->calls);
    }

    /** Different texts would tell whether an address exists and has a live code. */
    public function testLiveEveryCodeRefusalLooksTheSame(): void
    {
        AuthorizationPinTestDb::need($this);

        $c = $this->controller();
        $c->users[self::EMAIL] = $this->user(5);
        $results = [];

        $results['unknown address'] = $this->login(
            $c,
            'nobody@example.com',
            '123456'
        );
        $results['no code issued'] = $this->login($c, self::EMAIL, '123456');

        $this->send($c, self::EMAIL);
        $code = $this->code($c);
        $wrong = $code === '000000' ? '111111' : '000000';
        $results['malformed code'] = $this->login($c, self::EMAIL, 'abc');
        $results['wrong code'] = $this->login($c, self::EMAIL, $wrong);
        $this->login($c, self::EMAIL, $wrong);
        $results['attempts exhausted'] = $this->login($c, self::EMAIL, $wrong);

        $this->send($c, self::EMAIL);
        AuthorizationPinTestDb::db()->q(
            "UPDATE authorization_pin SET expired_at = '" .
                \diDateTime::sqlFormat('-1 minute') .
                "'"
        );
        $results['expired code'] = $this->login($c, self::EMAIL, $this->code($c));

        foreach ($results as $case => $result) {
            $this->assertSame($this->failure('pin.refused'), $result, $case);
        }

        // Every refusal is recorded except the malformed one, plus the second wrong code.
        $this->assertSame(
            count($results),
            AuthorizationPinTestDb::count(FailureLog::TABLE)
        );
        $this->assertSame([], $c->calls);
    }

    /**
     * A spent budget refuses BEFORE the lookup and the check: no attempt is burnt and
     * the refusal is not recorded, otherwise the window would never end.
     */
    public function testLiveFailureBudgetRefusesBeforeLookupAndAttempt(): void
    {
        AuthorizationPinTestDb::need($this);

        $c = $this->controller();
        $c->users[self::EMAIL] = $this->user(5);

        for ($i = 0; $i < FailureLog::MAX_FAILURES; $i++) {
            $this->assertSame(
                $this->failure('pin.refused'),
                $this->login($c, self::EMAIL, '123456')
            );
        }

        $this->send($c, self::EMAIL);
        $lookups = $c->lookups;

        $this->assertSame(
            $this->failure('pin.too_many'),
            $this->login($c, self::EMAIL, $this->code($c))
        );
        $this->assertSame($lookups, $c->lookups, 'no user lookup');
        $this->assertSame(
            0,
            AuthorizationPinTestDb::count('authorization_pin', 'attempts > 0')
        );
        $this->assertSame(
            FailureLog::MAX_FAILURES,
            AuthorizationPinTestDb::count(FailureLog::TABLE)
        );

        // The unknown address hits the limit the same way.
        for ($i = 0; $i < FailureLog::MAX_FAILURES; $i++) {
            $this->login($c, 'nobody@example.com', '123456');
        }

        $this->assertSame(
            $this->failure('pin.too_many'),
            $this->login($c, 'nobody@example.com', '123456')
        );

        $_SERVER['REMOTE_ADDR'] = '203.0.113.99';
        $this->assertTrue(
            $this->login($c, self::EMAIL, $this->code($c))['ok'],
            'another ip'
        );
    }

    public function testLiveJunkSpendsNoBudget(): void
    {
        AuthorizationPinTestDb::need($this);

        $c = $this->controller();

        for ($i = 0; $i < FailureLog::MAX_FAILURES; $i++) {
            $this->assertSame(
                $this->failure('pin.refused'),
                $this->login($c, 'not-an-email', '123456')
            );
            $this->assertSame(
                $this->failure('pin.refused'),
                $this->login($c, self::EMAIL, '12')
            );
            $this->assertSame(
                $this->failure('pin.refused'),
                $this->login($c, self::EMAIL, '')
            );
        }

        $this->assertSame(0, AuthorizationPinTestDb::count(FailureLog::TABLE));
        $this->assertSame(0, $c->lookups);
    }

    /** The code is consumed before the session: a failed session costs the code, not a second login. */
    public function testLiveSessionFailureHasItsOwnTextAndTheCodeIsGone(): void
    {
        AuthorizationPinTestDb::need($this);

        $c = $this->controller();
        $c->users[self::EMAIL] = $this->user(5);
        $c->sessionWorks = false;
        $this->send($c, self::EMAIL);
        $code = $this->code($c);

        $this->assertSame(
            $this->failure('pin.session_failed'),
            $this->login($c, self::EMAIL, $code)
        );
        $this->assertNotSame(Auth::L('pin.refused'), Auth::L('pin.session_failed'));

        $c->sessionWorks = true;
        $this->assertSame(
            $this->failure('pin.refused'),
            $this->login($c, self::EMAIL, $code)
        );
    }

    private function controller(): TestablePinAuth
    {
        return new TestablePinAuth();
    }

    private function user(int $id, int $active = 1): \diModel
    {
        return \diModel::create(\diTypes::user, [
            'id' => $id,
            'email' => self::EMAIL,
            'active' => $active,
        ]);
    }

    private function send(TestablePinAuth $c, string $email): array
    {
        $_POST = ['email' => $email];

        return $c->_postPinSendAction();
    }

    private function login(TestablePinAuth $c, string $email, string $code): array
    {
        $_POST = ['email' => $email, 'pin' => $code];

        return $c->_postPinLoginAction();
    }

    private function code(TestablePinAuth $c): string
    {
        $sent = $c->service->deliverer->sent;

        return (string) end($sent)[1];
    }

    private function failure(string $key): array
    {
        return [
            'ok' => false,
            'message' => Auth::L($key),
            'reason' => Auth::PIN_REASONS[$key],
        ];
    }
}
