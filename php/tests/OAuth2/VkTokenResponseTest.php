<?php

namespace diCore\Tests\OAuth2;

use PHPUnit\Framework\TestCase;

/**
 * Ответ VK на обмен кода, из которого не вышло массива, обязан стать `\Exception`
 * (его ловят контроллеры входа), а не `TypeError` от `count(null)` – тот уходил 500.
 */
class VkTokenResponseTest extends TestCase
{
    public function testEmptyBodyIsARefusalNotATypeError(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error during first request');

        FakeVkOAuth2::answering('')->retrieve();
    }

    public function testNonJsonBodyIsARefusalNotATypeError(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error during first request');

        FakeVkOAuth2::answering('<html>502 Bad Gateway</html>')->retrieve();
    }

    public function testVendorErrorIsPassedThrough(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('invalid_grant: Code is invalid or expired.');

        FakeVkOAuth2::answering(
            '{"error":"invalid_grant","error_description":"Code is invalid or expired."}'
        )->retrieve();
    }

    public function testVendorErrorWithoutDescriptionDoesNotWarn(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('invalid_client: ');

        FakeVkOAuth2::answering('{"error":"invalid_client"}')->retrieve();
    }
}

class FakeVkOAuth2 extends \diOAuth2Vk
{
    private static string $body = '';

    public static function answering(string $body): self
    {
        self::$body = $body;

        return new self();
    }

    public static function makeHttpRequest(
        $url,
        $params = [],
        $method = self::REQUEST_GET
    ) {
        return self::$body;
    }

    public function retrieve(): void
    {
        $this->retrieveProfile();
    }

    protected function getAuthUrlParams()
    {
        return [];
    }
}
