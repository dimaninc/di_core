<?php

namespace diCore\Tests\Tool;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;

/**
 * Sign-in kept the session id, and the id can be chosen by the client
 * (X-Session): a planted id stayed signed in after the victim logged in.
 * Runs in a child php: PHPUnit has already sent output, so no header and no
 * session_regenerate_id() could work in-process.
 */
class SessionRegenerateTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir =
            sys_get_temp_dir() . '/di-session-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    private function runChild(): array
    {
        $autoload =
            dirname((new \ReflectionClass(ClassLoader::class))->getFileName(), 2) .
            '/autoload.php';

        $script = <<<'PHP'
        [, $autoload, $diLib, $dir] = $argv;
        require $autoload;
        require $diLib;
        ini_set('session.save_path', $dir);
        session_id('plantedid0001abc');
        session_start();
        $_SESSION['payload'] = 'kept';
        $before = ['id' => session_id(), 'int' => \diSession::shortIntId()];
        $ok = \diSession::regenerate();
        session_write_close();
        echo json_encode([
            'ok' => $ok,
            'before' => $before,
            'after' => ['id' => session_id(), 'int' => \diSession::shortIntId()],
            'payload' => $_SESSION['payload'] ?? null,
            'oldFile' => file_exists($dir . '/sess_' . $before['id']),
        ]), "\nSENTINEL";
        PHP;

        $cmd = [
            PHP_BINARY,
            '-d',
            'display_errors=stderr',
            '-d',
            'error_reporting=-1',
            '-r',
            $script,
            $autoload,
            dirname(__DIR__, 2) . '/lib/diLib.php',
            $this->dir,
        ];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        $code = proc_close($proc);

        $this->assertSame(0, $code, $err);
        $this->assertStringEndsWith('SENTINEL', $out, $err);

        return json_decode(strstr($out, "\nSENTINEL", true), true);
    }

    public function testIdChangesDataAndIntIdSurvive(): void
    {
        $r = $this->runChild();

        $this->assertTrue($r['ok']);
        $this->assertNotSame($r['before']['id'], $r['after']['id']);
        $this->assertFalse($r['oldFile'], 'the planted id must not stay usable');
        $this->assertSame('kept', $r['payload']);
        // cards and drafts made before sign-in are keyed on it
        $this->assertSame($r['before']['int'], $r['after']['int']);
    }
}
