<?php

namespace diCore\Tests\Payment;

use diCore\Payment\AlfaBank\Helper;
use diCore\Payment\AlfaBank\RequestError;
use PHPUnit\Framework\TestCase;

/**
 * Alfa-Bank (RBS): credentials from options, a typed failure for every bad answer, a log without
 * request data, the callback checksum by the gateway's rules.
 */
class AlfaBankHelperTest extends TestCase
{
    protected function setUp(): void
    {
        AlfaBankHelperStub::$sent = [];
        AlfaBankHelperStub::$replies = [];
        AlfaBankHelperStub::$logged = [];
    }

    /** The doc's own example: the string to sign, then HMAC-SHA256 in upper case */
    public function testCallbackChecksumFollowsTheGatewayString(): void
    {
        $params = [
            'status' => '1',
            'orderNumber' => '10747',
            'checksum' => 'IGNORED',
            'sign_alias' => 'IGNORED',
            'mdOrder' => '3ff6962a-7dcc-4283-ab50-a6d7dd3386fe',
            'operation' => 'deposited',
            'amount' => '123456',
        ];
        $expected = strtoupper(hash_hmac(
            'sha256',
            'amount;123456;mdOrder;3ff6962a-7dcc-4283-ab50-a6d7dd3386fe;operation;deposited;orderNumber;10747;status;1;',
            'secret'
        ));

        $this->assertSame($expected, Helper::callbackChecksum($params, 'secret'));
    }

    public function testCallbackIsGenuineOnlyWithTheRightKeyAndPlainValues(): void
    {
        $params = ['mdOrder' => 'a-b', 'orderNumber' => '7', 'operation' => 'deposited', 'status' => '1'];
        $params['checksum'] = Helper::callbackChecksum($params, 'secret');

        $this->assertTrue(Helper::isCallbackGenuine($params, 'secret'));
        // The bank sends upper case; a lower-case copy is still the same code
        $this->assertTrue(Helper::isCallbackGenuine(['checksum' => strtolower($params['checksum'])] + $params, 'secret'));
        $this->assertFalse(Helper::isCallbackGenuine($params, 'other'));
        $this->assertFalse(Helper::isCallbackGenuine($params, ''));
        $this->assertFalse(Helper::isCallbackGenuine(['status' => '0'] + $params, 'secret'));
        $this->assertFalse(Helper::isCallbackGenuine(['extra' => ['x']] + $params, 'secret'));
        unset($params['checksum']);
        $this->assertFalse(Helper::isCallbackGenuine($params, 'secret'));
    }

    public function testRegisterSendsOptionsCredentialsAndReturnsTheForm(): void
    {
        AlfaBankHelperStub::$replies = ['{"orderId":"70906e55-7114-41d6-8332-4609dc6590f4","formUrl":"https://payment.alfabank.ru/payment/merchants/x/payment_ru.html?mdOrder=1"}'];
        $helper = new AlfaBankHelperStub(['login' => 'shop-api', 'password' => 'Pw-7f3a91c2', 'testMode' => true]);

        $reply = $helper->register([
            'orderNumber' => 'abc',
            'amount' => 49000,
            'returnUrl' => 'https://site/x/',
            'description' => str_repeat('я', 600),
        ]);

        $this->assertSame('70906e55-7114-41d6-8332-4609dc6590f4', $reply['orderId']);
        [$url, $body] = AlfaBankHelperStub::$sent[0];
        $this->assertSame(Helper::apiUriDemo . 'register.do', $url);
        parse_str($body, $sent);
        $this->assertSame('shop-api', $sent['userName']);
        $this->assertSame('Pw-7f3a91c2', $sent['password']);
        $this->assertSame('49000', $sent['amount']);
        $this->assertSame('643', $sent['currency']);
        $this->assertSame(512, mb_strlen($sent['description']));

        // The log names the method and the order, nothing of the request or the answer
        $log = implode("\n", AlfaBankHelperStub::$logged);
        $this->assertStringContainsString('register.do order abc: ok', $log);
        $this->assertStringNotContainsString('Pw-7f3a91c2', $log);
        $this->assertStringNotContainsString('formUrl', $log);
    }

    public function testEndpointByOptions(): void
    {
        AlfaBankHelperStub::$replies = ['{"orderStatus":2}', '{"orderStatus":2}', '{"orderStatus":2}'];

        (new AlfaBankHelperStub(['login' => 'l', 'password' => 'p', 'testMode' => false]))->getOrderStatus('x');
        // Without the trailing slash too
        (new AlfaBankHelperStub(['login' => 'l', 'password' => 'p', 'apiUri' => 'https://web.rbsuat.com/ab/rest']))->getOrderStatus('x');
        // No option – the class constant, as the legacy Settings subclasses expect
        (new AlfaBankHelperStub())->getOrderStatus('x');

        $this->assertSame(Helper::apiUri . 'getOrderStatusExtended.do', AlfaBankHelperStub::$sent[0][0]);
        $this->assertSame('https://web.rbsuat.com/ab/rest/getOrderStatusExtended.do', AlfaBankHelperStub::$sent[1][0]);
        $this->assertSame(Helper::apiUri . 'getOrderStatusExtended.do', AlfaBankHelperStub::$sent[2][0]);
    }

    /** An endpoint option alone would send the Settings pair to another stand */
    public function testEndpointOptionsNeedTheirOwnCredentials(): void
    {
        $cases = [
            ['testMode' => true],
            ['apiUri' => 'https://alfa.rbsuat.com/payment/rest'],
            ['login' => '', 'password' => '', 'testMode' => true],
            ['login' => 'l', 'apiUri' => 'https://alfa.rbsuat.com/payment/rest'],
        ];

        foreach ($cases as $options) {
            try {
                new AlfaBankHelperStub($options);
                $this->fail('no InvalidArgumentException for ' . json_encode($options));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** A legacy Settings driving test mode from config gets the demo pair with the demo endpoint */
    public function testLegacyTestModePicksThePairWithTheEndpoint(): void
    {
        AlfaBankHelperStub::$replies = ['{"orderStatus":2}'];

        (new AlfaBankLegacyDemoStub())->getOrderStatus('x');

        [$url, $body] = AlfaBankHelperStub::$sent[0];
        parse_str($body, $sent);
        $this->assertSame(Helper::apiUriDemo . 'getOrderStatusExtended.do', $url);
        $this->assertSame(['demo-api', 'demo-pw'], [$sent['userName'], $sent['password']]);
    }

    public function testLogNamesTheOrderSafely(): void
    {
        AlfaBankHelperStub::$replies = ['{"orderStatus":2}', '{"errorCode":"0"}'];
        $helper = new AlfaBankHelperStub();

        $helper->getOrderStatus('70906e55-7114');

        try {
            $helper->register(['orderNumber' => "5\n2026-11-15 forged line", 'amount' => 1, 'returnUrl' => 'https://x/']);
            $this->fail('no RequestError');
        } catch (RequestError $e) {
            $this->assertNull($e->bankCode);
            $this->assertFalse($e->transport);
        }

        $this->assertSame([
            'getOrderStatusExtended.do bank order 70906e55-7114: ok',
            'register.do order 5?2026-11-15?forged?line: ok',
            // The «ok» of request() is followed by the real outcome
            'register.do order 5?2026-11-15?forged?line: no orderId or formUrl in the answer',
        ], AlfaBankHelperStub::$logged);

        // The bank's code too
        AlfaBankHelperStub::$logged = [];
        AlfaBankHelperStub::$replies = ['{"errorCode":"7\nFAKE"}'];

        try {
            $helper->getOrderStatus('x');
            $this->fail('no RequestError');
        } catch (RequestError $e) {
            $this->assertSame(['getOrderStatusExtended.do bank order x: error 7?FAKE'], AlfaBankHelperStub::$logged);
        }
    }

    public function testDuplicateOrderOnlyFromRegister(): void
    {
        $helper = new AlfaBankHelperStub();
        AlfaBankHelperStub::$replies = ['{"errorCode":"1","errorMessage":"orderId or orderNumber expected"}'];

        try {
            $helper->getOrderStatus('x');
            $this->fail('no RequestError');
        } catch (RequestError $e) {
            $this->assertSame(['1', 'getOrderStatusExtended.do'], [$e->bankCode, $e->method]);
            $this->assertFalse($e->isDuplicateOrder());
        }
    }

    public function testNonScalarErrorFieldsStillGiveRequestError(): void
    {
        AlfaBankHelperStub::$replies = ['{"errorCode":"7","errorMessage":["x"]}', '{"errorCode":["7"]}'];
        $helper = new AlfaBankHelperStub();

        foreach (['7', '?'] as $code) {
            try {
                $helper->getOrderStatus('x');
                $this->fail('no RequestError');
            } catch (RequestError $e) {
                $this->assertSame($code, $e->bankCode);
                $this->assertStringContainsString('Unknown error', $e->getMessage());
            }
        }
    }

    public function testCurlOptionsKeepThePasswordOnHttpsAndInPlace(): void
    {
        $options = (new AlfaBankHelperStub(['login' => 'l', 'password' => 'p', 'testMode' => true, 'caBundle' => '/etc/ca.pem']))->options('body');

        $this->assertFalse($options[CURLOPT_FOLLOWLOCATION]);
        $this->assertSame(CURLPROTO_HTTPS, $options[CURLOPT_PROTOCOLS]);
        $this->assertSame('/etc/ca.pem', $options[CURLOPT_CAINFO]);
        $this->assertSame([5, 15], [$options[CURLOPT_CONNECTTIMEOUT], $options[CURLOPT_TIMEOUT]]);
        $this->assertArrayNotHasKey(CURLOPT_CAINFO, (new AlfaBankHelperStub())->options('body'), 'no bundle – the host store');
    }

    public function testFormUriFromDraftInRubles(): void
    {
        $draft = $this->getMockBuilder(\diCore\Entity\PaymentDraft\Model::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__call', 'getId'])
            ->getMock();
        $draft->method('getId')->willReturn(42);
        $draft->method('__call')->willReturnCallback(fn(string $method) => match ($method) {
            'getAmount' => '490.50',
            default => throw new \LogicException("unexpected $method"),
        });
        AlfaBankHelperStub::$replies = ['{"orderId":"o-1","formUrl":"https://payment.alfabank.ru/f"}'];

        $url = (new AlfaBankHelperStub())->getFormUri($draft, ['returnUrl' => 'https://site/back/', 'customerEmail' => 'a@b.ru']);

        $this->assertSame('https://payment.alfabank.ru/f', $url);
        parse_str(AlfaBankHelperStub::$sent[0][1], $sent);
        $this->assertSame(['42', '49050', 'https://site/back/', 'a@b.ru'], [$sent['orderNumber'], $sent['amount'], $sent['returnUrl'], $sent['email']]);
    }

    public function testFailuresAreTypedTransportOrBank(): void
    {
        $helper = new AlfaBankHelperStub(['login' => 'l', 'password' => 'S3cretPw']);

        $cases = [
            [['error' => 'Connection timed out'], true, null],
            ['<html>502</html>', true, null],
            ['{"errorCode":"1","errorMessage":"Order number is duplicated"}', false, '1'],
            ['{"errorCode":5,"errorMessage":"Access denied"}', false, '5'],
            // Success without the form is not a form: never a silent null
            ['{"errorCode":"0"}', false, null],
        ];

        foreach ($cases as [$reply, $transport, $code]) {
            AlfaBankHelperStub::$replies = [$reply];

            try {
                $helper->register(['orderNumber' => 'o', 'amount' => 1, 'returnUrl' => 'https://x/']);
                $this->fail('no RequestError for ' . json_encode($reply));
            } catch (RequestError $e) {
                $this->assertSame($transport, $e->transport, json_encode($reply));
                $this->assertSame($code, $e->bankCode, json_encode($reply));
                $this->assertStringNotContainsString('S3cretPw', $e->getMessage());
            }
        }

        AlfaBankHelperStub::$replies = ['{"errorCode":"1","errorMessage":"dup"}'];

        try {
            $helper->register(['orderNumber' => 'o', 'amount' => 1, 'returnUrl' => 'https://x/']);
            $this->fail('no RequestError');
        } catch (RequestError $e) {
            $this->assertTrue($e->isDuplicateOrder());
        }
    }

    /** send() itself, without the bank: a failed curl_init() and a refused connection are transport failures */
    public function testSendTurnsCurlFailuresIntoTransportErrors(): void
    {
        $cases = [
            // curl_init() failed
            [false, 'curl_init failed'],
            // Port 1 on the loopback refuses at once – the real cURL path, no outside network
            [curl_init('https://127.0.0.1:1/'), 'Failed to connect'],
        ];

        foreach ($cases as [$handle, $error]) {
            $helper = new AlfaBankCurlStub($handle);

            try {
                $helper->getOrderStatus('x');
                $this->fail('no RequestError');
            } catch (RequestError $e) {
                // The override was used: otherwise the call went to the real bank
                $this->assertSame([Helper::apiUri . 'getOrderStatusExtended.do'], $helper->opened);
                $this->assertTrue($e->transport);
                $this->assertStringContainsString($error, $e->getMessage());
            }
        }
    }

    public function testErrorCodeZeroIsSuccess(): void
    {
        AlfaBankHelperStub::$replies = ['{"errorCode":"0","orderStatus":2,"amount":49000}'];

        $this->assertSame(2, (new AlfaBankHelperStub())->getOrderStatus('x')['orderStatus']);
    }
}

class AlfaBankHelperStub extends Helper
{
    /** @var list<array{0: string, 1: string}> */
    public static array $sent = [];
    /** @var list<string|array{error: string}> */
    public static array $replies = [];
    /** @var list<string> */
    public static array $logged = [];

    public static function log($message)
    {
        static::$logged[] = $message;
    }

    public function options(string $body): array
    {
        return $this->curlOptions($body);
    }

    protected function send(string $url, string $body)
    {
        static::$sent[] = [$url, $body];

        return array_shift(static::$replies) ?? ['error' => 'no reply'];
    }
}

/** A legacy Settings: test mode from config, both pairs as constants */
class AlfaBankLegacyDemoStub extends AlfaBankHelperStub
{
    const login = 'prod-api';
    const password = 'prod-pw';
    const loginDemo = 'demo-api';
    const passwordDemo = 'demo-pw';

    protected static function isTestMode()
    {
        return true;
    }
}

/** The real send() over a handle given by the test */
class AlfaBankCurlStub extends Helper
{
    /** @var list<string> */
    public array $opened = [];

    public function __construct(private \CurlHandle|false $handle)
    {
        parent::__construct();
    }

    public static function log($message)
    {
    }

    protected function openCurl(string $url)
    {
        $this->opened[] = $url;

        return $this->handle;
    }
}
