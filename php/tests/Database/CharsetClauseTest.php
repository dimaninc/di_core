<?php

namespace diCore\Tests\Database;

use diCore\Data\Config;
use PHPUnit\Framework\TestCase;

/**
 * Generated DDL must never name a charset without its collation — MySQL 8 then
 * hands the table utf8mb4_0900_ai_ci and it collides with every table that did
 * name one, on every join.
 *
 * The one case where it legitimately cannot: a project whose dbCollation is
 * blank. `COLLATE ''` is a syntax error and "<charset>_general_ci" is a guess
 * that need not exist, so the keyword is dropped — and the misconfiguration is
 * written to the db log instead of passing unnoticed.
 */
class CharsetClauseTest extends TestCase
{
    public function testTableClauseNamesBothHalves(): void
    {
        $this->assertSame(
            "DEFAULT CHARSET = 'utf8mb4' COLLATE = 'utf8mb4_general_ci'",
            Config::buildCharsetClause(
                'DEFAULT CHARSET = ',
                ' COLLATE = ',
                'utf8mb4',
                'utf8mb4_general_ci'
            )
        );
    }

    public function testColumnClauseNamesBothHalves(): void
    {
        $this->assertSame(
            "CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_bin'",
            Config::buildCharsetClause(
                'CHARACTER SET ',
                ' COLLATE ',
                'utf8mb4',
                'utf8mb4_bin'
            )
        );
    }

    /** `COLLATE ''` would be a syntax error; `collate ;` was a real one. */
    public function testBlankCollationDropsTheKeywordEntirely(): void
    {
        foreach (
            [['DEFAULT CHARSET = ', ' COLLATE = '], ['CHARACTER SET ', ' COLLATE ']]
            as [$prefix, $keyword]
        ) {
            $clause = Config::buildCharsetClause($prefix, $keyword, 'utf8mb4', '');

            $this->assertSame($prefix . "'utf8mb4'", $clause);
            $this->assertStringNotContainsString('COLLATE', $clause);
        }
    }

    public function testBlankCharsetFallsBackToMb3(): void
    {
        $this->assertSame(
            "DEFAULT CHARSET = 'utf8'",
            Config::buildCharsetClause('DEFAULT CHARSET = ', ' COLLATE = ', '', '')
        );
    }

    /** What the live configuration actually produces has to be valid DDL. */
    public function testConfiguredClausesAreWellFormed(): void
    {
        foreach (
            [Config::getDbCharsetClause(), Config::getDbColumnCharsetClause()]
            as $clause
        ) {
            $this->assertMatchesRegularExpression(
                "/^(DEFAULT CHARSET = |CHARACTER SET )'\w+'( COLLATE (= )?'\w+')?$/",
                $clause
            );
            $this->assertStringNotContainsString("''", $clause);
        }
    }
}
