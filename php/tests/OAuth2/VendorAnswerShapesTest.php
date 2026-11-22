<?php

namespace diCore\Tests\OAuth2;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * То же правило, что в VkTokenResponseTest, для остальных включаемых вендоров:
 * ответ не той формы – `\Exception`, а не TypeError или warning.
 */
class VendorAnswerShapesTest extends TestCase
{
    private const TOKEN = '{"access_token":"t"}';

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

    /** @return array<string, array{class-string, string}> вендор и годный ответ профиля */
    public static function vendors(): array
    {
        return [
            'google' => [FakeGoogle::class, '{"id":"1","email":"ivan@gmail.example","given_name":"Ivan","family_name":"Petrov"}'],
            'mailru' => [FakeMailru::class, '[{"uid":"1","nick":"ivan","email":"ivan@mail.example","first_name":"Ivan","last_name":"Petrov"}]'],
            'yandex' => [FakeYandex::class, '{"id":"1","login":"ivan","default_email":"ivan@ya.example","first_name":"Ivan","last_name":"Petrov"}'],
            'ok' => [FakeOk::class, '{"uid":"1","name":"Ivan"}'],
        ];
    }

    #[DataProvider('vendors')]
    public function testBrokenTokenAnswersAreRefusals(string $class): void
    {
        foreach (['', '<html>502 Bad Gateway</html>', '"x"', '[]'] as $body) {
            $this->assertRefused('Error during first request', $class, $body);
        }
    }

    #[DataProvider('vendors')]
    public function testVendorErrorWithoutDescriptionDoesNotWarn(string $class): void
    {
        $this->assertRefused('invalid_grant: ', $class, '{"error":"invalid_grant"}');
    }

    #[DataProvider('vendors')]
    public function testBrokenProfileAnswersAreRefusals(string $class): void
    {
        foreach (['', '<html></html>', '"x"'] as $body) {
            $this->assertRefused('Error retrieving data', $class, self::TOKEN, $body);
        }
    }

    #[DataProvider('vendors')]
    public function testValidAnswersGiveTheProfile(string $class, string $profile): void
    {
        $vendor = $class::answering(self::TOKEN, $profile);

        $vendor->retrieve();

        $data = $vendor->rawData();
        $this->assertSame('1', (string) ($data['uid'] ?? $data['id'] ?? ''));
    }

    private function assertRefused(string $message, string $class, string ...$bodies): void
    {
        try {
            $class::answering(...$bodies)->retrieve();
        } catch (\ErrorException $e) {
            $this->fail("PHP warning instead of a refusal: {$e->getMessage()}");
        } catch (\Exception $e) {
            $this->assertStringContainsString($message, $e->getMessage());

            return;
        }

        $this->fail('Expected a refusal, the profile was retrieved');
    }
}

trait FakeVendorHttp
{
    /** @var string[] ответы по порядку запросов: обмен кода, профиль */
    private static array $bodies = [];

    public static function answering(string ...$bodies): static
    {
        self::$bodies = $bodies;

        return new static();
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

    public function rawData(): array
    {
        return $this->profileRawData;
    }

    protected function getAuthUrlParams()
    {
        return [];
    }
}

class FakeGoogle extends \diOAuth2Google
{
    use FakeVendorHttp;
}

class FakeMailru extends \diOAuth2Mailru
{
    use FakeVendorHttp;

    const appId = '1';
    const secret = 's';
}

class FakeYandex extends \diOAuth2Yandex
{
    use FakeVendorHttp;
}

class FakeOk extends \diOAuth2Ok
{
    use FakeVendorHttp;

    const publicKey = 'p';
    const secret = 's';
}
