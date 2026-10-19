<?php

namespace diCore\Tests\Image;

use PHPUnit\Framework\TestCase;

/**
 * JPEG без альфы: конвертер, который её просто отбрасывает, делает прозрачное
 * чёрным – логотип или вырезанный товар портится без ошибки и строки в логе.
 * ImageMagick-конвертеры обязаны класть альфу на белый фон.
 */
class HeifAlphaTest extends TestCase
{
    private const AVIF_WITH_ALPHA = __DIR__ . '/fixtures/alpha.avif';

    /** @var string[] */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }

        $this->files = [];
    }

    private function tmp(string $ext): string
    {
        $fn = sys_get_temp_dir() . '/heif-alpha-' . uniqid() . '.' . $ext;
        $this->files[] = $fn;

        return $fn;
    }

    /** Прозрачный фон и непрозрачный красный квадрат в центре */
    private function alphaPng(): string
    {
        $png = $this->tmp('png');
        $im = imagecreatetruecolor(40, 40);
        imagesavealpha($im, true);
        imagealphablending($im, false);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagefilledrectangle(
            $im,
            10,
            10,
            29,
            29,
            imagecolorallocate($im, 255, 0, 0)
        );
        imagepng($im, $png);
        imagedestroy($im);

        return $png;
    }

    /** @return int[] r, g, b */
    private function pixel(string $jpeg, int $x, int $y): array
    {
        $im = imagecreatefromjpeg($jpeg);
        $rgb = imagecolorat($im, $x, $y);
        imagedestroy($im);

        return [($rgb >> 16) & 0xff, ($rgb >> 8) & 0xff, $rgb & 0xff];
    }

    private function assertWhiteCornerRedCentre(string $jpeg, int $centre): void
    {
        [$r, $g, $b] = $this->pixel($jpeg, 1, 1);
        $this->assertGreaterThan(
            240,
            min($r, $g, $b),
            "угол $r,$g,$b вместо белого"
        );

        [$r, $g, $b] = $this->pixel($jpeg, $centre, $centre);
        $this->assertGreaterThan(200, $r);
        $this->assertLessThan(60, max($g, $b));
    }

    /**
     * Пропуск решается ДО запуска: отказ уже запущенного конвертера (порядок     * аргументов, сломанный делегат) – провал, а не «недоступен»
     */
    private function requireConverter(string $converter, bool $avif): void
    {
        $missing = match ($converter) {
            \diImage::HEIC_CONVERTER_IMAGICK => !class_exists('Imagick')
                ? 'ext-imagick не установлен'
                : ($avif && !\Imagick::queryFormats('AVIF')
                    ? 'у ext-imagick нет делегата AVIF'
                    : null),
            \diImage::HEIC_CONVERTER_MAGICK => !$this->hasBinary(
                \diCore\Data\Config::isMac() ? 'magick' : 'convert'
            )
                ? 'нет ImageMagick CLI'
                : ($avif && !$this->cliMagickReadsAvif()
                    ? 'у ImageMagick CLI нет делегата AVIF'
                    : null),
        };

        if ($missing) {
            $this->markTestSkipped($missing);
        }
    }

    private function hasBinary(string $name): bool
    {
        exec('command -v ' . escapeshellarg($name), $out, $code);

        return $code === 0;
    }

    private function cliMagickReadsAvif(): bool
    {
        $bin = \diCore\Data\Config::isMac() ? 'magick' : 'convert';
        exec("$bin -list format 2>/dev/null", $out);

        return (bool) preg_grep('/^\s*AVIF\*?\s+\S+\s+r/', $out);
    }

    private function convert(string $converter, string $src, bool $avif): string
    {
        $this->requireConverter($converter, $avif);
        $jpeg = $this->tmp('jpg');
        $this->assertTrue(HeifAlphaSpy::runReal($converter, $src, $jpeg));

        return $jpeg;
    }

    public function testAlphaIsFoundByItsAuxUrn(): void
    {
        $this->assertTrue(\diImage::hasHeifAlpha(self::AVIF_WITH_ALPHA));

        $depth = $this->tmp('heic');
        file_put_contents(
            $depth,
            "....ftypheic\0\0\0\0urn:mpeg:hevc:2015:auxid:2\0"
        );
        $this->assertFalse(\diImage::hasHeifAlpha($depth), 'depth-карта – не альфа');

        $hevcAlpha = $this->tmp('heic');
        file_put_contents(
            $hevcAlpha,
            "....ftypheic\0\0\0\0urn:mpeg:hevc:2015:auxid:1\0"
        );
        $this->assertTrue(\diImage::hasHeifAlpha($hevcAlpha));

        $this->assertFalse(\diImage::hasHeifAlpha('/nonexistent/file.avif'));
    }

    public function testImagickPutsAlphaOnWhite(): void
    {
        $jpeg = $this->convert(
            \diImage::HEIC_CONVERTER_IMAGICK,
            $this->alphaPng(),
            false
        );
        $this->assertWhiteCornerRedCentre($jpeg, 20);
    }

    public function testImagickPutsAvifAlphaOnWhite(): void
    {
        $jpeg = $this->convert(
            \diImage::HEIC_CONVERTER_IMAGICK,
            self::AVIF_WITH_ALPHA,
            true
        );
        $this->assertWhiteCornerRedCentre($jpeg, 100);
    }

    public function testImageMagickCliPutsAlphaOnWhite(): void
    {
        $jpeg = $this->convert(
            \diImage::HEIC_CONVERTER_MAGICK,
            $this->alphaPng(),
            false
        );
        $this->assertWhiteCornerRedCentre($jpeg, 20);
    }

    public function testImageMagickCliPutsAvifAlphaOnWhite(): void
    {
        $jpeg = $this->convert(
            \diImage::HEIC_CONVERTER_MAGICK,
            self::AVIF_WITH_ALPHA,
            true
        );
        $this->assertWhiteCornerRedCentre($jpeg, 100);
    }

    /**
     * heif-convert альфу не кладёт на фон, поэтому для файла с ней идёт
     * последним: ImageMagick сначала, чёрный фон – только если он не смог
     */
    public function testAlphaSourceTriesHeifConvertLast(): void
    {
        $this->assertSame(
            [
                \diImage::HEIC_CONVERTER_IMAGICK,
                \diImage::HEIC_CONVERTER_MAGICK,
                \diImage::HEIC_CONVERTER_HEIF,
            ],
            HeifAlphaSpy::orderFor(self::AVIF_WITH_ALPHA)
        );
    }

    public function testOpaqueSourceKeepsTheHostOrder(): void
    {
        $opaque = $this->tmp('avif');
        file_put_contents($opaque, "....ftypavif\0\0\0\0mif1");

        $this->assertSame(
            HeifAlphaSpy::LINUX_ORDER,
            HeifAlphaSpy::orderFor($opaque)
        );
    }
}

class HeifAlphaSpy extends \diImage
{
    const LINUX_ORDER = [
        self::HEIC_CONVERTER_HEIF,
        self::HEIC_CONVERTER_IMAGICK,
        self::HEIC_CONVERTER_MAGICK,
    ];

    public static function getHeicConverterOrder(): array
    {
        return self::LINUX_ORDER;
    }

    /** @return string[] */
    public static function orderFor(string $src): array
    {
        return static::getHeifConverterOrderFor($src);
    }

    public static function runReal(string $converter, string $src, string $dst): bool
    {
        return static::runHeicConverter($converter, $src, $dst, 10.0);
    }
}
