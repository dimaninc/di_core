<?php

namespace diCore\Tests\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Пер-экшенный опт-аут из speed-лога: у контроллера бывает один заведомо
 * долгий экшен (загрузка файла), из-за которого slow-speed запись пишется на
 * каждый запрос и глушит собой настоящие тормоза.
 */
class SpeedLogActionsTest extends TestCase
{
    protected function setUp(): void
    {
        SkippingController::setRoutedAction(null);
    }

    public function testNothingIsSkippedByDefault(): void
    {
        $this->assertFalse(PlainController::isSpeedLogSkippedAction('upload'));
        $this->assertFalse(PlainController::isSpeedLogSkippedAction(''));
        $this->assertFalse(PlainController::isSpeedLogSkippedAction(null));
    }

    public function testListedActionIsSkipped(): void
    {
        $this->assertTrue(SkippingController::isSpeedLogSkippedAction('upload'));
    }

    public function testOtherActionsOfTheSameControllerAreKept(): void
    {
        $this->assertFalse(SkippingController::isSpeedLogSkippedAction('create'));
        $this->assertFalse(SkippingController::isSpeedLogSkippedAction(''));
    }

    public function testMatchIsExactSoNoActionIsSilencedByAccident(): void
    {
        $this->assertFalse(SkippingController::isSpeedLogSkippedAction('uploads'));
        $this->assertFalse(SkippingController::isSpeedLogSkippedAction('Upload'));
        // '0' == '' при нестрогом сравнении — из-за этого пропал бы чужой экшен
        $this->assertFalse(SkippingController::isSpeedLogSkippedAction('0'));
    }

    public function testConstructorGateFallsBackToTheRoutedAction(): void
    {
        // конструктор вызывается без аргумента: имя экшена туда не доезжает
        $this->assertFalse(SkippingController::isSpeedLogSkippedAction());

        SkippingController::setRoutedAction('upload');
        $this->assertTrue(SkippingController::isSpeedLogSkippedAction());

        SkippingController::setRoutedAction('create');
        $this->assertFalse(SkippingController::isSpeedLogSkippedAction());
    }

    public function testSkipListIsNotInheritedByUnrelatedControllers(): void
    {
        SkippingController::setRoutedAction('upload');

        $this->assertFalse(PlainController::isSpeedLogSkippedAction());
    }
}

class PlainController extends \diBaseController {}

class SkippingController extends \diBaseController
{
    const SKIP_SPEED_LOG_ACTIONS = ['upload'];

    /** @param string|null $action */
    public static function setRoutedAction($action): void
    {
        static::$routedAction = $action;
    }
}
