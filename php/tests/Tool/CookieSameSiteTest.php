<?php

namespace diCore\Tests\Tool;

use PHPUnit\Framework\TestCase;

class TestableCookie extends \diCookie
{
    public static array $invalid = [];
    public static array $logged = [];
    public static array $sent = [];

    protected static function onInvalidSameSite($sameSite): void
    {
        static::$invalid[] = $sameSite;
    }

    // not into the real log file
    protected static function log($message)
    {
        static::$logged[] = $message;
    }

    protected static function sendCookie($name, $value, array $options): void
    {
        static::$sent[] = [$name, $value, $options];
    }
}

/** `sameSite => true` went out as `SameSite=1`, which browsers ignore. */
class CookieSameSiteTest extends TestCase
{
    protected function setUp(): void
    {
        TestableCookie::$invalid = [];
        TestableCookie::$logged = [];
        TestableCookie::$sent = [];
        (fn() => static::$noneOverHttpLogged = false)->bindTo(null, TestableCookie::class)();
    }

    public function testTrueMeansLax(): void
    {
        // 1 and '1' went out exactly like true before the fix
        foreach ([true, 1, '1'] as $value) {
            $this->assertSame('Lax', TestableCookie::sameSiteValue($value));
        }
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
        foreach ([2, 'lax-ish', ['Lax']] as $value) {
            $this->assertSame('', TestableCookie::sameSiteValue($value));
        }

        $this->assertSame([2, 'lax-ish', ['Lax']], TestableCookie::$invalid);
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
            $this->assertCount(1, TestableCookie::$logged);

            // reported once per request, not per cookie
            TestableCookie::setcookieOptions(['sameSite' => 'None']);
            $this->assertCount(1, TestableCookie::$logged);

            // an explicit secure is the caller's choice
            $options = TestableCookie::setcookieOptions(['sameSite' => 'None', 'secure' => true]);

            $this->assertSame('None', $options['samesite']);
            $this->assertTrue($options['secure']);
        });
    }

    /** Behind a TLS-terminating proxy PHP itself sees http. */
    public function testForwardedHttpsCounts(): void
    {
        $this->withHttps(null, function () {
            // a proxy chain: the first value is the client's
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'HTTPS, http';

            $options = TestableCookie::setcookieOptions(['sameSite' => 'None']);

            $this->assertSame('None', $options['samesite']);
            $this->assertTrue($options['secure']);
        });
    }

    /** The positional setcookie() had no place for sameSite: it was dropped. */
    public function testSetSendsSameSiteWithoutAsOptions(): void
    {
        TestableCookie::set('c', 'v', ['sameSite' => 'Strict', 'path' => '/']);

        $this->assertSame('c', TestableCookie::$sent[0][0]);
        $this->assertSame('Strict', TestableCookie::$sent[0][2]['samesite']);
        $this->assertSame('/', TestableCookie::$sent[0][2]['path']);
    }

    private function withHttps(?string $https, callable $test): void
    {
        $keys = ['HTTPS', 'SERVER_PORT', 'REQUEST_SCHEME', 'SSL_PROTOCOL', 'HTTP_X_FORWARDED_PROTO'];
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
