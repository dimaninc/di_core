<?php

namespace diCore\Tests\Payment;

use diCore\Entity\PaymentDraft\Model as Draft;
use diCore\Payment\CloudPayments\Vendor as CloudPaymentsVendor;
use diCore\Payment\Payment;
use diCore\Payment\System;
use PHPUnit\Framework\TestCase;

/**
 * Платёжная система описана в ЧЕТЫРЁХ независимых местах — реестр имён,
 * `System::getSystemClass()`, `switch` в `Payment::initiateProcess()` и
 * `PaymentDraft\Model::getVendorStr()`. Связи между ними нет никакой, а цена
 * расхождения разная: пропущенная ветка в первых двух роняет запрос
 * исключением, в четвёртой — молча печатает «Vendor #10» (там номер ВЕНДОРА,
 * не системы).
 *
 * Список WIRED и есть то, что этот тест сторожит: он перечисляет системы,
 * доведённые до приёма денег. Новая система либо попадает в него и обязана
 * резолвиться везде, либо не попадает — и тогда это осознанное решение, а не
 * забытая ветка.
 */
class SystemRegistryTest extends TestCase
{
    /**
     * Системы, у которых есть И контейнер вендоров, И ветка запуска оплаты.
     *
     * Здесь НЕТ webmoney и paymaster (контейнер есть, запуска нет), sberbank и
     * alfabank (запуск есть, контейнера нет) и sms_online (не реализована).
     * Это существующие пробелы библиотеки, а не недосмотр теста: делать его
     * красным из-за них значит выключить его для всех потребителей.
     */
    private const WIRED = [
        System::robokassa,
        System::yandex_kassa,
        System::tinkoff,
        System::mixplat,
        System::paypal,
        System::crypto_cloud,
        System::cloud_payments,
    ];

    public function testNamesAndTitlesDescribeTheSameSet(): void
    {
        $this->assertSame(
            array_keys(System::$names),
            array_keys(System::$titles),
            'Реестры имён и заголовков разошлись'
        );
    }

    public function testWiredSystemsAreInTheRegistry(): void
    {
        foreach (self::WIRED as $systemId) {
            $this->assertArrayHasKey($systemId, System::$names);
            $this->assertNotSame('', (string) System::title($systemId));
        }
    }

    public function testWiredSystemsResolveToAVendorContainer(): void
    {
        foreach (self::WIRED as $systemId) {
            $class = System::getSystemClass($systemId);

            $this->assertTrue(
                class_exists($class),
                'Нет класса вендоров для ' . System::name($systemId)
            );
        }
    }

    /**
     * Самая незаметная из четырёх веток: без неё ничего не падает, просто в
     * админке и в письме о платеже вместо способа оплаты стоит «Vendor #10».
     */
    public function testDraftNamesTheCloudPaymentsVendor(): void
    {
        $draft = Draft::create();
        $draft
            ->setPaySystem(System::cloud_payments)
            ->setVendor(CloudPaymentsVendor::FOREIGN_CARD);

        $this->assertSame(
            CloudPaymentsVendor::title(CloudPaymentsVendor::FOREIGN_CARD),
            $draft->getVendorStr()
        );
    }

    public function testInitiateProcessKnowsEveryWiredSystem(): void
    {
        foreach (self::WIRED as $systemId) {
            $spy = new InitiateProcessSpy(0, 0, 0);
            $spy->systemId = $systemId;

            $spy->initiateProcess(100, System::name($systemId), null);

            $this->assertTrue(
                $spy->launched,
                'Нет ветки запуска оплаты для ' . System::name($systemId)
            );
        }
    }

    /** Страховочная сетка не должна исчезнуть вместе с добавлением системы. */
    public function testUnknownSystemStillFailsLoudly(): void
    {
        $spy = new InitiateProcessSpy(0, 0, 0);
        $spy->systemId = 9999;

        $this->expectException(\Exception::class);

        $spy->initiateProcess(100, System::name(System::tinkoff), null);
    }
}

/**
 * Подменяет создание черновика (иначе нужна запись в БД) и все ветки запуска
 * (иначе нужен живой шлюз), оставляя ровно то, что проверяется — сам `switch`.
 */
class InitiateProcessSpy extends Payment
{
    public $systemId;
    public $launched = false;

    public function getNewDraft(
        $amount,
        $systemId,
        $vendorId = 0,
        $currency = self::rub
    ) {
        $draft = Draft::create();
        $draft->setPaySystem((int) $this->systemId);

        return $draft;
    }

    public function initMixplat($draft)
    {
        return $this->launch();
    }

    public function initSmsOnline($draft)
    {
        return $this->launch();
    }

    public function initPaypal($draft)
    {
        return $this->launch();
    }

    public function initCryptoCloud($draft)
    {
        return $this->launch();
    }

    public function initYandex($draft)
    {
        return $this->launch();
    }

    public function initRobokassa($draft)
    {
        return $this->launch();
    }

    public function initTinkoff($draft)
    {
        return $this->launch();
    }

    public function initSberbank($draft)
    {
        return $this->launch();
    }

    public function initAlfaBank($draft)
    {
        return $this->launch();
    }

    public function initCloudPayments($draft)
    {
        return $this->launch();
    }

    private function launch()
    {
        $this->launched = true;

        return true;
    }
}
