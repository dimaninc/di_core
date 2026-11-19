<?php

namespace diCore\Tests\Tool;

use diCore\Tool\Auth;
use PHPUnit\Framework\TestCase;

class TestablePasswordAuth extends Auth
{
    public static bool $allowed = true;
    public static array $failed = [];
    public static array $lookups = [];

    protected function isPasswordAttemptAllowed(string $login): bool
    {
        return static::$allowed;
    }

    protected function onPasswordAttemptFailed(string $login): void
    {
        static::$failed[] = $login;
    }

    protected static function fetchUserModel($id)
    {
        static::$lookups[] = $id;

        // no such user: the password check fails without touching the DB
        return \diModel::create(static::USER_MODEL_TYPE);
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
        TestablePasswordAuth::$failed = [];
        TestablePasswordAuth::$lookups = [];
    }

    protected function tearDown(): void
    {
        $_POST = $this->post;
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
