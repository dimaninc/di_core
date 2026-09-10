<?php

namespace diCore\Tests\Payment;

use diCore\Payment\CloudPayments\Helper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Решение «это оплата или отказ» принимается по адресу, на который пришло
 * уведомление, и по полю `Status` в нём. Оба источника ненадёжны по-своему:
 * адреса вбивает человек руками в кабинете, а поле присылает шлюз.
 *
 * Цена ошибки несимметрична. Лишний отказ стоит записи в диагностике; лишняя
 * оплата — фискального чека, отданного товара, партнёрской выплаты и строки в
 * отчёте о доходе.
 */
class CloudPaymentsNotificationRoutingTest extends TestCase
{
    private function decide(array $params, string $type): bool
    {
        $controller = (new ReflectionClass(
            NotificationRoutingProbe::class
        ))->newInstanceWithoutConstructor();

        return $controller->decide($params, $type);
    }

    public function testPayAddressWithMatchingStatusIsAPayment(): void
    {
        $this->assertTrue($this->decide(['Status' => 'Completed'], 'pay'));
        $this->assertTrue($this->decide(['Status' => 'Authorized'], 'pay'));
    }

    /**
     * Один и тот же URL, вставленный в кабинете в оба поля, иначе превращает
     * каждый отказ в оплаченную квитанцию — молча, с записью «Draft #N set as
     * paid» в логе и настоящим чеком на выходе.
     */
    public function testPayAddressDoesNotOverrideADeclinedStatus(): void
    {
        $this->assertFalse($this->decide(['Status' => 'Declined'], 'pay'));
    }

    /** Поле только консультируется: его отсутствие не отменяет адрес. */
    public function testPayAddressWithoutStatusIsStillAPayment(): void
    {
        $this->assertTrue($this->decide([], 'pay'));
    }

    /** Обратное направление доверия не нужно: отказ и есть безопасный исход. */
    public function testFailAddressStaysAFailureWhateverTheStatus(): void
    {
        $this->assertFalse($this->decide(['Status' => 'Completed'], 'fail'));
        $this->assertFalse($this->decide([], 'fail'));
    }

    /**
     * Адрес без суффикса типа: решает только поле. Неизвестный статус уходит в
     * отказ — решать по ОТСУТСТВИЮ признака провала значит помечать оплаченной
     * любую нераспознанную посылку.
     */
    #[DataProvider('statusOnlyProvider')]
    public function testAddressWithoutTypeGoesByStatus($status, bool $expected): void
    {
        $this->assertSame(
            $expected,
            $this->decide($status === null ? [] : ['Status' => $status], '')
        );
    }

    public static function statusOnlyProvider(): array
    {
        return [
            'оплачено' => ['Completed', true],
            'заблокировано' => ['Authorized', true],
            'отказ' => ['Declined', false],
            'неизвестный статус' => ['Whatever', false],
            'пустой статус' => ['', false],
            'поля нет вовсе' => [null, false],
        ];
    }

    /**
     * Тестовый режим у этого шлюза — состояние сайта, а не другие ключи, значит
     * подпись у тестового уведомления настоящая и весь живой путь оно проходит
     * до конца. Признак единственный, и читать его надо ровно так.
     */
    #[DataProvider('modeFlagProvider')]
    public function testTestModeIsRecognised($value, bool $expected): void
    {
        $this->assertSame(
            $expected,
            Helper::isTestMode($value === null ? [] : ['TestMode' => $value])
        );
    }

    public static function modeFlagProvider(): array
    {
        return [
            'единица числом' => [1, true],
            'единица строкой' => ['1', true],
            'булево' => [true, true],
            'ноль числом' => [0, false],
            'ноль строкой' => ['0', false],
            'поля нет' => [null, false],
        ];
    }
}

class NotificationRoutingProbe extends \diCore\Controller\Payment
{
    public function decide(array $params, string $type): bool
    {
        return $this->isCloudPaymentsSuccessNotification($params, $type);
    }
}
