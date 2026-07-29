<?php

use diCore\Data\Config;
use diCore\Database\Tool\CharsetConverter;

/**
 * Converts the tables di_core itself ships to the configured charset.
 *
 * Scoped to those tables on purpose: the consuming project owns the rest of its
 * schema — including the database default and any stored programs — and converts
 * them with its own migration. Running both is safe, each skips what is already
 * on the target charset.
 *
 * No-op unless the project actually asked for a different charset (an old
 * project that keeps `dbEncoding = 'utf8'` is left exactly as it was).
 */
class diMigration_20260728100000 extends \diCore\Database\Tool\Migration
{
    public static $idx = '20260728100000';
    public static $name = 'di_core tables: convert to the configured charset';

    public function up()
    {
        $charset = Config::getDbEncoding();

        // Never narrow. A project still configured as mb3 has mb3 tables and
        // nothing to do — but its schema may already hold an mb4 table created
        // from one of this package's (now mb4) dumps, and converting THAT down
        // to mb3 would truncate every 4-byte character in it. Widening is an
        // upgrade; narrowing is a decision only the project can make.
        if (!$this->applicable() || $this->isMb3($charset)) {
            return;
        }

        $this->convertTo($charset, Config::getDbCollation());
    }

    private function isMb3(string $charset): bool
    {
        return in_array(
            strtolower($charset),
            CharsetConverter::MB3_NAMES,
            true
        );
    }

    /**
     * Back to utf8mb3. Lossy by nature — 4-byte characters stored meanwhile do
     * not fit — so it runs strict: a failed rollback beats silently sweeping
     * every emoji out of these tables.
     */
    public function down()
    {
        if (!$this->applicable()) {
            return;
        }

        $name = CharsetConverter::mb3NameFor($this->getDb());
        if ($name === null) {
            throw new \Exception('No utf8mb3 charset on this server');
        }

        $this->convertTo($name, $name . '_general_ci', true);
    }

    /**
     * MySQL only. MigrationsManager also runs for SQLite and PostgreSQL
     * consumers, where the converter's information_schema queries and ALTER
     * syntax are meaningless — this migration is a no-op there, not a failure.
     */
    private function applicable(): bool
    {
        return CharsetConverter::supports($this->getDb());
    }

    private function convertTo(
        string $charset,
        string $collation,
        bool $strict = false
    ): void {
        $tables = $this->coreTables();
        if (!$tables) {
            return;
        }

        $converter = new CharsetConverter(
            $this->getDb(),
            $charset,
            $collation
        );

        $converter->inPreparedSession($strict, function ($c) use (
            $tables,
            $strict
        ) {
            // Engines first: at the old charset an oversized index still fits.
            // Not on the way back — a rollback of the charset has no business
            // changing engines, and which ones were MyISAM is not recorded.
            if (!$strict) {
                $c->moveMyisamTablesToInnoDb($tables);
            }

            $c->convertTables($tables);
        });
    }

    /**
     * Tables this package ships a dump for. Not filtered for existence here —
     * the converter's information_schema queries only ever match real ones.
     */
    private function coreTables(): array
    {
        $names = [];

        $files = array_merge(
            glob(__DIR__ . '/../../sql/*.sql') ?: [],
            glob(__DIR__ . '/../../sql/*/*.sql') ?: []
        );

        foreach ($files as $file) {
            if (preg_match('#/(postgres|sqlite)/#', $file)) {
                continue; // engine-specific variants, not MySQL
            }
            $names[] = pathinfo($file, PATHINFO_FILENAME);
        }

        return array_values(array_unique($names));
    }
}
