<?php

namespace diCore\Tests\Tool;

use diCore\Tool\Auth;
use PHPUnit\Framework\TestCase;

/**
 * Covers Tool\Auth::storeCookies(): the secret comes from the user model's own class
 * (it used to be \diBaseUserModel – always md5, never accepted by a bcrypt model), and a
 * bcrypt model without the cookie key gets no secret cookie at all.
 */
class AuthStoreCookiesTest extends TestCase
{
    protected function setUp(): void
    {
        RecordingCookie::$set = [];
    }

    public function testBcryptSecretIsTheModelsHmacAndIsAccepted(): void
    {
        $user = new CookieBcryptUser([
            'id' => 5,
            'active' => 1,
            'password' => CookieBcryptUser::hashPasswordFromRawToDb('secret'),
        ]);
        RecordingAuth::create(false)->forceAuthorize($user, true);

        $secret = RecordingCookie::$set[Auth::COOKIE_SECRET] ?? null;
        $this->assertSame(hash_hmac('sha256', $user->getPassword(), 'test-key'), $secret);
        $this->assertTrue($user->isPasswordOk($secret, 'cookie'));
    }

    public function testMd5SecretIsUnchanged(): void
    {
        $user = new CookieMd5User(['id' => 5, 'active' => 1, 'password' => md5('secret')]);
        RecordingAuth::create(false)->forceAuthorize($user, true);

        $this->assertSame(md5(md5('secret')), RecordingCookie::$set[Auth::COOKIE_SECRET] ?? null);
    }

    public function testBcryptWithoutKeyGetsNoSecretCookie(): void
    {
        $user = new CookieKeylessBcryptUser([
            'id' => 5,
            'active' => 1,
            'password' => CookieKeylessBcryptUser::hashPasswordFromRawToDb('secret'),
        ]);
        RecordingAuth::create(false)->forceAuthorize($user, true);

        $this->assertArrayNotHasKey(Auth::COOKIE_SECRET, RecordingCookie::$set);
        $this->assertArrayNotHasKey(Auth::COOKIE_USER_ID, RecordingCookie::$set);
    }
}

class RecordingCookie extends \diCookie
{
    /** @var array name => value of every set() */
    public static $set = [];

    public static function set($name, $value = null, $optionsOrDate = null, $path = null, $domain = null)
    {
        static::$set[$name] = $value;
    }

    public static function getDomainForAll()
    {
        return 'example.test';
    }
}

/** Nothing read from the request: only forceAuthorize() authorizes. */
class RecordingAuth extends Auth
{
    const COOKIE_PROVIDER = RecordingCookie::class;
    const USE_PHP_SESSION = false;
    const USE_COOKIES = false;
    const USE_HEADERS = false;

    public static function create($redirectAllowed = true)
    {
        return new static($redirectAllowed);
    }
}

class CookieMd5User extends \diBaseUserModel
{
    const table = '_di_core_test_cookie_user';
}

class CookieBcryptUser extends CookieMd5User
{
    const use_insecure_password_hash = false;
    const password_hash_cost = 4;

    protected static function passwordCookieSecret(): string
    {
        return 'test-key';
    }
}

class CookieKeylessBcryptUser extends CookieMd5User
{
    const use_insecure_password_hash = false;
    const password_hash_cost = 4;
    const password_cookie_secret_env = 'DI_CORE_TEST_AUTH_COOKIE_SECRET';
}
