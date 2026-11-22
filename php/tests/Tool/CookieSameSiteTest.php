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
        foreach ([false, null, '', 0, '0'] as $value) {
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
    public function testNoneForcesSecureOnHttps(): void
    {
        $this->withHttps('on', function () {
            $options = TestableCookie::setcookieOptions(['sameSite' => 'none']);

            $this->assertSame('None', $options['samesite']);
            $this->assertTrue($options['secure']);
        });
    }

    /** A Secure cookie set over http is not stored at all. */
    public function testNoneIsOmittedOverHttp(): void
    {
        $this->withHttps(null, function () {
            $options = TestableCookie::setcookieOptions(['sameSite' => 'None']);

            $this->assertSame('', $options['samesite']);
            $this->assertFalse($options['secure']);

            // an explicit secure is the caller's choice
            $options = TestableCookie::setcookieOptions(['sameSite' => 'None', 'secure' => true]);

            $this->assertSame('None', $options['samesite']);
            $this->assertTrue($options['secure']);
        });
    }

    private function withHttps(?string $https, callable $test): void
    {
        $keys = ['HTTPS', 'SERVER_PORT', 'REQUEST_SCHEME', 'SSL_PROTOCOL'];
        $saved = array_intersect_key($_SERVER, array_flip($keys));

        foreach ($keys as $key) {
            unset($_SERVER[$key]);
        }

        if ($https !== null) {
            $_SERVER['HTTPS'] = $https;
        }

        try {
            $test();
        } finally {
            foreach ($keys as $key) {
                unset($_SERVER[$key]);
            }

            $_SERVER = array_merge($_SERVER, $saved);
        }
    }

    public function testOtherValuesKeepSecureAsGiven(): void
    {
        $this->assertFalse(TestableCookie::setcookieOptions(['sameSite' => 'Lax'])['secure']);
        $this->assertTrue(
            TestableCookie::setcookieOptions(['sameSite' => 'Lax', 'secure' => true])['secure']
        );
    }
}
