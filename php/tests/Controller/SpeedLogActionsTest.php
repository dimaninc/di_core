<?php

namespace diCore\Tests\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Собственный slow-speed порог экшена. У загрузки файла несколько секунд —
 * норма, и с общим порогом она пишет slow-запись на каждый запрос; заглушить
 * её целиком нельзя, иначе невидимым станет и настоящее зависание, а это ровно
 * тот класс запросов, который исчерпывает pm.max_children.
 */
class SpeedLogActionsTest extends TestCase
{
    protected function setUp(): void
    {
        SlowUploadController::setRoutedAction(null);
    }

    /** null = общий Environment::slowSpeedValue, ничего не переопределяем */
    public function testControllerWithoutOverridesAnswersNull(): void
    {
        $this->assertNull(PlainController::slowSpeedValueForAction('upload'));
        $this->assertNull(PlainController::slowSpeedValueForAction(''));
        $this->assertNull(PlainController::slowSpeedValueForAction(null));
    }

    public function testListedActionGetsItsOwnThreshold(): void
    {
        $this->assertSame(
            25.0,
            SlowUploadController::slowSpeedValueForAction('upload')
        );
    }

    /** Целое в константе не должно приезжать в Logger как int */
    public function testThresholdIsAlwaysFloat(): void
    {
        $this->assertSame(
            30.0,
            SlowUploadController::slowSpeedValueForAction('export')
        );
    }

    public function testOtherActionsOfTheSameControllerKeepTheGlobalThreshold(): void
    {
        $this->assertNull(SlowUploadController::slowSpeedValueForAction('create'));
        $this->assertNull(SlowUploadController::slowSpeedValueForAction(''));
    }

    public function testMatchIsExactSoNoActionIsRetunedByAccident(): void
    {
        $this->assertNull(SlowUploadController::slowSpeedValueForAction('uploads'));
        $this->assertNull(SlowUploadController::slowSpeedValueForAction('Upload'));
        // '0' == '' при нестрогом сравнении — так чужой экшен получил бы порог
        $this->assertNull(SlowUploadController::slowSpeedValueForAction('0'));
    }

    /**
     * createAttempt() закрывает запрос, не зная имени экшена, и берёт его из
     * $routedAction, который autoCreate() выставил перед созданием контроллера.
     */
    public function testFallsBackToTheRoutedActionWhenNoneIsPassed(): void
    {
        $this->assertNull(SlowUploadController::slowSpeedValueForAction());

        SlowUploadController::setRoutedAction('upload');
        $this->assertSame(25.0, SlowUploadController::slowSpeedValueForAction());

        SlowUploadController::setRoutedAction('create');
        $this->assertNull(SlowUploadController::slowSpeedValueForAction());
    }

    /**
     * $routedAction физически один на всю иерархию (объявлен только в базе).
     * Проверяем то, что из этого следует: свой список решает, чужой маршрут нет.
     */
    public function testRoutedActionDoesNotLeakBetweenControllers(): void
    {
        SlowUploadController::setRoutedAction('upload');

        $this->assertNull(OtherSlowController::slowSpeedValueForAction());
        $this->assertNull(PlainController::slowSpeedValueForAction());

        SlowUploadController::setRoutedAction('render');
        $this->assertSame(12.0, OtherSlowController::slowSpeedValueForAction());
    }
}

class PlainController extends \diBaseController {}

class OtherSlowController extends \diBaseController
{
    const SLOW_SPEED_ACTIONS = ['render' => 12.0];
}

class SlowUploadController extends \diBaseController
{
    const SLOW_SPEED_ACTIONS = ['upload' => 25.0, 'export' => 30];

    /** @param string|null $action */
    public static function setRoutedAction($action): void
    {
        static::$routedAction = $action;
    }
}
