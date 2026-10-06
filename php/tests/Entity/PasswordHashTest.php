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

    public function save()
    {
        if ($this->failSave) {
            throw new \Exception('save failed');
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
}

class StrictBcryptPasswordModel extends BcryptPasswordModel
{
    const legacy_md5_password_hash_allowed = false;
}
