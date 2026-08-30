<?php

namespace diCore\Tests\Payment;

use diCore\Payment\Tinkoff\Helper;
use diCore\Payment\Tinkoff\MerchantApi;
use PHPUnit\Framework\TestCase;

/**
 * securepay.tinkoff.ru подписан корнем Минцифры, которого нет ни в одном
 * стандартном ca-certificates. Раз проверка сертификата включена, единственный
 * работающий на РФ-хосте вариант — подложить корень через CURLOPT_CAINFO, и
 * прокинуть его путь обязан Helper: MerchantApi он создаёт сам, подменить его
 * проекту негде. Молчаливая потеря этой связки = отказ ВСЕХ платежей.
 */
class TinkoffCaBundleTest extends TestCase
{
    protected function setUp(): void
    {
        CaBundleHelperStub::$path = null;
    }

    /** По умолчанию — хранилище хоста, поведение прочих потребителей не меняем */
    public function testNoBundleByDefault(): void
    {
        $api = new MerchantApi('terminal', 'secret');

        $this->assertNull($api->getCaBundle());
        $this->assertNull((new CaBundleHelperStub())->api()->getCaBundle());
    }

    public function testHelperPassesItsBundleToTheApi(): void
    {
        CaBundleHelperStub::$path = '/etc/ssl/certs/tinkoff.pem';

        $this->assertSame(
            '/etc/ssl/certs/tinkoff.pem',
            (new CaBundleHelperStub())->api()->getCaBundle()
        );
    }

    /**
     * Пустая строка — это «не настроено», а не «путь ''». Отдать её в CAINFO
     * значило бы поменять внятный отказ на «error setting certificate verify
     * locations» без единого имени файла внутри.
     */
    public function testEmptyPathMeansHostStore(): void
    {
        $api = new MerchantApi('terminal', 'secret');

        $this->assertNull($api->setCaBundle('')->getCaBundle());
        $this->assertNull($api->setCaBundle(null)->getCaBundle());
    }

    /**
     * Путь НЕ проверяется на существование: несуществующий файл даёт cURL 77 с
     * его именем в сообщении, а это лучшая диагностика, чем тихий откат к
     * хранилищу хоста — на РФ-хосте такой откат означает отказ платежей с
     * ошибкой, указывающей куда угодно, только не на опечатку в пути.
     */
    public function testMissingFileIsNotSilentlyDropped(): void
    {
        $api = new MerchantApi('terminal', 'secret');
        $api->setCaBundle('/nowhere/there-is-no-such-bundle.pem');

        $this->assertSame(
            '/nowhere/there-is-no-such-bundle.pem',
            $api->getCaBundle()
        );
    }
}

class CaBundleHelperStub extends Helper
{
    public static $path = null;

    public function api(): MerchantApi
    {
        return $this->getApi();
    }

    protected static function getCaBundlePath(): ?string
    {
        return static::$path;
    }
}
