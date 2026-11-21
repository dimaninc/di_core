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
        RotationSpyAuth::$rotations = [];
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
}
