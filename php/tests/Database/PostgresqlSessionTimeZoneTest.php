<?php

namespace diCore\Tests\Database;

use diCore\Database\Legacy\Postgresql;
use PHPUnit\Framework\TestCase;

/**
 * The Postgres session runs in PHP's zone. The sql/postgres dumps use timestamptz, and
 * PHP writes time as a string without an offset: under the server's zone such a value
 * came back shifted by the difference between the two zones.
 *
 * Live: DI_CORE_TEST_DB=postgresql://user:pass@host:port/db, skipped otherwise.
 */
class PostgresqlSessionTimeZoneTest extends TestCase
{
    private $zone;

    protected function setUp(): void
    {
        $dsn = getenv('DI_CORE_TEST_DB');

        if (!$dsn || parse_url($dsn, PHP_URL_SCHEME) !== 'postgresql') {
            $this->markTestSkipped('Needs DI_CORE_TEST_DB=postgresql://…');
        }

        $this->zone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        if ($this->zone) {
            date_default_timezone_set($this->zone);
        }
    }

    private function connect(): Postgresql
    {
        $p = parse_url(getenv('DI_CORE_TEST_DB'));

        return new Postgresql([
            'host' => $p['host'],
            'port' => $p['port'] ?? 5432,
            'username' => rawurldecode($p['user'] ?? ''),
            'password' => rawurldecode($p['pass'] ?? ''),
            'dbname' => ltrim($p['path'] ?? '', '/'),
        ]);
    }

    public function testSessionZoneIsPhpZone(): void
    {
        // A zone no server is likely to run in, so a default can't pass by chance.
        date_default_timezone_set('Asia/Kamchatka');
        $db = $this->connect();

        $this->assertSame('Asia/Kamchatka', $db->fetch_array($db->q('SHOW TIME ZONE'))['TimeZone']);
    }

    public function testPhpWrittenTimeReadsBackAsTheSameMoment(): void
    {
        date_default_timezone_set('Asia/Kamchatka');
        $db = $this->connect();
        $written = \diDateTime::sqlFormat('2026-01-15 10:00:00');

        $row = $db->fetch_array($db->q(
            "SELECT '$written'::timestamptz AS t, now() - CURRENT_TIMESTAMP AS zero, " .
                "to_char(now(), 'YYYY-MM-DD HH24') AS db_hour"
        ));

        $this->assertSame(strtotime($written), strtotime($row['t']));
        // DEFAULT CURRENT_TIMESTAMP and PHP's clock agree in the same zone too.
        $this->assertSame(date('Y-m-d H'), $row['db_hour']);
    }
}
