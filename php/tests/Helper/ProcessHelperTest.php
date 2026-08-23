<?php

namespace diCore\Tests\Helper;

use diCore\Helper\ProcessHelper;
use PHPUnit\Framework\TestCase;

/**
 * Зависший внешний процесс под FPM держит воркера до упора — set_time_limit()
 * на Unix не считает время в системных вызовах и не спасает.
 */
class ProcessHelperTest extends TestCase
{
    public function testCollectsStdoutAndExitCode(): void
    {
        $r = ProcessHelper::run(['echo', 'hello'], 5.0);

        $this->assertSame(0, $r['code']);
        $this->assertSame(['hello'], $r['output']);
        $this->assertFalse($r['timedOut']);
    }

    /** Причину утилиты пишут в stderr — потерять его значит потерять диагноз */
    public function testCollectsStderrToo(): void
    {
        $r = ProcessHelper::run('echo oops 1>&2; exit 3', 5.0);

        $this->assertSame(3, $r['code']);
        $this->assertSame(['oops'], $r['output']);
    }

    public function testNonZeroExitIsReported(): void
    {
        $r = ProcessHelper::run(['false'], 5.0);

        $this->assertNotSame(0, $r['code']);
        $this->assertFalse($r['timedOut']);
    }

    public function testMissingBinaryDoesNotThrow(): void
    {
        $r = ProcessHelper::run(['definitely-not-a-binary-9d4f'], 5.0);

        $this->assertNotSame(0, $r['code']);
        $this->assertFalse($r['timedOut']);
    }

    /**
     * Главное свойство: возврат управления не позже дедлайна, а не «когда
     * процесс сам закончит».
     */
    public function testHangingProcessIsKilledAtTheDeadline(): void
    {
        $startedAt = microtime(true);
        $r = ProcessHelper::run(['sleep', '10'], 0.3);
        $elapsed = microtime(true) - $startedAt;

        $this->assertTrue($r['timedOut']);
        $this->assertSame(ProcessHelper::EXIT_TIMED_OUT, $r['code']);
        $this->assertLessThan(
            5.0,
            $elapsed,
            'вернулись по дедлайну, а не дождались процесса'
        );
    }

    /**
     * Одиночную строковую команду шелл подменяет собой (`exec`), поэтому сигнал
     * доходит до настоящего процесса, а не до обёртки.
     */
    public function testSimpleStringCommandIsKilledToo(): void
    {
        $startedAt = microtime(true);
        $r = ProcessHelper::run('sleep 10', 0.3);
        $elapsed = microtime(true) - $startedAt;

        $this->assertTrue($r['timedOut']);
        $this->assertLessThan(5.0, $elapsed);
    }

    /**
     * У составной строки префикс `exec` отобрал бы смысл (`exec a; b` не
     * выполнит b), поэтому она идёт шеллу как есть. Убитым окажется шелл, но
     * управление всё равно возвращается по дедлайну — это и есть контракт.
     */
    public function testCompoundStringKeepsItsMeaningAndStillReturnsOnTime()
    {
        $r = ProcessHelper::run('echo one; echo two', 5.0);

        $this->assertSame(0, $r['code']);
        $this->assertSame(['one', 'two'], $r['output']);

        $startedAt = microtime(true);
        $r = ProcessHelper::run('echo started; sleep 10', 0.4);

        $this->assertTrue($r['timedOut']);
        $this->assertLessThan(5.0, microtime(true) - $startedAt);
    }

    /** Вывод, накопленный до убийства, не должен теряться */
    public function testOutputBeforeTheTimeoutSurvives(): void
    {
        $r = ProcessHelper::run('echo early; sleep 10', 0.5);

        $this->assertTrue($r['timedOut']);
        $this->assertSame(['early'], $r['output']);
    }

    /** Труба на 64кб+ вывода не должна вешать процесс насмерть */
    public function testLargeOutputDoesNotDeadlock(): void
    {
        $r = ProcessHelper::run(
            'for i in $(seq 1 20000); do echo 0123456789012345678901234567890123456789; done',
            10.0
        );

        $this->assertFalse($r['timedOut']);
        $this->assertSame(0, $r['code']);
        $this->assertCount(20000, $r['output']);
    }

    public function testExhaustedBudgetTimesOutImmediately(): void
    {
        $r = ProcessHelper::run(['sleep', '10'], 0.0);

        $this->assertTrue($r['timedOut']);
    }
}
