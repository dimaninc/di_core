<?php

namespace diCore\Tests\OAuth2;

use PHPUnit\Framework\TestCase;

/**
 * Любой ответ VK, из которого не вышел ожидаемый массив, обязан стать `\Exception`
 * (его ловят контроллеры входа), а не `TypeError` – тот уходил 500. Warning тут
 * тоже дефект: PHPUnit без `failOnWarning` его не роняет, поэтому тест делает это сам.
 */
class VkTokenResponseTest extends TestCase
{
    private const TOKEN = '{"access_token":"t","user_id":1}';

    protected function setUp(): void
    {
        set_error_handler(static function (int $no, string $message): bool {
            throw new \ErrorException($message, 0, $no);
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function testEmptyTokenAnswerIsARefusal(): void
    {
        $this->assertRefused('Error during first request', '');
    }

    public function testNonJsonTokenAnswerIsARefusal(): void
    {
        $this->assertRefused(
            'Error during first request',
            '<html>502 Bad Gateway</html>'
        );
    }

    public function testVendorErrorIsPassedThrough(): void
    {
        $this->assertRefused(
            'invalid_grant: Code is invalid or expired.',
            '{"error":"invalid_grant","error_description":"Code is invalid or expired."}'
        );
    }

    public function testVendorErrorWithoutDescriptionDoesNotWarn(): void
    {
        $this->assertRefused('invalid_client: ', '{"error":"invalid_client"}');
    }

    public function testTokenWithoutUserIdDoesNotWarn(): void
    {
        $this->assertRefused(
            'Error retrieving data',
            '{"access_token":"t"}',
            '{"response":[]}'
        );
    }

    public function testNonJsonProfileAnswerIsARefusal(): void
    {
        $this->assertRefused('Error retrieving data', self::TOKEN, '<html></html>');
    }

    public function testProfileWithoutResponseIsARefusal(): void
    {
        $this->assertRefused('Error retrieving data', self::TOKEN, '{"ok":1}');
    }

    public function testScalarProfileResponseIsARefusal(): void
    {
        $this->assertRefused(
            'Error retrieving data',
            self::TOKEN,
            '{"response":"x"}'
        );
    }

    public function testProfileErrorIsPassedThrough(): void
    {
        $this->assertRefused(
            'Error retrieving data: User authorization failed (code#5)',
            self::TOKEN,
            '{"error":{"error_code":5,"error_msg":"User authorization failed"}}'
        );
    }

    public function testScalarProfileErrorIsPassedThrough(): void
    {
        $this->assertRefused(
            'Error retrieving data: broken',
            self::TOKEN,
            '{"error":"broken"}'
        );
    }

    private function assertRefused(string $message, string ...$bodies): void
    {
        try {
            FakeVkOAuth2::answering(...$bodies)->retrieve();
        } catch (\ErrorException $e) {
            $this->fail("PHP warning instead of a refusal: {$e->getMessage()}");
        } catch (\Exception $e) {
            $this->assertStringContainsString($message, $e->getMessage());

            return;
        }

        $this->fail('Expected a refusal, the profile was retrieved');
    }
}

class FakeVkOAuth2 extends \diOAuth2Vk
{
    /** @var string[] ответы по порядку запросов: обмен кода, профиль */
    private static array $bodies = [];

    public static function answering(string ...$bodies): self
    {
        self::$bodies = $bodies;

        return new self();
    }

    public static function makeHttpRequest(
        $url,
        $params = [],
        $method = self::REQUEST_GET
    ) {
        return array_shift(self::$bodies) ?? '';
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
