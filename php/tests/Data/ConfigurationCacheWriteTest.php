<?php

namespace diCore\Tests\Data;

use diCore\Data\Configuration;
use PHPUnit\Framework\TestCase;

/**
 * Кэш настроек подключается каждым запросом: подмена только целиком, без
 * мусора рядом, и сбой – предупреждение, а исключение лишь по просьбе.
 */
class ConfigurationCacheWriteTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir =
            sys_get_temp_dir() . '/di-cfg-cache-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        @chmod($this->dir, 0777);

        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->dir);
    }

    private function writer(string $dir): CacheWriterStub
    {
        // Без конструктора: он перезаписывает синглтон Configuration.
        $w = (new \ReflectionClass(
            CacheWriterStub::class
        ))->newInstanceWithoutConstructor();
        $w->file = $dir . '/configuration.php';

        return $w;
    }

    private function filesIn(string $dir): array
    {
        return array_map('basename', glob($dir . '/*') ?: []);
    }

    public function testReplacesFileWithoutLeftovers(): void
    {
        $w = $this->writer($this->dir);
        file_put_contents($w->file, 'old');

        $this->assertTrue($w->write('<?php // new'));
        $this->assertSame('<?php // new', file_get_contents($w->file));
        $this->assertSame(['configuration.php'], $this->filesIn($this->dir));
    }

    public function testUnwritableDirThrowsOnRequestAndLeavesNothing(): void
    {
        $w = $this->writer($this->dir);
        file_put_contents($w->file, 'old');
        chmod($w->file, 0666);
        // Файл писать можно, каталог – нет: временный файл рядом не создать, а
        // tempnam() в системном tmp отвергается (оттуда rename() не атомарен).
        chmod($this->dir, 0555);

        if (is_writable($this->dir)) {
            // root (docker) права каталога не останавливают – отказа не будет.
            $this->markTestSkipped('directory is still writable (running as root?)');
        }

        try {
            $w->write('new', true);
            $this->fail('expected an exception');
        } catch (\Exception $e) {
            $this->assertStringContainsString('configuration', $e->getMessage());
        }

        chmod($this->dir, 0777);
        $this->assertSame('old', file_get_contents($w->file));
        $this->assertSame(['configuration.php'], $this->filesIn($this->dir));
    }

    /** Отказ самого rename(): цель – каталог, файл поверх него не встанет. */
    public function testFailedRenameThrowsAndLeavesNoTempFile(): void
    {
        $w = $this->writer($this->dir);
        $w->file = $this->dir . '/configuration.php';
        mkdir($w->file);

        try {
            $w->write('new', true);
            $this->fail('expected an exception');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Unable to replace', $e->getMessage());
        }

        $this->assertDirectoryExists($w->file);
        $this->assertSame(['configuration.php'], $this->filesIn($this->dir));
        rmdir($w->file);
    }

    public function testFailureIsAWarningByDefault(): void
    {
        $w = $this->writer($this->dir . '/missing');
        $warnings = [];
        set_error_handler(function ($no, $msg) use (&$warnings) {
            $warnings[] = [$no, $msg];

            return true;
        });

        try {
            $result = $w->write('new');
        } finally {
            restore_error_handler();
        }

        $this->assertFalse($result);
        // Обработчик видит и подавленный `@` notice tempnam() – ищем своё.
        $this->assertContains(E_USER_WARNING, array_column($warnings, 0));
        $this->assertFileDoesNotExist($w->file);
    }
}

class CacheWriterStub extends Configuration
{
    public string $file;

    protected function getFullCacheFilename()
    {
        return $this->file;
    }

    public function write(string $content, bool $throw = false): bool
    {
        return $this->writeCacheFile($content, $throw);
    }
}
