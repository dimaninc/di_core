<?php

namespace diCore\Tests\Queue;

use PHPUnit\Framework\TestCase;

/**
 * forPdo() on an entry point without the CMS (a light webhook intake): diLib is not loaded there,
 * and the factory must neither need it nor drop the Setup it was given.
 */
final class QueueFactoryTest extends TestCase
{
    public function testForPdoWorksWithoutTheCms(): void
    {
        // The consumer's Composer autoloader – whichever loaded this test.
        $loader = (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName();
        $out = shell_exec(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/no-cms.php') . ' ' .
                escapeshellarg(dirname($loader, 2) . '/autoload.php') . ' 2>&1'
        );

        $this->assertSame(
            ['diLib' => false, 'class' => \diCore\Queue\PgQueue::class, 'queues' => (new TestSetup())->queues()],
            json_decode((string) $out, true),
            (string) $out
        );
    }
}
