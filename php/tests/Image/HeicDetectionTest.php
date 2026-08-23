<?php

namespace diCore\Tests\Image;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Не опознанный HEIC не конвертируется, дальше getimagesize() отдаёт 0x0, и в
 * card_pics уезжает картинка нулевого размера — без исключения и без строки в
 * логе. Поэтому детект читает ftyp сам, а не спрашивает finfo, чья база magic
 * разная на маке и на проде.
 */
class HeicDetectionTest extends TestCase
{
    /** @var string[] */
    private $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }

        $this->files = [];
    }

    private function write(string $bytes): string
    {
        $fn = sys_get_temp_dir() . '/ftyp-' . uniqid() . '.bin';
        file_put_contents($fn, $bytes);
        $this->files[] = $fn;

        return $fn;
    }

    /** @param string[] $compatible */
    private function ftyp(string $major, array $compatible = []): string
    {
        $payload = $major . "\x00\x00\x00\x00" . join('', $compatible);
        $size = 8 + strlen($payload);

        return pack('N', $size) . 'ftyp' . $payload . str_repeat("\x00", 32);
    }

    #[DataProvider('heifBrandProvider')]
    public function testHeifBrandsAreRecognised(string $brand): void
    {
        $this->assertTrue(\diImage::isHeic($this->write($this->ftyp($brand))));
    }

    public static function heifBrandProvider(): array
    {
        return [
            'обычное фото с айфона' => ['heic'],
            'heic с альфой' => ['heix'],
            'сетка тайлов' => ['heim'],
            'heis' => ['heis'],
            'серия (live photo / бёрст)' => ['hevc'],
            'серия с альфой' => ['hevx'],
            'hevm' => ['hevm'],
            'hevs' => ['hevs'],
            'общий HEIF-образ' => ['mif1'],
            'общая HEIF-серия' => ['msf1'],
        ];
    }

    /**
     * Камера может положить в major brand что угодно, а `heic` оставить в
     * списке совместимых — прежний детект по одному mime такой файл терял.
     */
    public function testCompatibleBrandIsEnough(): void
    {
        $file = $this->write($this->ftyp('mp42', ['isom', 'heic']));

        $this->assertTrue(\diImage::isHeic($file));
    }

    #[DataProvider('foreignProvider')]
    public function testNonHeifFilesAreLeftAlone(string $bytes): void
    {
        $this->assertFalse(\diImage::isHeic($this->write($bytes)));
    }

    public static function foreignProvider(): array
    {
        return [
            // mp4/mov разрешены как pic и обрабатываются извлечением кадра —
            // принять их за HEIC значит сломать этот путь
            'mp4' => [
                pack('N', 24) . 'ftyp' . 'isom' . "\x00\x00\x02\x00" . 'isomiso2',
            ],
            'quicktime' => [pack('N', 20) . 'ftyp' . 'qt  ' . "\x00\x00\x02\x00"],

            'jpeg' => ["\xff\xd8\xff\xe0" . str_repeat("\x00", 32)],
            'png' => ["\x89PNG\r\n\x1a\n" . str_repeat("\x00", 32)],
            'мусор' => [str_repeat('x', 40)],
            'обрезано до заголовка' => ['ftyp'],
            'пустой' => [''],
        ];
    }

    /**
     * AVIF — тоже MIAF и объявляет `mif1` совместимым, то есть по одному
     * `mif1` от HEIF неотличим. Раскладка ровно как у avifenc: первая версия
     * этого теста подсовывала 16-байтный ftyp без совместимых брендов, он был
     * зелёным — и пропускал настоящий AVIF в HEIC-конвертер.
     */
    #[DataProvider('avifProvider')]
    public function testRealAvifIsNotMistakenForHeif(string $major): void
    {
        $file = $this->write($this->ftyp($major, ['mif1', 'miaf', 'MA1B']));

        $this->assertFalse(\diImage::isHeic($file));
    }

    public static function avifProvider(): array
    {
        return [
            'кадр' => ['avif'],
            'последовательность' => ['avis'],
        ];
    }

    public function testMissingFileIsNotHeic(): void
    {
        $this->assertFalse(\diImage::isHeic('/tmp/definitely-missing-4f2a.heic'));
    }

    /**
     * Объявленный размер бокса — из файла, то есть недоверенный: он не должен
     * уводить чтение за пределы того, что реально прочитано.
     */
    public function testDishonestBoxSizeDoesNotOverrun(): void
    {
        $bytes = pack('N', 0xffffff) . 'ftyp' . 'heic' . "\x00\x00\x00\x00";

        $this->assertTrue(\diImage::isHeic($this->write($bytes)));
    }

    /** size == 1 — дальше 8-байтный largesize, major brand сдвинут на 16 */
    public function testSixtyFourBitBoxSizeIsUnderstood(): void
    {
        $bytes =
            pack('N', 1) .
            'ftyp' .
            pack('J', 32) .
            'heic' .
            "\x00\x00\x00\x00" .
            'mif1';

        $this->assertTrue(\diImage::isHeic($this->write($bytes)));
    }

    /** size == 0 — бокс до конца файла, ограничения сверху нет */
    public function testZeroBoxSizeReadsToTheEnd(): void
    {
        $bytes = pack('N', 0) . 'ftyp' . 'mp42' . "\x00\x00\x00\x00" . 'isomheic';

        $this->assertTrue(\diImage::isHeic($this->write($bytes)));
    }
}
