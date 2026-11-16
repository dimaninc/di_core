<?php

namespace diCore\Tests\Payment;

use diCore\Entity\PaymentDraft\Model as Draft;
use diCore\Payment\System;
use diCore\Payment\Yandex\Vendor;
use diCore\Payment\YooKassa\Helper;
use diCore\Payment\YooKassa\MerchantApi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class YooKassaHelperTest extends TestCase
{
    private function draft(int $vendor = Vendor::CARD, $amount = 543.2): Draft
    {
        $draft = Draft::create();
        $draft
            ->setId(77)
            ->setPaySystem(System::yandex_kassa)
            ->setVendor($vendor)
            ->setAmount($amount);

        return $draft;
    }

    public function testBodyIsSingleStageRedirectBoundToTheDraft(): void
    {
        $body = Helper::paymentBody($this->draft(), [
            'returnUrl' => 'https://site.test/api/payment/yookassa/return/?draft=77',
            'description' => 'Оплата',
        ]);

        $this->assertSame(
            ['value' => '543.20', 'currency' => 'RUB'],
            $body['amount']
        );
        $this->assertTrue($body['capture']);
        $this->assertSame('redirect', $body['confirmation']['type']);
        $this->assertSame(
            'https://site.test/api/payment/yookassa/return/?draft=77',
            $body['confirmation']['return_url']
        );
        $this->assertSame(['draft_id' => '77'], $body['metadata']);
        $this->assertSame('Оплата', $body['description']);
        $this->assertSame(['type' => 'bank_card'], $body['payment_method_data']);
    }

    /** Чек по умолчанию не передаётся: у проекта со своей кассой он был бы вторым */
    public function testNoReceiptByDefault(): void
    {
        $body = Helper::paymentBody($this->draft(), ['customerEmail' => 'a@b.c']);

        $this->assertArrayNotHasKey('receipt', $body);
    }

    public function testReceiptWhenEnabled(): void
    {
        $body = ReceiptHelper::paymentBody($this->draft(), [
            'customerEmail' => 'a@b.c',
            'customerPhone' => '+7 (900) 111-22-33',
            'description' => 'Открытка',
        ]);

        $this->assertSame(
            ['email' => 'a@b.c', 'phone' => '79001112233'],
            $body['receipt']['customer']
        );
        $item = $body['receipt']['items'][0];
        $this->assertSame('Открытка', $item['description']);
        $this->assertSame(
            ['value' => '543.20', 'currency' => 'RUB'],
            $item['amount']
        );
        $this->assertSame(1, $item['vat_code']);
        $this->assertSame(2, $body['receipt']['tax_system_code']);
    }

    /** ЮKassa ждёт E.164: «8 (900)…» одними цифрами дало бы 8900… и отказ */
    public function testReceiptPhoneIsE164(): void
    {
        $body = ReceiptHelper::paymentBody($this->draft(), [
            'customerPhone' => '8 (900) 111-22-33',
        ]);

        $this->assertSame(['phone' => '79001112233'], $body['receipt']['customer']);
    }

    /** 8-800 – горячая линия магазина: чек по SMS ушёл бы туда, а не покупателю */
    public function testTollFreePhoneIsNotTheBuyers(): void
    {
        $body = ReceiptHelper::paymentBody($this->draft(), [
            'customerEmail' => 'a@b.c',
            'customerPhone' => '8 800 555-35-35',
        ]);

        $this->assertSame(['email' => 'a@b.c'], $body['receipt']['customer']);
    }

    /** В чек идёт то же обрезанное описание, что в платёж */
    public function testReceiptGetsTheTrimmedDescription(): void
    {
        $item = fn(string $d) => ReceiptHelper::paymentBody($this->draft(), [
            'customerEmail' => 'a@b.c',
            'description' => $d,
        ])['receipt']['items'][0]['description'];

        $this->assertSame('Открытка', $item('  Открытка  '));
        $this->assertSame('Payment', $item('   '));
    }

    /** Контакты покупателя из чека в лог не попадают */
    public function testLoggedBodyHidesTheCustomer(): void
    {
        $body = ReceiptHelper::paymentBody($this->draft(), [
            'customerEmail' => 'a@b.c',
            'customerPhone' => '+7 900 111-22-33',
        ]);
        $logged = print_r(Helper::bodyForLog($body), true);

        $this->assertStringNotContainsString('a@b.c', $logged);
        $this->assertStringNotContainsString('79001112233', $logged);
        $this->assertSame($body['amount'], Helper::bodyForLog($body)['amount']);
    }

    /** Сумма уходит в рублях: долларовый черновик не стартует, до сети */
    public function testNonRubleDraftIsRefused(): void
    {
        $draft = $this->draft();
        $draft->setCurrency(\diCore\Payment\Payment::usd);

        $this->expectExceptionMessage('RUB only');

        (new Helper())->getFormUri($draft);
    }

    /** Без контакта ЮKassa отвергла бы и чек, и платёж */
    public function testReceiptWithoutContactIsDropped(): void
    {
        $body = ReceiptHelper::paymentBody($this->draft(), []);

        $this->assertArrayNotHasKey('receipt', $body);
    }

    public function testLongDescriptionIsCut(): void
    {
        $body = Helper::paymentBody($this->draft(), [
            'description' => str_repeat('я', 200),
        ]);

        $this->assertSame(128, mb_strlen($body['description']));
    }

    /** Способ без типа в API: ЮKassa покажет все способы магазина */
    public function testVendorWithoutApiTypeLeavesTheChoiceToThePage(): void
    {
        $body = Helper::paymentBody($this->draft(Vendor::QIWI_WALLET));

        $this->assertArrayNotHasKey('payment_method_data', $body);
    }

    #[DataProvider('apiTypes')]
    public function testVendorMapsToApiType(int $vendor, string $type): void
    {
        $this->assertSame($type, Vendor::apiType($vendor));
    }

    public static function apiTypes(): array
    {
        return [
            'карта' => [Vendor::CARD, 'bank_card'],
            'Mir Pay' => [Vendor::MIR_PAY, 'bank_card'],
            'СБП' => [Vendor::SBP, 'sbp'],
            'SberPay' => [Vendor::SBERPAY, 'sberbank'],
            'T-Pay' => [Vendor::T_PAY, 'tinkoff_bank'],
            'ЮMoney' => [Vendor::YANDEX_MONEY, 'yoo_money'],
        ];
    }

    #[DataProvider('paidMethods')]
    public function testPaidMethodMapsBack(array $method, ?int $vendor): void
    {
        $this->assertSame($vendor, Vendor::fromPaymentMethod($method));
    }

    public static function paidMethods(): array
    {
        return [
            'карта' => [
                ['type' => 'bank_card', 'card' => ['last4' => '1111']],
                Vendor::CARD,
            ],
            'Mir Pay' => [
                ['type' => 'bank_card', 'card' => ['source' => 'mir_pay']],
                Vendor::MIR_PAY,
            ],
            'СБП' => [['type' => 'sbp'], Vendor::SBP],
            'SberPay' => [['type' => 'sberbank'], Vendor::SBERPAY],
            'T-Pay' => [['type' => 'tinkoff_bank'], Vendor::T_PAY],
            'source не строка' => [
                ['type' => 'bank_card', 'card' => ['source' => ['x']]],
                Vendor::CARD,
            ],
            'неизвестный' => [['type' => 'cash'], null],
            'пусто' => [[], null],
        ];
    }

    /** Имена совпадают с Т-Банком и Робокассой: по ним выбираются иконка и текст */
    public function testFallbackVendorNamesMatchOtherGateways(): void
    {
        $this->assertSame('sbp', Vendor::name(Vendor::SBP));
        $this->assertSame('sberpay', Vendor::name(Vendor::SBERPAY));
        $this->assertSame('t-pay', Vendor::name(Vendor::T_PAY));
        $this->assertSame(Vendor::SBERPAY, Vendor::SBERBANK_ONLINE);
    }

    /** Форма со старым именем не должна молча создать черновик без способа */
    public function testLegacyNameFindsTheSameVendor(): void
    {
        $this->assertSame(Vendor::SBERPAY, Vendor::id('sberbank-online'));
        $this->assertSame(Vendor::SBERPAY, Vendor::id('sberpay'));
        // КупиВКредит – не T-Pay: старое имя не превращается в другой способ
        $this->assertNull(Vendor::id('tinkoff'));
    }

    /** Платёж без paymentType – способ неизвестен, а не T-Pay */
    public function testEmptyCodeIsNoVendor(): void
    {
        $this->assertNull(Vendor::id(null));
        $this->assertNull(Vendor::id(''));
        $this->assertNull(Vendor::code(Vendor::T_PAY));
    }

    public function testAmountHasTwoDecimals(): void
    {
        $this->assertSame('100.00', Helper::amount(100)['value']);
        $this->assertSame('543.20', Helper::amount(543.2000000001)['value']);
        $this->assertSame('0.10', Helper::amount('0.1')['value']);
    }

    public function testIdempotenceKeyIsPerDraft(): void
    {
        $this->assertSame('draft-77', Helper::idempotenceKey($this->draft()));
    }

    public function testGoodPaymentUrls(): void
    {
        $this->assertTrue(
            Helper::isPaymentUrl(
                'https://yoomoney.ru/checkout/payments/v2/contract?orderId=22e12f66-000f-5000-8000-18db351245c7'
            )
        );
        $this->assertTrue(Helper::isPaymentUrl('HTTPS://YooKassa.ru/pay'));
    }

    #[DataProvider('badUrls')]
    public function testBadPaymentUrls($url): void
    {
        $this->assertFalse(Helper::isPaymentUrl($url));
    }

    public static function badUrls(): array
    {
        return [
            'не строка' => [null],
            'http' => ['http://yoomoney.ru/checkout'],
            'чужой домен' => ['https://evil.test/checkout'],
            'похожий домен' => ['https://notyoomoney.ru/checkout'],
            'userinfo' => ['https://yoomoney.ru@evil.test/checkout'],
            'перевод строки' => ["https://yoomoney.ru/checkout\n"],
            // браузер читает `\` как `/` и уходит на evil.com
            'обратный слеш в хосте' => ['https://evil.com\.yoomoney.ru/pay'],
            'слишком длинный' => ['https://yoomoney.ru/' . str_repeat('a', 1100)],
        ];
    }

    #[DataProvider('ips')]
    public function testNotificationNetworks(string $ip, bool $expected): void
    {
        $this->assertSame($expected, Helper::isNotificationIp($ip));
    }

    public static function ips(): array
    {
        return [
            'начало /27' => ['185.71.76.0', true],
            'конец /27' => ['185.71.76.31', true],
            'за /27' => ['185.71.76.32', false],
            '/25' => ['77.75.153.100', true],
            'одиночный' => ['77.75.156.11', true],
            'сосед одиночного' => ['77.75.156.12', false],
            '154.128/25' => ['77.75.154.200', true],
            '154.127' => ['77.75.154.127', false],
            'IPv6' => ['2a02:5180:0:1::1', true],
            'чужой IPv6' => ['2a02:5181::1', false],
            'IPv4 через dual-stack' => ['::ffff:185.71.76.1', true],
            'чужой IPv4 через dual-stack' => ['::ffff:8.8.8.8', false],
            'чужой' => ['8.8.8.8', false],
            'мусор' => ['not-an-ip', false],
        ];
    }

    /** Опечатка в префиксе списка не должна пускать всех */
    public function testBadPrefixMatchesNothing(): void
    {
        foreach (['8.8.8.0/-1', '8.8.8.0/33', '8.8.8.0/x', '8.8.8.0/'] as $cidr) {
            $this->assertFalse(Helper::ipInCidr('1.2.3.4', $cidr), $cidr);
        }

        $this->assertTrue(Helper::ipInCidr('1.2.3.4', '0.0.0.0/0'));
        $this->assertTrue(Helper::ipInCidr('8.8.8.8', '8.8.8.8/32'));
    }

    public function testNotificationGivesOnlyAPaymentId(): void
    {
        $h = new Helper();

        $h->readNotification(
            '{"type":"notification","event":"payment.succeeded","object":{"id":"2d5c-0001","status":"succeeded"}}'
        );
        $this->assertSame('2d5c-0001', $h->getNotificationPaymentId());

        $h->readNotification(
            '{"type":"notification","event":"refund.succeeded","object":{"id":"r-1"}}'
        );
        $this->assertNull($h->getNotificationPaymentId());

        $h->readNotification('garbage');
        $this->assertNull($h->getNotificationPaymentId());
    }

    /** id из неподписанного тела не подделывает строки лога */
    public function testFailedFetchLogsTheIdOnOneLine(): void
    {
        $h = new LogCapturingHelper();
        LogCapturingHelper::$lines = [];

        $this->assertNull($h->fetchPayment("a\nFAKE LINE"));
        $this->assertCount(1, LogCapturingHelper::$lines);
        $this->assertStringNotContainsString("\n", LogCapturingHelper::$lines[0]);
    }

    public function testPaymentStates(): void
    {
        $paid = ['status' => 'succeeded', 'paid' => true];

        $this->assertTrue(Helper::isPaid($paid));
        $this->assertFalse(Helper::isPaid(['status' => 'succeeded']));
        $this->assertFalse(
            Helper::isPaid(['status' => 'waiting_for_capture', 'paid' => true])
        );
        $this->assertTrue(Helper::isHold(['status' => 'waiting_for_capture']));
        $this->assertTrue(Helper::isCanceled(['status' => 'canceled']));
        $this->assertTrue(Helper::isTest(['test' => true]));
        $this->assertFalse(Helper::isTest([]));
        $this->assertSame(
            77,
            Helper::draftIdOf(['metadata' => ['draft_id' => '77']])
        );
        $this->assertSame(0, Helper::draftIdOf([]));
    }
}

class ReceiptHelper extends Helper
{
    const SEND_RECEIPT = true;
    const RECEIPT_TAX_SYSTEM_CODE = 2;
}

class LogCapturingHelper extends Helper
{
    public static array $lines = [];

    public function __construct()
    {
    }

    public static function log($message)
    {
        static::$lines[] = (string) $message;
    }

    /** Без сети: невалидный id отказывается до запроса */
    protected function createApi(): MerchantApi
    {
        return new MerchantApi('1', 'k');
    }
}
