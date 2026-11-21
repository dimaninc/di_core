<?php

namespace diCore\Tests\Tool;

use diCore\Tool\Auth;
use PHPUnit\Framework\TestCase;

/** An active user whose password matches; nothing reaches the DB. */
class PasswordOkUser extends \diCore\Entity\User\Model
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

class TestablePasswordAuth extends Auth
{
    public static bool $allowed = true;
    public static bool $userExists = false;
    public static array $failed = [];
    public static array $succeeded = [];
    public static array $lookups = [];

    protected function isPasswordAttemptAllowed(string $login): bool
    {
        return static::$allowed;
    }

    protected function onPasswordAttemptFailed(string $login): void
    {
        static::$failed[] = $login;
    }

    protected function onPasswordAttemptSucceeded(string $login): void
    {
        static::$succeeded[] = $login;
    }

    protected static function fetchUserModel($id)
    {
        static::$lookups[] = $id;

        // only the POSTed login: the cookie step asks for id 0 first
        if (static::$userExists && $id === 'someone@example.com') {
            return (new PasswordOkUser())->setId(1)->set('active', 1);
        }

        // no such user: the password check fails without touching the DB
        return \diModel::create(static::USER_MODEL_TYPE);
    }

    // cookies and the user token are not what is tested here
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
 * Password sign-in had no limit at all, unlike the PIN; the hooks let a project
 * add one where every POST login goes through.
 */
class PasswordAttemptHooksTest extends TestCase
{
    private array $post;

    protected function setUp(): void
    {
        $this->post = $_POST;
        $_POST = [
            Auth::POST_LOGIN_FIELD => 'someone@example.com',
            Auth::POST_PASSWORD_FIELD => 'guess',
        ];
        TestablePasswordAuth::$allowed = true;
        TestablePasswordAuth::$userExists = false;
        TestablePasswordAuth::$failed = [];
        TestablePasswordAuth::$succeeded = [];
        TestablePasswordAuth::$lookups = [];
    }

    protected function tearDown(): void
    {
        $_POST = $this->post;
        unset($_SESSION[Auth::SESSION_USER_ID_FIELD]);
    }

    public function testSucceededAttemptIsReported(): void
    {
        TestablePasswordAuth::$userExists = true;

        $auth = new TestablePasswordAuth(false);

        $this->assertTrue($auth->authorized());
        $this->assertSame(['someone@example.com'], TestablePasswordAuth::$succeeded);
        $this->assertSame([], TestablePasswordAuth::$failed);
    }

    public function testNonScalarLoginReachesNoHook(): void
    {
        $_POST[Auth::POST_LOGIN_FIELD] = ['someone@example.com'];

        // as a project that turns warnings into exceptions
        set_error_handler(static function (int $no, string $message) {
            throw new \ErrorException($message, 0, $no);
        });

        try {
            $auth = new TestablePasswordAuth(false);
        } finally {
            restore_error_handler();
        }

        $this->assertFalse($auth->authorized());
        // the cookie step looks up id 0; the POSTed array must not be looked up
        $this->assertNotContains(['someone@example.com'], TestablePasswordAuth::$lookups);
        $this->assertSame([], TestablePasswordAuth::$failed);
    }

    public function testFailedAttemptIsReported(): void
    {
        $auth = new TestablePasswordAuth(false);

        $this->assertFalse($auth->authorized());
        $this->assertSame(['someone@example.com'], TestablePasswordAuth::$failed);
    }

    public function testRefusedAttemptNeverChecksThePassword(): void
    {
        TestablePasswordAuth::$allowed = false;

        $auth = new TestablePasswordAuth(false);

        $this->assertFalse($auth->authorized());
        $this->assertNotContains(
            'someone@example.com',
            TestablePasswordAuth::$lookups
        );
        $this->assertSame([], TestablePasswordAuth::$failed);
    }

    public function testNoCredentialsNoHooks(): void
    {
        $_POST = [];

        new TestablePasswordAuth(false);

        $this->assertSame([], TestablePasswordAuth::$failed);
    }
}
