<?php

namespace diCore\Tests\Tool;

use PHPUnit\Framework\TestCase;

class TestableCookie extends \diCookie
{
    public static array $invalid = [];

    protected static function onInvalidSameSite($sameSite): void
    {
        static::$invalid[] = $sameSite;
    }
}

/** `sameSite => true` went out as `SameSite=1`, which browsers ignore. */
class CookieSameSiteTest extends TestCase
{
    protected function setUp(): void
    {
        TestableCookie::$invalid = [];
    }

    public function testTrueMeansLax(): void
    {
        $this->assertSame('Lax', TestableCookie::sameSiteValue(true));
    }

    public function testValidStringsPass(): void
    {
        foreach (['Lax', 'Strict', 'None'] as $value) {
            $this->assertSame($value, TestableCookie::sameSiteValue($value));
        }
    }

    public function testAnyCaseIsCanonicalised(): void
    {
        $this->assertSame('Lax', TestableCookie::sameSiteValue('lax'));
        $this->assertSame('Strict', TestableCookie::sameSiteValue('STRICT'));
        $this->assertSame('None', TestableCookie::sameSiteValue('none'));
        $this->assertSame([], TestableCookie::$invalid);
    }

    public function testNotSetOmitsTheAttributeSilently(): void
    {
        foreach ([false, null, ''] as $value) {
            $this->assertSame('', TestableCookie::sameSiteValue($value));
        }

        $this->assertSame([], TestableCookie::$invalid);
    }

    public function testUnknownValueIsOmittedAndReported(): void
    {
        foreach ([1, '1', 'lax-ish', ['Lax']] as $value) {
            $this->assertSame('', TestableCookie::sameSiteValue($value));
        }

        $this->assertSame([1, '1', 'lax-ish', ['Lax']], TestableCookie::$invalid);
    }

    /** Chrome drops a `SameSite=None` cookie without `Secure` entirely. */
    public function testNoneForcesSecure(): void
    {
        $options = TestableCookie::setcookieOptions(['sameSite' => 'none']);

        $this->assertSame('None', $options['samesite']);
        $this->assertTrue($options['secure']);
    }

    public function testOtherValuesKeepSecureAsGiven(): void
    {
        $this->assertFalse(TestableCookie::setcookieOptions(['sameSite' => 'Lax'])['secure']);
        $this->assertTrue(
            TestableCookie::setcookieOptions(['sameSite' => 'Lax', 'secure' => true])['secure']
        );
    }
}
