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

    public static function reset(): void
    {
        static::$order = [];
        static::$handlers = [];
        static::$tried = [];
        static::$logged = [];
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
        return parent::runHeicConverter($converter, $heicFile, $jpegFile);
    }

    public static function outputIsUsable(string $jpegFile): bool
    {
        return static::heicOutputIsUsable($jpegFile);
    }

    protected static function runHeicConverter(
        string $converter,
        string $heicFile,
        string $jpegFile
    ): bool {
        static::$tried[] = $converter;

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
