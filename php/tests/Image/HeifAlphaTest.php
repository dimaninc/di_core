<?php

namespace diCore\Tests\Image;

use PHPUnit\Framework\TestCase;

/**
 * JPEG без альфы: конвертер, который её просто отбрасывает, делает прозрачное
 * чёрным – логотип или вырезанный товар портится без ошибки и строки в логе.
 * Каждый конвертер обязан класть альфу на белый фон.
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

    /** Конвертер, которого на машине нет, – повод пропустить, а не упасть */
    private function runOrSkip(string $converter, string $src, string $dst): void
    {
        try {
            $ok = HeifAlphaSpy::runReal($converter, $src, $dst);
        } catch (\Exception $e) {
            $this->markTestSkipped("$converter недоступен: {$e->getMessage()}");
        }

        $this->assertTrue($ok);
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

    public function testGdFlattenPutsAlphaOnWhite(): void
    {
        $jpeg = $this->tmp('jpg');

        $this->assertTrue(HeifAlphaSpy::flatten($this->alphaPng(), $jpeg));
        $this->assertWhiteCornerRedCentre($jpeg, 20);
    }

    public function testImagickPutsAlphaOnWhite(): void
    {
        if (!class_exists('Imagick')) {
            $this->markTestSkipped('ext-imagick не установлен');
        }

        $jpeg = $this->tmp('jpg');
        $this->runOrSkip(\diImage::HEIC_CONVERTER_IMAGICK, $this->alphaPng(), $jpeg);
        $this->assertWhiteCornerRedCentre($jpeg, 20);
    }

    public function testImageMagickCliPutsAlphaOnWhite(): void
    {
        $jpeg = $this->tmp('jpg');
        $this->runOrSkip(\diImage::HEIC_CONVERTER_MAGICK, $this->alphaPng(), $jpeg);
        $this->assertWhiteCornerRedCentre($jpeg, 20);
    }

    /** Настоящий AVIF 200×200: прозрачный фон, красный круг в центре */
    public function testHeifConvertPutsAvifAlphaOnWhite(): void
    {
        $jpeg = $this->tmp('jpg');
        $this->runOrSkip(
            \diImage::HEIC_CONVERTER_HEIF,
            self::AVIF_WITH_ALPHA,
            $jpeg
        );
        $this->assertWhiteCornerRedCentre($jpeg, 100);
        $this->assertSame(
            [],
            glob("$jpeg.alpha*") ?: [],
            'промежуточный PNG остался'
        );
    }
}

class HeifAlphaSpy extends \diImage
{
    public static function runReal(string $converter, string $src, string $dst): bool
    {
        return static::runHeicConverter($converter, $src, $dst, 10.0);
    }

    public static function flatten(string $png, string $jpeg): bool
    {
        return static::flattenPngToJpeg($png, $jpeg);
    }
}
