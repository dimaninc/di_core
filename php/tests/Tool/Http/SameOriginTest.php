<?php

namespace diCore\Tests\Tool\Http;

use diCore\Base\Exception\HttpException;
use diCore\Tool\Http\SameOrigin;
use PHPUnit\Framework\TestCase;

class ProdSameOrigin extends SameOrigin
{
    protected static function isDev(): bool
    {
        return false;
    }
}

class SameOriginTest extends TestCase
{
    private $server = [];

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['SERVER_PORT'] = 443;
        $_SERVER['HTTPS'] = 'on';
        unset(
            $_SERVER['HTTP_ORIGIN'],
            $_SERVER['HTTP_REFERER'],
            $_SERVER['HTTP_X_SESSION'],
            $_SERVER['HTTP_AUTH_TOKEN']
        );
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    public function testOriginIsNormalized(): void
    {
        $this->assertSame(
            'https://example.com',
            SameOrigin::normalizeOrigin('HTTPS://Example.com:443/path?q=1')
        );
        $this->assertSame(
            'http://example.com:8080',
            SameOrigin::normalizeOrigin('http://example.com:8080/')
        );
        $this->assertSame('', SameOrigin::normalizeOrigin('example.com'));
        $this->assertSame('', SameOrigin::normalizeOrigin('null'));
    }

    public function testSameOriginPasses(): void
    {
        $this->assertSame('https://example.com', ProdSameOrigin::expectedOrigin());

        $_SERVER['HTTP_ORIGIN'] = 'https://example.com';
        $this->assertTrue(ProdSameOrigin::isCsrfSafe());
    }

    public function testRefererIsTheFallback(): void
    {
        $_SERVER['HTTP_REFERER'] = 'https://example.com/sign-in/';
        $this->assertTrue(ProdSameOrigin::isCsrfSafe());

        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
        $this->assertFalse(ProdSameOrigin::isCsrfSafe(), 'Origin wins over Referer');
    }

    public function testForeignDowngradedOrMissingOriginIsRefused(): void
    {
        $this->assertFalse(ProdSameOrigin::isCsrfSafe(), 'no Origin and no Referer');

        foreach (
            [
                'https://evil.example',
                'http://example.com',
                'https://example.com:8443',
            ]
            as $origin
        ) {
            $_SERVER['HTTP_ORIGIN'] = $origin;
            $this->assertFalse(ProdSameOrigin::isCsrfSafe(), $origin);
        }

        $this->expectException(HttpException::class);
        ProdSameOrigin::enforceCsrf();
    }

    public function testHeaderAuthenticatedRequestIsImmune(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example';

        $_SERVER['HTTP_X_SESSION'] = 'abc';
        $this->assertTrue(ProdSameOrigin::isCsrfSafe());

        unset($_SERVER['HTTP_X_SESSION']);
        $_SERVER['HTTP_AUTH_TOKEN'] = 'abc';
        $this->assertTrue(ProdSameOrigin::isCsrfSafe());

        $_SERVER['HTTP_AUTH_TOKEN'] = '  ';
        $this->assertFalse(
            ProdSameOrigin::isCsrfSafe(),
            'a blank header is no authentication'
        );
    }
}
