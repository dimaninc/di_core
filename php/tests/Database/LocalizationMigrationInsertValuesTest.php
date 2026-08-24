<?php

namespace diCore\Tests\Database;

use diCore\Database\Tool\LocalizationMigration;
use PHPUnit\Framework\TestCase;

/**
 * Covers \diCore\Database\Tool\LocalizationMigration::insertValues() — the helper
 * localization migrations use instead of hand-rolling an INSERT per token.
 *
 * The two things it has to get right, because a migration runs unattended:
 *   - the per-token list is POSITIONAL (ru, en, de, it, es, fr) and lines up with
 *     getValueFields(); a short list must not shift values into the wrong language
 *   - values go through the DB API. Localization strings are prose — "L'amour",
 *     "don't" — so an apostrophe is the normal case, not the exotic one, and an
 *     unescaped one would break the migration for everybody.
 *
 * Self-contained: creates a throwaway localization-shaped table in setUp and drops
 * it in tearDown, so the project's real `localization` is never touched.
 */
class LocalizationMigrationInsertValuesTest extends TestCase
{
    private const TABLE = '_di_core_test_localization';

    private \diDB $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \diCore\Database\Connection::get()->getDb();

        $this->db->q('DROP TABLE IF EXISTS `' . self::TABLE . '`');
        $this->db->q(
            'CREATE TABLE `' .
                self::TABLE .
                '` (
                id BIGINT NOT NULL AUTO_INCREMENT,
                name VARCHAR(191) NOT NULL,
                value TEXT,
                en_value TEXT,
                de_value TEXT,
                it_value TEXT,
                es_value TEXT,
                fr_value TEXT,
                UNIQUE KEY name_idx (name),
                PRIMARY KEY (id)
            ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC ' .
                \diCore\Data\Config::getDbCharsetClause()
        );
    }

    protected function tearDown(): void
    {
        $this->db->q('DROP TABLE IF EXISTS `' . self::TABLE . '`');

        parent::tearDown();
    }

    /**
     * @param string[]|null $valueFields null keeps the helper's own default
     */
    private function makeMigration(?array $valueFields = null): object
    {
        return new class(self::TABLE, $valueFields) extends LocalizationMigration {
            private string $table;
            private ?array $valueFields;

            public function __construct(string $table, ?array $valueFields)
            {
                $this->table = $table;
                $this->valueFields = $valueFields;
            }

            public function up()
            {
            }

            protected function getLocalizationTable(): string
            {
                return $this->table;
            }

            protected function getValueFields(): array
            {
                return $this->valueFields ?? parent::getValueFields();
            }

            public function insert(array $values): void
            {
                $this->insertValues($values);
            }

            public function remove(array $names): void
            {
                $this->names = $names;
                $this->down();
            }
        };
    }

    private function row(string $name)
    {
        // escapeValue() = escape_string() + quoteValue()
        return $this->db->r(
            self::TABLE,
            'WHERE name = ' . $this->db->escapeValue($name)
        );
    }

    private function rowCount(): int
    {
        return (int) $this->db->r(self::TABLE, '', 'COUNT(*) AS n')->n;
    }

    public function testSixValuesLandInTheirOwnColumns(): void
    {
        $this->makeMigration()->insert([
            'greeting' => ['Привет', 'Hello', 'Hallo', 'Ciao', 'Hola', 'Salut'],
        ]);

        $row = $this->row('greeting');

        $this->assertSame('Привет', $row->value);
        $this->assertSame('Hello', $row->en_value);
        $this->assertSame('Hallo', $row->de_value);
        $this->assertSame('Ciao', $row->it_value);
        $this->assertSame('Hola', $row->es_value);
        $this->assertSame('Salut', $row->fr_value);
    }

    public function testSeveralTokensAtOnce(): void
    {
        $this->makeMigration()->insert([
            'one' => ['Один', 'One', 'Eins', 'Uno', 'Uno', 'Un'],
            'two' => ['Два', 'Two', 'Zwei', 'Due', 'Dos', 'Deux'],
        ]);

        $this->assertSame(2, $this->rowCount());
        $this->assertSame('One', $this->row('one')->en_value);
        $this->assertSame('Deux', $this->row('two')->fr_value);
    }

    /**
     * INSERT IGNORE, not INSERT: these migrations get re-run against databases
     * that are already ahead of them, and an editor's translation must survive.
     */
    public function testExistingTokenIsLeftUntouched(): void
    {
        $m = $this->makeMigration();

        $m->insert(['greeting' => ['Привет', 'Hello', '', '', '', '']]);
        $m->insert(['greeting' => ['ЗАМЕНА', 'REPLACED', 'x', 'x', 'x', 'x']]);

        $this->assertSame(1, $this->rowCount());
        $this->assertSame('Привет', $this->row('greeting')->value);
        $this->assertSame('Hello', $this->row('greeting')->en_value);
    }

    /** A short list must fill the tail, never shift values left into it. */
    public function testShortListLeavesTheRemainingColumnsEmpty(): void
    {
        $this->makeMigration()->insert([
            'partial' => ['Только русский', 'English only'],
        ]);

        $row = $this->row('partial');

        $this->assertSame('Только русский', $row->value);
        $this->assertSame('English only', $row->en_value);
        $this->assertSame('', $row->de_value);
        $this->assertSame('', $row->it_value);
        $this->assertSame('', $row->es_value);
        $this->assertSame('', $row->fr_value);
    }

    public function testLongerListHasItsTailIgnored(): void
    {
        $this->makeMigration()->insert([
            'extra' => ['ru', 'en', 'de', 'it', 'es', 'fr', 'pt', 'pl'],
        ]);

        $this->assertSame('fr', $this->row('extra')->fr_value);
        $this->assertSame(1, $this->rowCount());
    }

    /**
     * Apostrophes are ordinary in en/fr/it prose. Unescaped, the migration is a
     * syntax error at best.
     */
    public function testQuotesAndBackslashesSurviveVerbatim(): void
    {
        $values = [
            "Кавычка ' и \\обратный слэш",
            "don't",
            'Anführungszeichen "so"',
            "L'italiano",
            '¿Qué\\?',
            "L'amour",
        ];

        $this->makeMigration()->insert(["it's.a\\token" => $values]);

        $row = $this->row("it's.a\\token");

        $this->assertNotFalse($row, 'the token key itself must be escaped too');
        $this->assertSame($values[0], $row->value);
        $this->assertSame($values[1], $row->en_value);
        $this->assertSame($values[2], $row->de_value);
        $this->assertSame($values[3], $row->it_value);
        $this->assertSame($values[4], $row->es_value);
        $this->assertSame($values[5], $row->fr_value);
    }

    public function testInjectionAttemptIsStoredAsPlainText(): void
    {
        $payload = "', 'x'); DROP TABLE `" . self::TABLE . '`; --';

        $this->makeMigration()->insert([
            'evil' => [$payload, $payload, '', '', '', ''],
        ]);

        $this->assertSame(1, $this->rowCount(), 'the table must still be there');
        $this->assertSame($payload, $this->row('evil')->value);
    }

    public function testEmptyInputInsertsNothing(): void
    {
        $this->makeMigration()->insert([]);

        $this->assertSame(0, $this->rowCount());
    }

    /** A bare string is treated as the ru value rather than crashing. */
    public function testScalarValueBecomesTheFirstColumn(): void
    {
        $this->makeMigration()->insert(['scalar' => 'Одно значение']);

        $row = $this->row('scalar');

        $this->assertSame('Одно значение', $row->value);
        $this->assertSame('', $row->en_value);
    }

    /**
     * This package's own dump only has `value` and `en_value` — a project on that
     * schema narrows the list instead of getting an unknown-column error.
     */
    public function testValueFieldsOverrideNarrowsTheColumnSet(): void
    {
        $this->makeMigration(['value', 'en_value'])->insert([
            'narrow' => ['Привет', 'Hello', 'Hallo'],
        ]);

        $row = $this->row('narrow');

        $this->assertSame('Привет', $row->value);
        $this->assertSame('Hello', $row->en_value);
        $this->assertNull($row->de_value, 'untouched columns keep their default');
    }

    /**
     * getValueFields() overrides get written as array_filter()/unset() results,
     * which keep their original keys — those must not become value indexes.
     */
    public function testValueFieldsOverrideWithGappyKeysStaysPositional(): void
    {
        // keys 0, 2, 5 — what array_filter() on the default list leaves behind
        $fields = array_filter(
            ['value', 'en_value', 'de_value', 'it_value', 'es_value', 'fr_value'],
            function ($f) {
                return in_array($f, ['value', 'de_value', 'fr_value'], true);
            }
        );

        $this->makeMigration($fields)->insert([
            'gappy' => ['Привет', 'Hallo', 'Salut'],
        ]);

        $row = $this->row('gappy');

        $this->assertSame('Привет', $row->value);
        $this->assertSame('Hallo', $row->de_value);
        $this->assertSame('Salut', $row->fr_value);
        $this->assertNull($row->en_value, 'untouched columns keep their default');
    }

    /** down() deletes exactly the tokens the migration declared, and no others. */
    public function testDownRemovesOnlyTheDeclaredTokens(): void
    {
        $m = $this->makeMigration();
        $m->insert([
            'one' => ['Один', 'One', '', '', '', ''],
            'two' => ['Два', 'Two', '', '', '', ''],
            'three' => ['Три', 'Three', '', '', '', ''],
        ]);

        $m->remove(['one', 'three']);

        $this->assertSame(1, $this->rowCount());
        $this->assertNotFalse($this->row('two'));
    }

    /**
     * insertValues() accepts a quoted token name, so down() has to be able to
     * delete one: diDB::in() quotes its items but does not escape them.
     */
    public function testDownRemovesAQuotedTokenName(): void
    {
        $quoted = "it's.a\\token";
        $m = $this->makeMigration();

        $m->insert([
            $quoted => ['Один', 'One', '', '', '', ''],
            'plain' => ['Два', 'Two', '', '', '', ''],
        ]);
        $this->assertSame(2, $this->rowCount());

        $m->remove([$quoted]);

        $this->assertSame(1, $this->rowCount());
        $this->assertFalse($this->row($quoted));
        $this->assertNotFalse($this->row('plain'));
    }

    /** A single quoted name goes down the `= 'x'` branch of diDB::in(). */
    public function testDownRemovesASingleQuotedTokenName(): void
    {
        $quoted = "l'unico";
        $m = $this->makeMigration();

        $m->insert([$quoted => ["Единственный", 'The only one', '', '', '', '']]);
        $m->remove([$quoted]);

        $this->assertSame(0, $this->rowCount());
    }
}
