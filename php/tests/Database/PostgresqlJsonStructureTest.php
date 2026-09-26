<?php

namespace diCore\Tests\Database;

use diCore\Database\Legacy\Postgresql;
use PHPUnit\Framework\TestCase;

/**
 * Postgresql::getJsonForStructure() must escape the literal itself: diModel passes
 * a JSON field's structure through untouched, and a direct diDB::insert() never
 * escaped it at all – a quote in a value broke out of '…'::jsonb.
 *
 * No server needed: the literal is built with an SQLite PDO, whose quote() does
 * what PostgreSQL's does under standard_conforming_strings (the default since 9.1):
 * doubles ' and leaves backslashes alone.
 */
class PostgresqlJsonStructureTest extends TestCase
{
    private function makeDb(): Postgresql
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Needs pdo_sqlite to stand in for the link');
        }

        $db = (new \ReflectionClass(
            Postgresql::class
        ))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($db, 'link'))->setValue(
            $db,
            new \PDO('sqlite::memory:')
        );

        return $db;
    }

    private function build(Postgresql $db, array $value): string
    {
        return (new \ReflectionMethod($db, 'getJsonForStructure'))->invoke(
            $db,
            $value
        );
    }

    public function testQuoteCannotBreakOutOfTheLiteral(): void
    {
        $value = ['sql' => "x'); DROP TABLE t; --", 'nested' => ["it's", 'a\\b']];

        $literal = $this->build($this->makeDb(), $value);

        $this->assertMatchesRegularExpression(
            "/^'(?:[^']|'')*'::jsonb$/u",
            $literal
        );
        $body = substr($literal, 1, -strlen("'::jsonb"));
        $this->assertSame($value, json_decode(str_replace("''", "'", $body), true));
    }

    public function testUnencodableValueThrowsInsteadOfAnEmptyLiteral(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to encode a JSON field value');

        $this->build($this->makeDb(), ['bad' => "\xB1\x31"]);
    }

    public function testNonFiniteFloatsBecomeStrings(): void
    {
        $this->assertSame(
            "'{\"inf\":\"INF\",\"n\":[\"NAN\",1.5]}'::jsonb",
            $this->build($this->makeDb(), ['inf' => INF, 'n' => [NAN, 1.5]])
        );
    }
}
