<?php

namespace diCore\Tests\Image;

use PHPUnit\Framework\TestCase;

/**
 * Конвертация HEIC перебирает конвертеры по очереди: падение одного больше не
 * означает, что человек не может загрузить фото с айфона.
 */
class HeicConversionTest extends TestCase
{
    /** @var string */
    private $dst;

    protected function setUp(): void
    {
        HeicConverterSpy::reset();

        $this->dst = sys_get_temp_dir() . '/heic-test-' . uniqid() . '.jpg';
    }

    protected function tearDown(): void
    {
        if (is_file($this->dst)) {
            unlink($this->dst);
        }
    }

    public function testEveryConverterIsTriedExactlyOnce(): void
    {
        $order = \diImage::getHeicConverterOrder();

        $this->assertSame(
            $order,
            array_unique($order),
            'один и тот же конвертер не должен пробоваться дважды'
        );
        $this->assertContains(
            \diImage::HEIC_CONVERTER_IMAGICK,
            $order,
            'ext-imagick — единственный путь без exec(), он обязан быть в списке'
        );
        $this->assertContains(\diImage::HEIC_CONVERTER_HEIF, $order);
        $this->assertContains(\diImage::HEIC_CONVERTER_MAGICK, $order);
    }

    public function testPlatformConverterGoesFirst(): void
    {
        $expected = \diCore\Data\Config::isMac()
            ? \diImage::HEIC_CONVERTER_MAGICK
            : \diImage::HEIC_CONVERTER_HEIF;

        $this->assertSame($expected, \diImage::getHeicConverterOrder()[0]);
    }

    public function testFallbackTakesOverWhenTheFirstConverterThrows(): void
    {
        HeicConverterSpy::$order = ['first', 'second'];
        HeicConverterSpy::$handlers = [
            'first' => function () {
                throw new \Exception('heif-convert: not found');
            },
            'second' => function () {
                return true;
            },
        ];

        $this->assertSame(
            $this->dst,
            HeicConverterSpy::convertHeicToJpeg('/tmp/whatever.heic', $this->dst)
        );
        $this->assertSame(['first', 'second'], HeicConverterSpy::$tried);
    }

    public function testFallbackTakesOverWhenTheFirstConverterWritesNothingUsable(): void
    {
        HeicConverterSpy::$order = ['first', 'second'];
        HeicConverterSpy::$handlers = [
            'first' => function () {
                return false;
            },
            'second' => function () {
                return true;
            },
        ];

        $this->assertSame(
            $this->dst,
            HeicConverterSpy::convertHeicToJpeg('/tmp/whatever.heic', $this->dst)
        );
    }

    public function testEarlierFailuresAreLoggedEvenWhenAFallbackSucceeds(): void
    {
        HeicConverterSpy::$order = ['first', 'second'];
        HeicConverterSpy::$handlers = [
            'first' => function () {
                throw new \Exception('no delegate for HEIC');
            },
            'second' => function () {
                return true;
            },
        ];

        HeicConverterSpy::convertHeicToJpeg('/tmp/whatever.heic', $this->dst);

        $this->assertCount(1, HeicConverterSpy::$logged);
        $this->assertStringContainsString(
            'converted by second',
            HeicConverterSpy::$logged[0]
        );
        $this->assertStringContainsString(
            'no delegate for HEIC',
            HeicConverterSpy::$logged[0]
        );
    }

    public function testNothingIsLoggedWhenTheFirstConverterWorks(): void
    {
        HeicConverterSpy::$order = ['first', 'second'];
        HeicConverterSpy::$handlers = [
            'first' => function () {
                return true;
            },
        ];

        HeicConverterSpy::convertHeicToJpeg('/tmp/whatever.heic', $this->dst);

        $this->assertSame(['first'], HeicConverterSpy::$tried);
        $this->assertSame([], HeicConverterSpy::$logged);
    }

    public function testExceptionCarriesTheReasonOfEveryAttempt(): void
    {
        HeicConverterSpy::$order = ['first', 'second', 'third'];
        HeicConverterSpy::$handlers = [
            'first' => function () {
                throw new \Exception('binary missing');
            },
            'second' => function () {
                throw new \Exception('no delegate');
            },
            'third' => function () {
                return false;
            },
        ];

        $e = null;

        try {
            HeicConverterSpy::convertHeicToJpeg('/tmp/whatever.heic', $this->dst);
        } catch (\Exception $caught) {
            $e = $caught;
        }

        $this->assertNotNull($e, 'ожидалось исключение');
        $this->assertStringContainsString('first: binary missing', $e->getMessage());
        $this->assertStringContainsString('second: no delegate', $e->getMessage());
        $this->assertStringContainsString(
            'third: no readable jpeg',
            $e->getMessage()
        );
        $this->assertSame([$e->getMessage()], HeicConverterSpy::$logged);
    }

    public function testBrokenLeftoverIsRemovedBeforeTheNextAttempt(): void
    {
        $dst = $this->dst;
        $seenBySecond = true;

        HeicConverterSpy::$order = ['first', 'second'];
        HeicConverterSpy::$handlers = [
            // конвертер вышел с нулём, но записал обрезанный файл
            'first' => function () use ($dst) {
                file_put_contents($dst, 'not a jpeg');

                return false;
            },
            'second' => function () use (&$seenBySecond, $dst) {
                $seenBySecond = is_file($dst);

                return false;
            },
        ];

        try {
            HeicConverterSpy::convertHeicToJpeg('/tmp/whatever.heic', $dst);
        } catch (\Exception $e) {
            // ожидаемо: не сработал ни один
        }

        $this->assertFalse(
            $seenBySecond,
            'следующий конвертер не должен видеть мусор от предыдущего'
        );
        $this->assertFileDoesNotExist($dst);
    }

    public function testUnusableOutputIsRejected(): void
    {
        file_put_contents($this->dst, 'not a jpeg');
        $this->assertFalse(HeicConverterSpy::outputIsUsable($this->dst));

        $this->assertFalse(
            HeicConverterSpy::outputIsUsable($this->dst . '-missing')
        );

        $image = imagecreatetruecolor(4, 3);
        imagejpeg($image, $this->dst);
        imagedestroy($image);

        $this->assertTrue(HeicConverterSpy::outputIsUsable($this->dst));
    }

    /**
     * heif-convert и ImageMagick на HEIC с несколькими top-level кадрами пишут
     * `out-1.jpg` вместо `out.jpg` и выходят с нулём — раньше это выглядело как
     * успех без файла.
     */
    public function testNumberedOutputIsAdoptedAsTheResult(): void
    {
        $base = substr($this->dst, 0, -4);

        file_put_contents("$base-1.jpg", 'primary');
        file_put_contents("$base-2.jpg", 'depth map');

        HeicConverterSpy::adoptNumbered($this->dst);

        $this->assertSame('primary', file_get_contents($this->dst));
        $this->assertFileDoesNotExist("$base-2.jpg");
        $this->assertFileDoesNotExist("$base-1.jpg");
    }

    /** ImageMagick нумерует с нуля, heif-convert с единицы */
    public function testLowestNumberWinsRegardlessOfTheFirstIndex(): void
    {
        $base = substr($this->dst, 0, -4);

        file_put_contents("$base-0.jpg", 'zero based');
        file_put_contents("$base-1.jpg", 'second frame');

        HeicConverterSpy::adoptNumbered($this->dst);

        $this->assertSame('zero based', file_get_contents($this->dst));
    }

    /** Сортировка числовая, а не строковая: -10 не должен обгонять -2 */
    public function testFramesAreOrderedNumerically(): void
    {
        $base = substr($this->dst, 0, -4);

        file_put_contents("$base-10.jpg", 'tenth');
        file_put_contents("$base-2.jpg", 'second');

        HeicConverterSpy::adoptNumbered($this->dst);

        $this->assertSame('second', file_get_contents($this->dst));
    }

    public function testNothingHappensWithoutNumberedOutput(): void
    {
        HeicConverterSpy::adoptNumbered($this->dst);

        $this->assertFileDoesNotExist($this->dst);
    }

    /** Соседние файлы с похожим именем не должны попадать под раздачу */
    public function testUnrelatedNeighboursAreLeftAlone(): void
    {
        $base = substr($this->dst, 0, -4);

        file_put_contents("$base-1.jpg", 'primary');
        file_put_contents("$base-x.jpg", 'not a frame');
        file_put_contents("$base-1.png", 'other format');

        HeicConverterSpy::adoptNumbered($this->dst);

        $this->assertSame('primary', file_get_contents($this->dst));
        $this->assertFileExists("$base-x.jpg");
        $this->assertFileExists("$base-1.png");

        unlink("$base-x.jpg");
        unlink("$base-1.png");
    }

    public function testLeftoversAreRemovedBetweenAttempts(): void
    {
        $base = substr($this->dst, 0, -4);

        file_put_contents($this->dst, 'broken');
        file_put_contents("$base-1.jpg", 'stray');

        HeicConverterSpy::removeLeftovers($this->dst);

        $this->assertFileDoesNotExist($this->dst);
        $this->assertFileDoesNotExist("$base-1.jpg");
    }

    /**
     * Бюджет один на всю цепочку: отдельный таймаут на каждый конвертер дал бы
     * утроенный худший случай — ровно то, ради чего таймаут и вводился.
     */
    public function testTimeoutBudgetIsSharedAndShrinks(): void
    {
        HeicConverterSpy::$order = ['first', 'second'];
        HeicConverterSpy::$handlers = [
            'first' => function () {
                usleep(30000);

                return false;
            },
            'second' => function () {
                return true;
            },
        ];

        HeicConverterSpy::convertHeicToJpeg('/tmp/whatever.heic', $this->dst);

        $this->assertCount(2, HeicConverterSpy::$budgets);
        $this->assertLessThanOrEqual(
            \diImage::HEIC_TOTAL_TIMEOUT_SEC,
            HeicConverterSpy::$budgets[0]
        );
        $this->assertLessThan(
            HeicConverterSpy::$budgets[0],
            HeicConverterSpy::$budgets[1],
            'второму конвертеру достаётся остаток, а не полный бюджет'
        );
    }

    public function testExhaustedBudgetIsRefusedRatherThanRunUnbounded(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('no time left');

        HeicConverterSpy::runRealConverterWithBudget(
            \diImage::HEIC_CONVERTER_HEIF,
            '/tmp/x.heic',
            $this->dst,
            0.0
        );
    }

    public function testUnknownConverterNameDoesNotFallThroughSilently(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('unknown converter');

        HeicConverterSpy::runRealConverter('nope', '/tmp/x.heic', $this->dst);
    }
}

/**
 * Подменяет и перебор конвертеров, и запись в лог: сама конвертация упирается
 * в exec() и наличие делегата, а проверяется здесь логика перебора.
 */
class HeicConverterSpy extends \diImage
{
    /** @var string[] */
    public static $order = [];

    /** @var callable[] */
    public static $handlers = [];

    /** @var string[] */
    public static $tried = [];

    /** @var string[] */
    public static $logged = [];

    /** @var float[] остаток общего бюджета, с которым звали каждый конвертер */
    public static $budgets = [];

    public static function reset(): void
    {
        static::$order = [];
        static::$handlers = [];
        static::$tried = [];
        static::$logged = [];
        static::$budgets = [];
    }

    public static function getHeicConverterOrder(): array
    {
        return static::$order;
    }

    /** Настоящий диспетчер, в обход подмены ниже */
    public static function runRealConverter(
        string $converter,
        string $heicFile,
        string $jpegFile
    ): bool {
        return parent::runHeicConverter($converter, $heicFile, $jpegFile, 5.0);
    }

    public static function adoptNumbered(string $jpegFile): void
    {
        static::adoptNumberedCliOutput($jpegFile);
    }

    public static function removeLeftovers(string $jpegFile): void
    {
        static::removeHeicLeftovers($jpegFile);
    }

    public static function runRealConverterWithBudget(
        string $converter,
        string $heicFile,
        string $jpegFile,
        float $timeoutSec
    ): bool {
        return parent::runHeicConverter(
            $converter,
            $heicFile,
            $jpegFile,
            $timeoutSec
        );
    }

    public static function outputIsUsable(string $jpegFile): bool
    {
        return static::heicOutputIsUsable($jpegFile);
    }

    protected static function runHeicConverter(
        string $converter,
        string $heicFile,
        string $jpegFile,
        float $timeoutSec
    ): bool {
        static::$tried[] = $converter;
        static::$budgets[] = $timeoutSec;

        $handler = static::$handlers[$converter] ?? null;

        if (!$handler) {
            throw new \Exception('not configured');
        }

        return (bool) $handler($heicFile, $jpegFile);
    }

    protected static function logHeic(string $message): void
    {
        static::$logged[] = $message;
    }
}
