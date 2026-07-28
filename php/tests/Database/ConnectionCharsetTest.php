<?php

namespace diCore\Tests\Database;

use diCore\Data\Config;
use diCore\Database\Connection;
use diCore\Database\Tool\CharsetConverter;
use PHPUnit\Framework\TestCase;

/**
 * The live connection must end up on the CONFIGURED charset and collation.
 *
 * initCharset() runs set_charset() and `SET NAMES … COLLATE …`, and the first
 * silently overrides the collation of the second — so with the calls in the
 * wrong order the connection sits on the charset's default collation while the
 * columns use the configured one, and comparisons hit "Illegal mix of
 * collations". Nothing else in the suite would notice.
 */
class ConnectionCharsetTest extends TestCase
{
    private function db(): \diDB
    {
        $db = Connection::get()->getDb();

        // `SELECT @@SESSION.…` is MySQL-only, and initCharset() does not even run
        // on the drivers that opt out — this test travels into consumer projects
        // whose default connection may be any of them.
        if (!$db::CHARSET_INIT_NEEDED || !($db instanceof \diMYSQLi)) {
            $this->markTestSkipped('charset init applies to MySQL connections only');
        }

        return $db;
    }

    private function sessionVar(string $name): string
    {
        $db = $this->db();
        $rs = $db->q("SELECT @@SESSION.$name AS v");

        return (string) $db->fetch($rs)->v;
    }

    /**
     * Compared through sameCharset(): a project configured as 'utf8' reads back
     * as 'utf8mb3' from MySQL 8.0.28 on, and that rename is not a mismatch.
     */
    public function testConnectionUsesConfiguredCharset(): void
    {
        $actual = $this->sessionVar('character_set_connection');

        $this->assertTrue(
            CharsetConverter::sameCharset(Config::getDbEncoding(), $actual),
            'connection charset is ' . $actual
        );
    }

    /** The assertion that fails if set_charset() is moved back after SET NAMES. */
    public function testConnectionUsesConfiguredCollation(): void
    {
        $expected = Config::getDbCollation();
        $actual = $this->sessionVar('collation_connection');

        // Same rename, one level down: utf8_general_ci -> utf8mb3_general_ci.
        $this->assertTrue(
            $this->sameCollation($expected, $actual),
            "collation is $actual, configured $expected"
        );
    }

    private function sameCollation(string $expected, string $actual): bool
    {
        return $this->normaliseCollation($expected) ===
            $this->normaliseCollation($actual);
    }

    /** utf8_general_ci and utf8mb3_general_ci are the same collation. */
    private function normaliseCollation(string $collation): string
    {
        $collation = strtolower($collation);

        foreach (CharsetConverter::MB3_NAMES as $name) {
            if (strpos($collation, $name . '_') === 0) {
                return 'utf8mb3_' . substr($collation, strlen($name) + 1);
            }
        }

        return $collation;
    }

    /**
     * The CLIENT-side charset, which is what escaping uses. Deliberately not
     * `@@SESSION.character_set_client` — that is a server variable that SET NAMES
     * moves on its own, so it would stay green even if set_charset() had silently
     * failed and left the client library on another charset.
     */
    public function testClientLibraryCharsetMatchesConfig(): void
    {
        $actual = $this->db()->get_charset();

        $this->assertTrue(
            CharsetConverter::sameCharset(Config::getDbEncoding(), $actual),
            'client charset is ' . $actual
        );
    }
}
