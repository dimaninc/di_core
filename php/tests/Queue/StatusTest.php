<?php

namespace diCore\Tests\Queue;

use diCore\Queue\Status;
use PHPUnit\Framework\TestCase;

/**
 * The container and the CHECK of sql/postgres/job.sql hold the same set of statuses.
 */
final class StatusTest extends TestCase
{
    public function testContainerMatchesTheCheck(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../../sql/postgres/job.sql');
        $this->assertSame(1, preg_match("/CHECK \(status IN \(([^)]*)\)\)/", $sql, $m));

        $check = array_map(fn($v) => trim($v, " '"), explode(',', $m[1]));
        $this->assertSame(array_keys(Status::$names), $check);
    }
}
