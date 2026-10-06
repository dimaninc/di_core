<?php

namespace diCore\Tests\Entity;

use PHPUnit\Framework\TestCase;

/**
 * Covers \diModel password hashing: md5 by default, bcrypt by use_insecure_password_hash
 * = false, a legacy md5 verifying and being replaced under bcrypt, and the cookie secret.
 * No DB: save() and the read-back are stubbed.
 */
class PasswordHashTest extends TestCase
{
    public function testDefaultModelKeepsMd5(): void
    {
        $m = new Md5PasswordModel(['password' => md5('secret')]);

        $this->assertSame(md5('secret'), Md5PasswordModel::hashPasswordFromRawToDb('secret'));
        $this->assertTrue($m->isPasswordOk('secret'));
        $this->assertFalse($m->isPasswordOk('wrong'));
        $this->assertFalse(Md5PasswordModel::passwordNeedsRehash(md5('secret')));
    }

    public function testMd5ModelVerifiesThroughItsOwnHashOverride(): void
    {
        $m = new SaltedMd5PasswordModel(['password' => md5('salt' . 'secret')]);

        $this->assertTrue($m->isPasswordOk('secret'));
        $this->assertFalse((new SaltedMd5PasswordModel(['password' => md5('secret')]))->isPasswordOk('secret'));
    }

    public function testBcryptModelHashesWithItsCost(): void
    {
        $hash = BcryptPasswordModel::hashPasswordFromRawToDb('secret');

        $this->assertSame(4, password_get_info($hash)['options']['cost']);
        $this->assertTrue((new BcryptPasswordModel(['password' => $hash]))->isPasswordOk('secret'));
        $this->assertFalse((new BcryptPasswordModel(['password' => $hash]))->isPasswordOk('wrong'));
        $this->assertFalse(BcryptPasswordModel::passwordNeedsRehash($hash));
    }

    public function testBcryptOfAnotherCostNeedsRehash(): void
    {
        $hash = password_hash('secret', PASSWORD_BCRYPT, ['cost' => 5]);

        $this->assertTrue(BcryptPasswordModel::passwordNeedsRehash($hash));
    }

    public function testLegacyMd5VerifiesUnderBcryptAndNeedsRehash(): void
    {
        $m = new BcryptPasswordModel(['password' => md5('secret')]);

        $this->assertTrue($m->isPasswordOk('secret'));
        $this->assertFalse($m->isPasswordOk('wrong'));
        $this->assertTrue(BcryptPasswordModel::passwordNeedsRehash(md5('secret')));
    }

    public function testLegacyMd5IsRefusedWhenNotAllowed(): void
    {
        $m = new StrictBcryptPasswordModel(['password' => md5('secret')]);

        $this->assertFalse($m->isPasswordOk('secret'));
    }

    /** The md5 of the password must not pass as the password itself. */
    public function testMd5StringIsNotAcceptedAsRawPasswordForBcryptHash(): void
    {
        $hash = BcryptPasswordModel::hashPasswordFromRawToDb('secret');
        $m = new BcryptPasswordModel(['password' => $hash]);

        $this->assertFalse($m->isPasswordOk(md5('secret')));
    }

    public function testUpgradeReplacesLegacyHashAndSavesOnce(): void
    {
        $m = new BcryptPasswordModel(['id' => 7, 'password' => md5('secret')]);
        $m->upgradePasswordHash('secret');

        $this->assertCount(1, $m->saved);
        $this->assertStringStartsWith('$2y$04$', $m->getPassword());
        $this->assertTrue($m->isPasswordOk('secret'));
    }

    public function testUpgradeLeavesCurrentHashAlone(): void
    {
        $hash = BcryptPasswordModel::hashPasswordFromRawToDb('secret');
        $m = new BcryptPasswordModel(['id' => 7, 'password' => $hash]);
        $m->upgradePasswordHash('secret');

        $this->assertSame([], $m->saved);
        $this->assertSame($hash, $m->getPassword());
    }

    public function testUpgradeNeverTouchesMd5Model(): void
    {
        $m = new Md5PasswordModel(['id' => 7, 'password' => md5('secret')]);
        $m->upgradePasswordHash('secret');

        $this->assertSame([], $m->saved);
    }

    /** A column too narrow for bcrypt: the old hash is written back, sign-in keeps working. */
    public function testUpgradeRestoresOldHashWhenColumnCutTheNewOne(): void
    {
        $m = new BcryptPasswordModel(['id' => 7, 'password' => md5('secret')]);
        $m->storedIntact = false;
        $m->upgradePasswordHash('secret');

        $this->assertSame(md5('secret'), end($m->saved));
        $this->assertSame(md5('secret'), $m->getPassword());
        $this->assertTrue($m->isPasswordOk('secret'));
    }

    public function testFailedSaveKeepsOldHashInModel(): void
    {
        $m = new BcryptPasswordModel(['id' => 7, 'password' => md5('secret')]);
        $m->failSave = true;
        $m->upgradePasswordHash('secret');

        $this->assertSame(md5('secret'), $m->getPassword());
    }

    /** cost 12 by the project's own hasher, password_hash_cost left null (10 on 8.3). */
    public function testOwnHasherIsNotRehashedAtEverySignIn(): void
    {
        $hash = OwnHasherPasswordModel::hashPasswordFromRawToDb('secret');
        $m = new OwnHasherPasswordModel(['id' => 7, 'password' => $hash]);
        $m->upgradePasswordHash('secret');
        $m->upgradePasswordHash('secret');

        $this->assertSame([], $m->saved);
        $this->assertSame($hash, $m->getPassword());
    }

    public function testSaltedLegacyMd5VerifiesThroughItsHook(): void
    {
        $m = new SaltedLegacyBcryptPasswordModel(['id' => 7, 'password' => md5('salt' . 'secret')]);

        $this->assertTrue($m->isPasswordOk('secret'));
        $this->assertFalse($m->isPasswordOk('wrong'));

        $m->upgradePasswordHash('secret');
        $this->assertStringStartsWith('$2y$04$', $m->getPassword());
    }

    public function testMd5WithTrailingNewlineIsNotLegacy(): void
    {
        $this->assertTrue(BcryptPasswordModel::isLegacy(md5('x')));
        $this->assertFalse(BcryptPasswordModel::isLegacy(md5('x') . "\n"));
    }

    /** \Error from a project's beforeSave()/afterSave() must not turn a sign-in into a 500. */
    public function testUpgradeSurvivesErrorAndRestoresValidationMode(): void
    {
        $m = new BcryptPasswordModel(['id' => 7, 'password' => md5('secret')]);
        $m->failSave = true;
        $m->saveError = \Error::class;
        $m->setValidationNeeded(false);
        $m->upgradePasswordHash('secret');

        $this->assertSame(md5('secret'), $m->getPassword());
        $this->assertFalse($m->isValidationNeeded());
    }

    public function testCutHashAndFailedRestoreDoNotThrow(): void
    {
        $m = new BcryptPasswordModel(['id' => 7, 'password' => md5('secret')]);
        $m->storedIntact = false;
        $m->failSaveFrom = 2;
        $m->upgradePasswordHash('secret');

        $this->assertSame(md5('secret'), $m->getPassword());
        $this->assertTrue($m->isValidationNeeded());
    }

    public function testMd5CookieSecretIsUnchanged(): void
    {
        $db = md5('secret');
        $m = new Md5PasswordModel(['password' => $db]);

        $this->assertSame(md5($db), Md5PasswordModel::hash($db, 'cookie', 'db'));
        $this->assertTrue($m->isPasswordOk(md5($db), 'cookie'));
    }

    public function testBcryptCookieSecretIsKeyedHmacNotTheHash(): void
    {
        $hash = BcryptPasswordModel::hashPasswordFromRawToDb('secret');
        $cookie = BcryptPasswordModel::hash($hash, 'cookie', 'db');
        $m = new BcryptPasswordModel(['password' => $hash]);

        $this->assertSame(hash_hmac('sha256', $hash, 'test-key'), $cookie);
        $this->assertTrue($m->isPasswordOk($cookie, 'cookie'));
        $this->assertFalse($m->isPasswordOk($hash, 'cookie'));
        $this->assertFalse($m->isPasswordOk(hash('sha256', $hash), 'cookie'));
    }

    public function testCookieKeyIsReadFromEnv(): void
    {
        putenv(EnvKeyBcryptPasswordModel::password_cookie_secret_env . '=env-key');

        try {
            $hash = EnvKeyBcryptPasswordModel::hashPasswordFromRawToDb('secret');
            $this->assertSame(
                hash_hmac('sha256', $hash, 'env-key'),
                EnvKeyBcryptPasswordModel::hashPasswordFromDbToCookie($hash)
            );
        } finally {
            putenv(EnvKeyBcryptPasswordModel::password_cookie_secret_env);
        }
    }

    /** An unkeyed HMAC is computable from a dump: no key – no cookie, either way. */
    public function testWithoutCookieKeyBcryptCookieIsOff(): void
    {
        $hash = EnvKeyBcryptPasswordModel::hashPasswordFromRawToDb('secret');
        $m = new EnvKeyBcryptPasswordModel(['password' => $hash]);

        $this->assertSame('', EnvKeyBcryptPasswordModel::hashPasswordFromDbToCookie($hash));
        $this->assertFalse($m->isPasswordOk(hash_hmac('sha256', $hash, ''), 'cookie'));
    }

    public function testEmptyStoredPasswordNeverMatches(): void
    {
        $m = new BcryptPasswordModel(['password' => '']);

        $this->assertFalse($m->isPasswordOk('', 'cookie'));
        $this->assertFalse($m->isPasswordOk('anything'));
        $this->assertSame('', BcryptPasswordModel::hashPasswordFromDbToCookie(''));
    }
}

trait StubbedPasswordSave
{
    /** @var string[] password values passed to save() */
    public $saved = [];
    public $storedIntact = true;
    public $failSave = false;
    /** @var int|null fail from this save() call on (1-based) */
    public $failSaveFrom = null;
    public $saveError = \Exception::class;
    private $saveCalls = 0;

    public function save()
    {
        $this->saveCalls++;

        if (
            $this->failSave ||
            ($this->failSaveFrom && $this->saveCalls >= $this->failSaveFrom)
        ) {
            throw new $this->saveError('save failed');
        }

        $this->saved[] = $this->get('password');

        return $this;
    }

    protected function isStoredValueIntact(string $field, string $value): bool
    {
        return $this->storedIntact;
    }
}

class Md5PasswordModel extends \diModel
{
    use StubbedPasswordSave;

    const table = '_di_core_test_password';
}

class SaltedMd5PasswordModel extends Md5PasswordModel
{
    public static function hashPasswordFromRawToDb($rawPassword, $field = null)
    {
        return md5('salt' . $rawPassword);
    }
}

class BcryptPasswordModel extends \diModel
{
    use StubbedPasswordSave;

    const table = '_di_core_test_password';
    const use_insecure_password_hash = false;
    // the lowest cost bcrypt allows – keeps the test fast
    const password_hash_cost = 4;

    protected static function passwordCookieSecret(): string
    {
        return 'test-key';
    }

    public static function isLegacy(string $hash): bool
    {
        return static::isLegacyMd5Hash($hash);
    }
}

class StrictBcryptPasswordModel extends BcryptPasswordModel
{
    const legacy_md5_password_hash_allowed = false;
}

class SaltedLegacyBcryptPasswordModel extends BcryptPasswordModel
{
    protected static function legacyPasswordHash($rawPassword): string
    {
        return md5('salt' . $rawPassword);
    }
}

class OwnHasherPasswordModel extends BcryptPasswordModel
{
    const password_hash_cost = null;

    public static function hashPasswordFromRawToDb($rawPassword, $field = null)
    {
        return password_hash((string) $rawPassword, PASSWORD_BCRYPT, ['cost' => 5]);
    }
}

/** Reads the cookie key from env like a real model, under a name no environment has. */
class EnvKeyBcryptPasswordModel extends \diModel
{
    const table = '_di_core_test_password';
    const use_insecure_password_hash = false;
    const password_hash_cost = 4;
    const password_cookie_secret_env = 'DI_CORE_TEST_AUTH_COOKIE_SECRET';
}
