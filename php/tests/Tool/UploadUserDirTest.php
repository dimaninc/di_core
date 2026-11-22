<?php

namespace diCore\Tests\Tool;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;

/**
 * `user_dirs` named the folder after session_id(): rotated at sign-in, it lost
 * the files uploaded before, and the name was reproducible from the old id.
 * Runs in a child php: PHPUnit has already sent output, so no session can be
 * started (and no id rotated) in-process.
 */
class UploadUserDirTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir =
            sys_get_temp_dir() . '/di-upload-dir-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    public function testDirSurvivesIdRotationAndIsNotTheId(): void
    {
        $autoload =
            dirname((new \ReflectionClass(ClassLoader::class))->getFileName(), 2) .
            '/autoload.php';

        $script = <<<'PHP'
        [, $autoload, $diLib, $dir] = $argv;
        require $autoload;
        require $diLib;
        // the constructor builds its URL from these
        $_SERVER += ['SERVER_NAME' => 'site.example', 'SERVER_PORT' => '443'];
        ini_set('session.save_path', $dir);
        session_id('plantedid0001abc');
        session_start();
        $dirOf = fn() => (fn() => $this->get_user_id())->call(
            new \diCore\Vendor\Blueimp\FileUpload\UploadHandler(null, false)
        );
        $before = ['id' => session_id(), 'dir' => $dirOf()];
        $ok = \diSession::regenerate();
        echo json_encode([
            'ok' => $ok,
            'before' => $before,
            'after' => ['id' => session_id(), 'dir' => $dirOf()],
        ]), "\nSENTINEL";
        PHP;

        $cmd = [
            PHP_BINARY,
            '-d',
            'display_errors=stderr',
            '-r',
            $script,
            $autoload,
            dirname(__DIR__, 2) . '/lib/diLib.php',
            $this->dir,
        ];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        $this->assertSame(0, proc_close($proc), $err);
        $this->assertStringEndsWith('SENTINEL', $out, $err);

        $r = json_decode(strstr($out, "\nSENTINEL", true), true);

        $this->assertTrue($r['ok']);
        $this->assertNotSame($r['before']['id'], $r['after']['id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}\z/', $r['before']['dir']);
        $this->assertNotSame($r['before']['id'], $r['before']['dir']);
        // files uploaded before sign-in stay reachable
        $this->assertSame($r['before']['dir'], $r['after']['dir']);
    }
}
