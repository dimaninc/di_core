<?php

namespace diCore\Tests\Helper;

use diCore\Helper\StringHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Driver and exception messages embed the connection URI, credentials included.
 */
class ScrubUriCredentialsTest extends TestCase
{
    #[DataProvider('credentialProvider')]
    public function testCredentialsNeverSurvive(
        string $message,
        string $secret
    ): void {
        $scrubbed = StringHelper::scrubUriCredentials($message);

        $this->assertStringNotContainsString($secret, $scrubbed);
        $this->assertStringContainsString('***', $scrubbed);
    }

    public static function credentialProvider(): array
    {
        return [
            'plain' => ['fail mongodb://user:secret@host:27017/db', 'secret'],
            'encoded at' => ['fail mongodb://user:se%40cret@host/db', 'se%40cret'],
            'encoded slash' => ['fail mongodb://u:se%2Fcret@host/db', 'se%2Fcret'],
            'user only' => ['fail mongodb://secretuser@host/db', 'secretuser'],
            'stray e-mail after the uri' => [
                'mongodb://u:hunter2@host:27017/db failed, notify admin@example.com',
                'hunter2',
            ],
            'two uris' => [
                'no servers for mongodb://u:hunter2@a.host/db; also mongodb://u:hunter2@b.host/db',
                'hunter2',
            ],
        ];
    }

    public function testDiagnosticsAroundTheCredentialsSurvive(): void
    {
        // A greedy match to the LAST '@' would swallow the hosts and the trailing
        // text, leaving a safe but useless log line.
        $scrubbed = StringHelper::scrubUriCredentials(
            'no servers for mongodb://u:pw@a.host/db; also mongodb://u:pw@b.host/db'
        );

        $this->assertStringContainsString('a.host', $scrubbed);
        $this->assertStringContainsString('b.host', $scrubbed);
    }

    public function testAStrayEmailIsLeftAlone(): void
    {
        $scrubbed = StringHelper::scrubUriCredentials(
            'mongodb://u:pw@host:27017/db failed, notify admin@example.com'
        );

        $this->assertStringContainsString('host:27017/db', $scrubbed);
        $this->assertStringContainsString('admin@example.com', $scrubbed);
    }

    public function testACleanUriStaysDiagnosable(): void
    {
        $message = 'No suitable servers found for mongodb://host:27017/db';

        $this->assertSame($message, StringHelper::scrubUriCredentials($message));
    }
}
