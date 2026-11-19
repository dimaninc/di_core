<?php

namespace diCore\Tests\Tool;

use PHPUnit\Framework\TestCase;

/** `sameSite => true` went out as `SameSite=1`, which browsers ignore. */
class CookieSameSiteTest extends TestCase
{
    public function testTrueMeansLax(): void
    {
        $this->assertSame('Lax', \diCookie::sameSiteValue(true));
    }

    public function testValidStringsPass(): void
    {
        foreach (['Lax', 'Strict', 'None'] as $value) {
            $this->assertSame($value, \diCookie::sameSiteValue($value));
        }
    }

    public function testAnythingElseOmitsTheAttribute(): void
    {
        foreach ([false, null, '', 1, '1', 'lax-ish'] as $value) {
            $this->assertSame('', \diCookie::sameSiteValue($value));
        }
    }
}
