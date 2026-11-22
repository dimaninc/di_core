<?php

namespace diCore\Tests\Tool;

use diCore\Tool\Auth;
use PHPUnit\Framework\TestCase;

/** An active user whose password or cookie secret matches; nothing reaches the DB. */
class RotationUser extends \diCore\Entity\User\Model
{
    public function isPasswordOk($password, $source = 'raw', $field = 'password')
    {
        return true;
    }

    public function upgradePasswordHash($rawPassword, $field = 'password')
    {
        return $this;
    }

    public function save()
    {
        return $this;
    }
}

class RotationSpyAuth extends Auth
{
    /** user id held by the session at the moment of each rotation */
    public static array $rotations = [];

    protected static function fetchUserModel($id)
    {
        // only a real id or login: an absent cookie asks for id 0
        if ($id) {
            return (new RotationUser())->setId(7)->set('active', 1);
        }

        return \diModel::create(static::USER_MODEL_TYPE);
    }

    protected function rotateSessionId(): void
    {
        static::$rotations[] = $_SESSION[static::SESSION_USER_ID_FIELD] ?? null;
    }

    protected function storeCookies($remember = false)
    {
        return $this;
    }

    protected function userSessionCreationNeeded()
    {
        return false;
    }
}

/** Stands in for diSession: PHPUnit has sent output, no real id can rotate. */
class FakeRotatingSession extends \diSession
{
    public static bool $fromClient = true;
    public static bool $regenerates = true;
    public static string $id = 'planted';
    public static int $regenerated = 0;

    public static function idFromClient(): bool
    {
        return static::$fromClient;
    }

    public static function id()
    {
        return static::$id;
    }

    public static function regenerate(): bool
    {
        static::$regenerated++;

        if (static::$regenerates) {
            static::$id = 'fresh';
        }

        return static::$regenerates;
    }
}

/** The real rotateSessionId() over the fake session. */
class RotationBodyAuth extends Auth
{
    public static array $changed = [];
    public static bool $hookThrows = false;

    protected function sessionClass(): string
    {
        return FakeRotatingSession::class;
    }

    protected function onSessionIdChanged(string $oldId, string $newId): void
    {
        static::$changed[] = [$oldId, $newId];

        if (static::$hookThrows) {
            throw new \RuntimeException('no table');
        }
    }

    protected function storeCookies($remember = false)
    {
        return $this;
    }

    protected function userSessionCreationNeeded()
    {
        return false;
    }

    public function rotate(): void
    {
        $this->rotateSessionId();
    }
}

class NoPhpSessionAuth extends RotationBodyAuth
{
    const USE_PHP_SESSION = false;
}

/**
 * Only the POST password sign-in rotated the id: a planted id S became the
 * victim's session as soon as a "remember me" cookie signed them in under it.
 */
class SessionRotationOnSignInTest extends TestCase
{
    private array $post;
    private array $cookie;

    protected function setUp(): void
    {
        $this->post = $_POST;
        $this->cookie = $_COOKIE;
        $_POST = [];
        $_COOKIE = [];
        $_SESSION = [];
        // ip2bin() of the user session code is fed REMOTE_ADDR
        $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
        RotationSpyAuth::$rotations = [];
        RotationBodyAuth::$changed = [];
        RotationBodyAuth::$hookThrows = false;
        FakeRotatingSession::$fromClient = true;
        FakeRotatingSession::$regenerates = true;
        FakeRotatingSession::$id = 'planted';
        FakeRotatingSession::$regenerated = 0;
    }

    protected function tearDown(): void
    {
        $_POST = $this->post;
        $_COOKIE = $this->cookie;
        $_SESSION = [];
    }

    public function testPostSignInRotatesBeforeTheSessionGetsTheUser(): void
    {
        $_POST = [
            Auth::POST_LOGIN_FIELD => 'someone@example.com',
            Auth::POST_PASSWORD_FIELD => 'right',
        ];

        $auth = new RotationSpyAuth(false);

        $this->assertTrue($auth->authorized());
        // null: the old id must not be written with the user before it dies
        $this->assertSame([null], RotationSpyAuth::$rotations);
        $this->assertSame(7, $_SESSION[Auth::SESSION_USER_ID_FIELD]);
    }

    public function testCookieSignInRotates(): void
    {
        $_COOKIE = [
            Auth::COOKIE_USER_ID => '7',
            Auth::COOKIE_SECRET => 'secret',
        ];

        $auth = new RotationSpyAuth(false);

        $this->assertTrue($auth->authorized());
        $this->assertSame([null], RotationSpyAuth::$rotations);
    }

    public function testForceAuthorizeRotates(): void
    {
        $auth = new RotationSpyAuth(false);
        $this->assertSame([], RotationSpyAuth::$rotations);

        $auth->forceAuthorize((new RotationUser())->setId(7)->set('active', 1));

        $this->assertSame([null], RotationSpyAuth::$rotations);
    }

    public function testSessionSignInDoesNotRotate(): void
    {
        // the session itself already holds the user: rotating on every
        // request would only break parallel requests
        $_SESSION[Auth::SESSION_USER_ID_FIELD] = 7;

        new RotationSpyAuth(false);

        $this->assertSame([], RotationSpyAuth::$rotations);
    }

    public function testRotationHandsTheOldIdOver(): void
    {
        (new RotationBodyAuth(false))->rotate();

        $this->assertSame(1, FakeRotatingSession::$regenerated);
        $this->assertSame([['planted', 'fresh']], RotationBodyAuth::$changed);
    }

    public function testIdPhpGeneratedIsNotRotated(): void
    {
        FakeRotatingSession::$fromClient = false;

        (new RotationBodyAuth(false))->rotate();

        $this->assertSame(0, FakeRotatingSession::$regenerated);
        $this->assertSame([], RotationBodyAuth::$changed);
    }

    public function testWithoutPhpSessionNothingRotates(): void
    {
        (new NoPhpSessionAuth(false))->rotate();

        $this->assertSame(0, FakeRotatingSession::$regenerated);
    }

    public function testFailedRegenerateMovesNothing(): void
    {
        FakeRotatingSession::$regenerates = false;

        (new RotationBodyAuth(false))->rotate();

        $this->assertSame([], RotationBodyAuth::$changed);
    }

    public function testFailingHookNeverCostsTheSignIn(): void
    {
        RotationBodyAuth::$hookThrows = true;

        (new RotationBodyAuth(false))->rotate();

        $this->assertCount(1, RotationBodyAuth::$changed);
    }

    /** An app ignoring the new X-Session rotated (and lost its cart) per request. */
    public function testTokenSignInStaysOutOfThePhpSession(): void
    {
        $auth = new RotationBodyAuth(false);
        $user = (new RotationUser())->setId(7)->set('active', 1);

        // authorize() needs a user_session row; its storing step is what matters
        // Auth's private state: bound to Auth's scope, not the subclass's
        \Closure::bind(function () use ($user) {
            $this->user = $user;
            $this->authSource = Auth::SOURCE_USER_SESSION;
            $this->storeSession();
        }, $auth, Auth::class)();

        $this->assertArrayNotHasKey(Auth::SESSION_USER_ID_FIELD, $_SESSION);

        // a sign-in forced during a token request is still kept
        $auth->forceAuthorize($user);

        $this->assertSame(7, $_SESSION[Auth::SESSION_USER_ID_FIELD]);
    }
}
