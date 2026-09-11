<?php

namespace diCore\Tests\Payment;

use diCore\Payment\CloudPayments\Helper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * У этого шлюза нет ни списка IP, ни второго канала подтверждения: подпись
 * уведомления — единственное, что отделяет настоящий платёж от любого, кто
 * угадал номер черновика. И адрес, который шлюз вернул на создание счёта, — это
 * буквально то место, куда уедут деньги плательщика.
 *
 * Поэтому проверяются ровно две вещи: что подпись нельзя обойти и что адрес
 * нельзя подменить.
 */
class CloudPaymentsHelperTest extends TestCase
{
    private const SECRET = 'api-secret-value';

    protected function setUp(): void
    {
        unset($_SERVER['HTTP_CONTENT_HMAC'], $_SERVER['HTTP_X_CONTENT_HMAC']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_CONTENT_HMAC'], $_SERVER['HTTP_X_CONTENT_HMAC']);
    }

    private function signatureOf(string $body): string
    {
        return base64_encode(hash_hmac('sha256', $body, self::SECRET, true));
    }

    public function testCorrectSignatureIsAccepted(): void
    {
        $body = '{"TransactionId":1,"Amount":543.2}';
        $_SERVER['HTTP_CONTENT_HMAC'] = $this->signatureOf($body);

        $helper = new CloudPaymentsSecretStub();
        $helper->readNotification($body);

        $this->assertTrue($helper->checkSignature());
    }

    /** Второй заголовок принимается наравне: формат уведомления задаётся в ЛК. */
    public function testAlternativeHeaderIsAccepted(): void
    {
        $body = 'TransactionId=1&Amount=543.20';
        $_SERVER['HTTP_X_CONTENT_HMAC'] = $this->signatureOf($body);

        $helper = new CloudPaymentsSecretStub();
        $helper->readNotification($body);

        $this->assertTrue($helper->checkSignature());
    }

    /**
     * Подпись считается по СЫРЫМ байтам, поэтому любая правка тела её ломает.
     * Ровно этим и держится доверие к сумме и к номеру черновика в уведомлении.
     */
    public function testTamperedBodyIsRejected(): void
    {
        $body = '{"TransactionId":1,"Amount":543.2}';
        $_SERVER['HTTP_CONTENT_HMAC'] = $this->signatureOf($body);

        $helper = new CloudPaymentsSecretStub();
        $helper->readNotification('{"TransactionId":1,"Amount":1.0}');

        $this->assertFalse($helper->checkSignature());
    }

    /** Отсутствие заголовка — это отказ, а не «проверять нечего». */
    public function testMissingSignatureIsRejected(): void
    {
        $helper = new CloudPaymentsSecretStub();
        $helper->readNotification('{"TransactionId":1}');

        $this->assertFalse($helper->checkSignature());
    }

    /**
     * Пустой секрет означает незаполненный env, а не «проверка не нужна».
     * Иначе забытая переменная превращала бы эндпоинт в открытый.
     */
    public function testEmptySecretRejectsEverything(): void
    {
        $body = '{"TransactionId":1}';
        $_SERVER['HTTP_CONTENT_HMAC'] = base64_encode(
            hash_hmac('sha256', $body, '', true)
        );

        $helper = new CloudPaymentsNoSecretStub();
        $helper->readNotification($body);

        $this->assertFalse($helper->checkSignature());
    }

    public function testNotificationParsesBothFormats(): void
    {
        $helper = new CloudPaymentsSecretStub();

        $helper->readNotification('{"InvoiceId":"42","Status":"Completed"}');
        $this->assertSame('42', $helper->getNotificationParams()['InvoiceId']);

        $helper->readNotification('InvoiceId=42&Status=Completed');
        $this->assertSame('42', $helper->getNotificationParams()['InvoiceId']);
    }

    public function testPaidStatuses(): void
    {
        $this->assertTrue(Helper::isPaidStatus('Completed'));
        $this->assertFalse(Helper::isPaidStatus('Declined'));
        $this->assertFalse(Helper::isPaidStatus(''));
        $this->assertFalse(Helper::isPaidStatus(null));
    }

    public function testGoodOrderUrlIsAccepted(): void
    {
        $this->assertTrue(
            Helper::isOrderUrl('https://orders.cloudpayments.ru/d/f2K8LV6reGE9WBFn')
        );
    }

    #[DataProvider('badUrlProvider')]
    public function testBadOrderUrlIsRejected($url, string $why): void
    {
        $this->assertFalse(Helper::isOrderUrl($url), $why);
    }

    public static function badUrlProvider(): array
    {
        return [
            'нет схемы https' => [
                'http://orders.cloudpayments.ru/d/x',
                'по http платёжную страницу открывать нельзя',
            ],
            'чужой хост' => [
                'https://evil.tld/d/x',
                'адрес платежа обязан вести к шлюзу',
            ],
            'хост как суффикс чужого' => [
                'https://cloudpayments.ru.evil.tld/d/x',
                'проверка домена не должна ловиться на суффикс',
            ],
            'userinfo подменяет хост на вид' => [
                'https://orders.cloudpayments.ru@evil.tld/d/x',
                'parse_url отдаёт здесь host=evil.tld',
            ],
            'перевод строки в конце' => [
                "https://orders.cloudpayments.ru/d/x\n",
                'иначе строка подделывает записи в логе',
            ],
            'пустая строка' => ['', 'пустой адрес — это отсутствие адреса'],
            'не строка' => [null, 'ответ шлюза может не содержать поля вовсе'],
            'массив вместо строки' => [
                ['https://orders.cloudpayments.ru/d/x'],
                'json отдаёт любой тип',
            ],
        ];
    }

    public function testOverlongUrlIsRejected(): void
    {
        $url =
            'https://orders.cloudpayments.ru/d/' .
            str_repeat('a', Helper::ORDER_URL_MAX_LEN);

        $this->assertFalse(Helper::isOrderUrl($url));
    }

    /** Схема и хост нечувствительны к регистру по RFC 3986. */
    public function testUppercaseSchemeAndHostAreAccepted(): void
    {
        $this->assertTrue(Helper::isOrderUrl('HTTPS://Orders.CloudPayments.RU/d/x'));
    }

    /**
     * Пустые необязательные поля выбрасываются, а нулевые числа — нет.
     *
     * Разница не теоретическая: голый `array_filter()` считает `0.0` пустым,
     * и нулевая сумма молча исчезла бы из запроса. Шлюз ответил бы «нет
     * обязательного поля Amount», то есть диагностика уехала бы на шаг дальше
     * от причины — от «сумма нулевая» к «мы что-то не отправили».
     */
    public function testOrderParamsKeepZeroAndDropEmptyStrings(): void
    {
        $params = CloudPaymentsSecretStub::params([
            'amount' => 0,
            'currency' => 'RUB',
            'draftId' => 42,
            'description' => 'Оплата',
            'customerEmail' => '',
            'cultureName' => 'ru-RU',
            'successUrl' =>
                'https://example.com/api/payment/cloud_payments/success/',
            'failUrl' => '',
        ]);

        $this->assertArrayHasKey('Amount', $params, 'нулевая сумма не исчезает');
        $this->assertSame(0.0, $params['Amount']);
        $this->assertSame('42', $params['InvoiceId']);
        $this->assertArrayNotHasKey('Email', $params, 'пустой адрес не отправляем');
        $this->assertArrayNotHasKey('FailRedirectUrl', $params);
    }

    public function testResponsesDifferInMeaning(): void
    {
        $this->assertSame(['code' => 0], Helper::okResponse());
        $this->assertNotSame(
            Helper::okResponse(),
            Helper::retryResponse(),
            'принятое уведомление и отказ обязаны отвечать по-разному'
        );
        $this->assertNotSame(0, Helper::retryResponse()['code']);
    }
}

class CloudPaymentsSecretStub extends Helper
{
    public static function getPassword()
    {
        return 'api-secret-value';
    }

    /** Открывает защищённую сборку тела запроса. */
    public static function params(array $opts)
    {
        return static::orderParams($opts);
    }
}

class CloudPaymentsNoSecretStub extends Helper
{
    public static function getPassword()
    {
        return '';
    }
}
