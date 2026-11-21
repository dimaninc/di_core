<?php

namespace diCore\Tests\Tool;

use diCore\Vendor\Blueimp\FileUpload\UploadHandler;
use PHPUnit\Framework\TestCase;

/**
 * `user_dirs` named the folder after session_id(): rotated at sign-in, it lost
 * the files uploaded before, and the name was reproducible from the old id.
 */
class UploadUserDirTest extends TestCase
{
    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        // the constructor builds its URL from these
        $_SERVER += ['SERVER_NAME' => 'site.example', 'SERVER_PORT' => '443'];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_SESSION = [];
    }

    private function userId(UploadHandler $h): string
    {
        return (fn() => $this->get_user_id())->call($h);
    }

    public function testDirSurvivesIdRotationAndIsNotTheId(): void
    {
        $h = new UploadHandler(null, false);

        $first = $this->userId($h);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}\z/', $first);
        $this->assertNotSame((string) session_id(), $first);
        // the data survives session_regenerate_id(), so does the name
        $this->assertSame($first, $this->userId(new UploadHandler(null, false)));
    }
}
