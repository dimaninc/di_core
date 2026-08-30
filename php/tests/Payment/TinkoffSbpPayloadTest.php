<?php

namespace diCore\Tests\Payment;

use diCore\Payment\Tinkoff\Helper;
use diCore\Payment\Tinkoff\MerchantApi;
use PHPUnit\Framework\TestCase;

/**
 * Строка СБП – это и картинка QR, и deep-link в приложение банка, то есть
 * единственный вход в оплату по СБП. Но она не обязана быть: по конкретному
 * платежу QR может отсутствовать, а шлюз – не ответить. Вызывающий обязан уметь
 * деградировать на веб-форму, поэтому getSbpPayload() не бросает никогда.
 */
class TinkoffSbpPayloadTest extends TestCase
{
    private function helper($response): SbpPayloadHelperStub
    {
        SbpPayloadHelperStub::$logs = [];
        SbpPayloadHelperStub::$lastArgs = null;

        return new SbpPayloadHelperStub($response);
    }

    private function logs(): string
    {
        return join("\n", SbpPayloadHelperStub::$logs);
    }

    public function testPayloadIsReturned(): void
    {
        $h = $this->helper(
            '{"Success":true,"ErrorCode":"0","Data":"https://qr.nspk.ru/AS1000670LSS7SSJ48A9OK1EOK1QOK1M"}'
        );

        $this->assertSame(
            'https://qr.nspk.ru/AS1000670LSS7SSJ48A9OK1EOK1QOK1M',
            $h->getSbpPayload(3456789)
        );
    }

    /** Реальный payload несёт query-строку — она часть платёжных данных */
    public function testPayloadWithQueryStringIsReturned(): void
    {
        $payload =
            'https://qr.nspk.ru/AS1000670LSS7SSJ48A9OK1EOK1QOK1M?type=01&bank=100000000004&sum=10000&cur=RUB&crc=C08B';
        $h = $this->helper(json_encode(['Success' => true, 'Data' => $payload]));

        $this->assertSame($payload, $h->getSbpPayload(1));
    }

    /** DataType=IMAGE отдал бы SVG, он не нужен; PaymentId шлём строкой */
    public function testRequestAsksForThePayloadDataType(): void
    {
        $h = $this->helper('{"Success":true,"Data":"https://qr.nspk.ru/A1"}');
        $h->getSbpPayload(3456789);

        $this->assertSame(
            [
                'PaymentId' => '3456789',
                'DataType' => 'PAYLOAD',
            ],
            SbpPayloadHelperStub::$lastArgs
        );
    }

    /**
     * Success=false – это «по этому платежу QR нет», а не сбой запроса, и
     * верхнеуровневые флаги MerchantApi эти два случая смешивают.
     */
    public function testUnsuccessfulResponseGivesNull(): void
    {
        $h = $this->helper(
            '{"Success":false,"ErrorCode":"9999","Message":"Неверные параметры","Data":"https://qr.nspk.ru/A1"}'
        );

        $this->assertNull($h->getSbpPayload(1));
        $this->assertNotEmpty(SbpPayloadHelperStub::$logs);
    }

    /**
     * Отсутствие Success – не успех: иначе JSON от промежуточного прокси/WAF
     * вида {"Data":"https://…"} прошёл бы проверку целиком.
     */
    public function testMissingSuccessGivesNull(): void
    {
        $h = $this->helper('{"Data":"https://qr.nspk.ru/A1"}');

        $this->assertNull($h->getSbpPayload(1));
        $this->assertNotEmpty(SbpPayloadHelperStub::$logs);

        $h = $this->helper('{"Success":null,"Data":"https://qr.nspk.ru/A1"}');

        $this->assertNull($h->getSbpPayload(1));
    }

    public function testEmptyDataGivesNull(): void
    {
        $h = $this->helper('{"Success":true,"ErrorCode":"0","Data":""}');

        $this->assertNull($h->getSbpPayload(1));
        $this->assertNotEmpty(SbpPayloadHelperStub::$logs);
    }

    public function testMissingDataGivesNull(): void
    {
        $h = $this->helper('{"Success":true,"ErrorCode":"0"}');

        $this->assertNull($h->getSbpPayload(1));
    }

    /** Не https:// – значит не та строка (SVG из DataType=IMAGE, мусор шлюза) */
    public function testNonHttpsDataGivesNull(): void
    {
        foreach (
            ['<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'http://qr.nspk.ru/A1', 'nope']
            as $data
        ) {
            $h = $this->helper(json_encode(['Success' => true, 'Data' => $data]));

            $this->assertNull($h->getSbpPayload(1), $data);
        }
    }

    /**
     * Из этой строки собирается QR и deep-link оплаты, то есть она и есть адрес
     * платежа. Одного https:// мало: чужой хост – это платёж не туда, и лучше
     * отдать null (вызывающий покажет веб-форму), чем увести плательщика.
     */
    public function testForeignHostGivesNull(): void
    {
        foreach (
            [
                'https://evil.tld/qr.nspk.ru/A1',
                'https://qr.nspk.ru.evil.tld/A1', // не поддомен nspk.ru
                'https://qr.nspk.ru@evil.tld/A1', // userinfo: host здесь evil.tld
                'https://nspk.ru.evil.tld/A1',
                'https://securepay.tinkoff.ru/html/payForm/success.html',
            ]
            as $data
        ) {
            $h = $this->helper(json_encode(['Success' => true, 'Data' => $data]));

            $this->assertNull($h->getSbpPayload(1), $data);
            $this->assertNotEmpty(SbpPayloadHelperStub::$logs, $data);
        }
    }

    public function testNspkSubdomainIsAccepted(): void
    {
        foreach (['https://qr.nspk.ru/A1', 'https://nspk.ru/A1', 'https://sbp.qr.nspk.ru/A1']
            as $data) {
            $h = $this->helper(json_encode(['Success' => true, 'Data' => $data]));

            $this->assertSame($data, $h->getSbpPayload(1), $data);
        }
    }

    /**
     * Схема и хост по RFC 3986 регистронезависимы, а parse_url() не приводит ни
     * то, ни другое. Отказ здесь безопасен по направлению, но это всё равно
     * авария: «HTTPS://…» выключил бы СБП на всех платежах, а в логе было бы
     * «no usable SBP payload» — про регистр ни слова. Путь не трогаем: он как
     * раз регистрозависим, в нём идентификатор QR.
     */
    public function testSchemeAndHostAreCaseInsensitive(): void
    {
        foreach (
            [
                'HTTPS://qr.nspk.ru/A1',
                'Https://qr.nspk.ru/A1',
                'https://QR.NSPK.RU/A1',
                'HtTpS://Qr.NsPk.Ru/AbC',
            ]
            as $data
        ) {
            $h = $this->helper(json_encode(['Success' => true, 'Data' => $data]));

            $this->assertSame($data, $h->getSbpPayload(1), $data);
        }
    }

    /**
     * Управляющие символы и пробелы в «ссылке» – это не ссылка. Завершающий
     * перевод строки — отдельный случай: PCRE-шный `$` пропускает его перед
     * концом строки, поэтому якорь обязан быть `\z`. Иначе payload с "\n" на
     * хвосте признаётся годным и подделывает строку в логе.
     */
    public function testPayloadWithControlCharsGivesNull(): void
    {
        foreach (
            [
                "https://qr.nspk.ru/A1\nX",
                "https://qr.nspk.ru/A1\n",
                "https://qr.nspk.ru/A1\r\n",
                "https://qr.nspk.ru/A1 X",
                "https://qr.nspk.ru/\x00A1",
            ]
            as $data
        ) {
            $h = $this->helper(json_encode(['Success' => true, 'Data' => $data]));

            $this->assertNull($h->getSbpPayload(1), $data);
        }
    }

    /**
     * Реальный payload — 50-120 символов. Без потолка «ссылка» на сто тысяч
     * символов проходит все остальные проверки, уходит в QR-кодер и, если
     * вызывающий её сохранит, в колонку БД, где при sql_mode без STRICT молча
     * обрежется.
     */
    public function testOverlongPayloadGivesNull(): void
    {
        $long = 'https://qr.nspk.ru/' . str_repeat('A', 100000);
        $h = $this->helper(json_encode(['Success' => true, 'Data' => $long]));

        $this->assertNull($h->getSbpPayload(1));

        // граница: 512 включительно — ещё годно
        $atLimit = 'https://qr.nspk.ru/' . str_repeat('A', 512 - 19);
        $h = $this->helper(json_encode(['Success' => true, 'Data' => $atLimit]));

        $this->assertSame($atLimit, $h->getSbpPayload(1));
    }

    /**
     * Редакт обязан идти ДО обрезки. Обрезать первым дешевле, но разрез внутри
     * значения Token оставляет `"Token":"aaaa…` без закрывающей кавычки, регулярка
     * такое не ловит, и кусок подписи уезжает в лог как есть. Тест держит порядок.
     */
    public function testTokenIsRedactedEvenWhenTheBodyGetsTruncated(): void
    {
        $token = str_repeat('a', 64);
        $h = $this->helper(
            '{"Token":"' . $token . '","Junk":"' . str_repeat('x', 5000) . '"}'
        );

        $this->assertNull($h->getSbpPayload(1));

        $log = $this->logs();

        $this->assertStringContainsString('truncated', $log);
        $this->assertStringNotContainsString($token, $log);
        $this->assertStringNotContainsString(str_repeat('a', 10), $log);
    }

    public function testUndecodableJsonGivesNull(): void
    {
        $h = $this->helper('<html>502 Bad Gateway</html>');

        $this->assertNull($h->getSbpPayload(1));
        $this->assertNotEmpty(SbpPayloadHelperStub::$logs);
    }

    /** curl_exec() при сбое транспорта возвращает false */
    public function testTransportFailureGivesNull(): void
    {
        $this->assertNull($this->helper(false)->getSbpPayload(1));
        $this->assertNull($this->helper('')->getSbpPayload(1));
    }

    /** Таймаут, DNS и TLS в логе обязаны различаться, иначе разбирать нечего */
    public function testTransportFailureReasonIsLogged(): void
    {
        $h = $this->helper(false);
        $h->api()->stubError = 'cURL error: Operation timed out after 20001 ms';

        $this->assertNull($h->getSbpPayload(1));
        $this->assertStringContainsString('Operation timed out', $this->logs());
    }

    public function testThrownExceptionIsSwallowed(): void
    {
        $h = $this->helper(new \RuntimeException('connection reset'));

        $this->assertNull($h->getSbpPayload(1));
        $this->assertStringContainsString('connection reset', $this->logs());
        $this->assertStringContainsString('RuntimeException', $this->logs());
    }

    /**
     * HttpException из MerchantApi несёт тело запроса, а в нём – подпись Token.
     * Платёжный лог читают шире, чем секреты.
     */
    public function testTokenIsNotLoggedFromException(): void
    {
        // Фикстура фиксированная и заведомо не секрет: редакт идёт по КЛЮЧУ
        // ("Token": "…"), значение ему безразлично, поэтому брать сюда что-то
        // похожее на настоящую подпись — тем более из окружения — незачем.
        $token = str_repeat('0', 64);

        $h = $this->helper(
            new \RuntimeException(
                'Can not create connection to https://securepay.tinkoff.ru/v2/GetQr/ with args ' .
                    '{"PaymentId":"1","TerminalKey":"t","Token":"' .
                    $token .
                    '"}'
            )
        );

        $this->assertNull($h->getSbpPayload(1));
        $this->assertStringNotContainsString($token, $this->logs());
    }

    /**
     * Тело ответа не аутентифицировано: перевода строки в логе быть не должно
     * (подделка записей), как и неограниченной длины (заполнение диска).
     */
    public function testLoggedResponseBodyIsSanitized(): void
    {
        // перевод строки здесь — валидный для JSON пробельный символ, то есть
        // тело с ним доезжает до лога как есть
        $h = $this->helper(
            "{\"Success\":false,\n\"Details\":\"broken\",\"Junk\":\"" .
                str_repeat('A', 5000) .
                '"}'
        );

        $this->assertNull($h->getSbpPayload(1));

        $log = $this->logs();

        $this->assertStringNotContainsString("\n", $log);
        $this->assertLessThan(1200, mb_strlen($log));
        $this->assertStringContainsString('truncated', $log);
    }

    /** JSON верхнего уровня, но не объект – Data взять неоткуда */
    public function testScalarJsonGivesNull(): void
    {
        $this->assertNull($this->helper('"ok"')->getSbpPayload(1));
        $this->assertNull($this->helper('null')->getSbpPayload(1));
    }

    public function testMerchantApiCallsTheGetQrMethod(): void
    {
        $api = new RecordingMerchantApi('terminal', 'secret');
        $api->getQr(['PaymentId' => '1']);

        $this->assertSame('GetQr', $api->path);
    }

    /**
     * GetQr идёт по тому же экземпляру MerchantApi, что и Init, а его ответ
     * PaymentURL не содержит. Раньше успешный GetQr обнулял paymentUrl и
     * выставлял ложную ошибку «missing PaymentURL» — то есть портил состояние,
     * из которого getFormUri() читает результат Init.
     */
    public function testGetQrDoesNotSpoilInitState(): void
    {
        $api = new ExposedMerchantApi('terminal', 'secret');

        $api->handle(
            '{"Success":true,"ErrorCode":"0","PaymentId":"3456789","Status":"NEW",' .
                '"PaymentURL":"https://securepay.tinkoff.ru/A1B2"}',
            'Init'
        );

        $this->assertSame('', $api->getError());
        $this->assertSame('https://securepay.tinkoff.ru/A1B2', $api->getPaymentUrl());

        $api->handle(
            '{"Success":true,"ErrorCode":"0","PaymentId":"3456789",' .
                '"Data":"https://qr.nspk.ru/A1"}',
            'GetQr'
        );

        // Испорченным было именно это: getError() отдавал «missing PaymentURL»
        // после совершенно успешного GetQr, и вызывающий, который проверяет
        // только ошибку, считал платёж непроинициализированным.
        $this->assertSame('', $api->getError());

        // А сами поля описывают последний ответ. В ответе GetQr нет ни Status,
        // ни PaymentURL — значит их нет и в состоянии. Оставленные от Init, они
        // читались бы как результат ЭТОГО вызова; getFormUri() берёт URL сразу
        // после init(), так что переживать чужой вызов ему незачем.
        $this->assertNull($api->status);
        $this->assertNull($api->getPaymentUrl());
    }

    /**
     * buildQuery() публичен, и его докблок прямо зовёт делать свои вызовы —
     * значит имя метода приходит от потребителя. Строгое сравнение с ['Init']
     * молча пропускало $api->buildQuery('init', …): ошибки нет, URL'а нет,
     * редиректить плательщика некуда.
     */
    public function testPaymentUrlCheckIsCaseInsensitive(): void
    {
        $body =
            '{"Success":true,"ErrorCode":"0","PaymentId":"1",' .
            '"PaymentURL":"https://securepay.tinkoff.ru/A1B2"}';

        foreach (['Init', 'init', 'INIT'] as $path) {
            $api = new ExposedMerchantApi('terminal', 'secret');
            $api->handle($body, $path);

            $this->assertSame('', $api->getError(), $path);
            $this->assertSame(
                'https://securepay.tinkoff.ru/A1B2',
                $api->getPaymentUrl(),
                $path
            );
        }

        // И отсутствие URL остаётся ошибкой при любом регистре
        foreach (['Init', 'init', 'INIT'] as $path) {
            $api = new ExposedMerchantApi('terminal', 'secret');
            $api->handle('{"Success":true,"ErrorCode":"0","PaymentId":"1"}', $path);

            $this->assertStringContainsString(
                'missing PaymentURL',
                $api->getError(),
                $path
            );
        }
    }

    /**
     * Ветки ошибки — то же самое требование, что и к успеху, и именно их легко
     * забыть: ранний return стоит ДО присваивания полей. Сверщик держит один
     * экземпляр на весь прогон, и «платежа B не существует» не должно читаться
     * как «платёж B подтверждён».
     */
    public function testErrorBranchesAlsoClearThePreviousResponse(): void
    {
        $bad = [
            'gateway error' => '{"Success":false,"ErrorCode":"9999",' .
                '"Details":"нет платежа"}',
            'not a json' => '<html>502 Bad Gateway</html>',
        ];

        foreach ($bad as $label => $body) {
            $api = new ExposedMerchantApi('terminal', 'secret');
            $api->handle(
                '{"Success":true,"ErrorCode":"0","PaymentId":"1000",' .
                    '"Status":"CONFIRMED"}',
                'GetState'
            );
            $this->assertSame('CONFIRMED', $api->status, $label);

            $api->handle($body, 'GetState');

            $this->assertNotSame('', $api->getError(), $label);
            $this->assertNull($api->status, $label);
            $this->assertNull($api->paymentId, $label);
            $this->assertNull($api->getPaymentUrl(), $label);
        }
    }

    /**
     * Экземпляр MerchantApi кэшируется в Helper::getApi() и обслуживает весь
     * прогон сверщика. Значит поля, описывающие ответ, обязаны обнуляться:
     * иначе после GetState(платёж A) и GetQr(платёж B) $api->status тихо
     * отдаёт статус ЧУЖОГО платежа — хуже, чем видимое отсутствие данных.
     */
    public function testStateDoesNotLeakBetweenPayments(): void
    {
        $api = new ExposedMerchantApi('terminal', 'secret');

        $api->handle(
            '{"Success":true,"ErrorCode":"0","PaymentId":"1000",' .
                '"Status":"CONFIRMED"}',
            'GetState'
        );

        $this->assertSame('CONFIRMED', $api->status);
        $this->assertSame('1000', $api->paymentId);

        $api->handle(
            '{"Success":true,"ErrorCode":"0","PaymentId":"2000",' .
                '"Data":"https://qr.nspk.ru/A1"}',
            'GetQr'
        );

        $this->assertSame('2000', $api->paymentId);
        $this->assertNull($api->status);
    }

    /** Но для Init отсутствие PaymentURL — по-прежнему ошибка */
    public function testInitWithoutPaymentUrlStillErrors(): void
    {
        $api = new ExposedMerchantApi('terminal', 'secret');
        $api->handle('{"Success":true,"ErrorCode":"0","PaymentId":"1"}', 'Init');

        $this->assertStringContainsString('missing PaymentURL', $api->getError());
    }

    public function testGatewayErrorCodeStillBecomesAnError(): void
    {
        $api = new ExposedMerchantApi('terminal', 'secret');
        $api->handle('{"Success":false,"ErrorCode":"9999","Details":"Нет связи"}', 'GetQr');

        $this->assertSame('Нет связи', $api->getError());
    }
}

class SbpPayloadHelperStub extends Helper
{
    public static $logs = [];
    public static $lastArgs = null;

    public function __construct($response)
    {
        // одинаковый экземпляр на все вызовы — как и в Helper::getApi()
        $this->api = new StubMerchantApi($response);
    }

    public function api(): StubMerchantApi
    {
        return $this->api;
    }

    protected function getApi()
    {
        return $this->api;
    }

    public static function log($message)
    {
        static::$logs[] = $message;
    }
}

class StubMerchantApi extends MerchantApi
{
    /** @var mixed строка/false ответа шлюза либо \Throwable для сбоя */
    private $response;

    /** текст, который MerchantApi положил бы в error при сбое curl */
    public $stubError = '';

    public function __construct($response)
    {
        parent::__construct('terminal', 'secret');

        $this->response = $response;
    }

    public function getError()
    {
        return $this->stubError;
    }

    public function getQr($args)
    {
        SbpPayloadHelperStub::$lastArgs = $args;

        if ($this->response instanceof \Throwable) {
            throw $this->response;
        }

        return $this->response;
    }
}

class RecordingMerchantApi extends MerchantApi
{
    public $path;
    public $args;

    public function buildQuery($path, $args)
    {
        $this->path = $path;
        $this->args = $args;

        return '';
    }
}

class ExposedMerchantApi extends MerchantApi
{
    public function handle($out, $path = null)
    {
        return $this->handleResponse($out, $path);
    }
}
