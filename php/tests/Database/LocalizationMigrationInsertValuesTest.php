<?php

namespace diCore\Tests\Database;

use diCore\Data\Config;
use diCore\Database\Tool\LocalizationMigration;
use PHPUnit\Framework\TestCase;

/**
 * Covers \diCore\Database\Tool\LocalizationMigration::insertValues().
 *
 * Three things it must get right, since a migration runs unattended: the list is
 * keyed by language (positional shifted translations into neighbouring columns),
 * $strict catches an incomplete or unknown one before the first write, and prose
 * values ("L'amour") go through the DB API.
 *
 * Self-contained: throwaway table, columns declared per migration, and the
 * language map is built off Config::getMainLanguage() — only the main language
 * maps to a bare `value`, so a suite hard-coding 'ru' would pass here and fail
 * in a consumer whose main language is another one.
 */
class LocalizationMigrationInsertValuesTest extends TestCase
{
    private const TABLE = '_di_core_test_localization';

    private const CANDIDATES = ['ru', 'en', 'de', 'it', 'es', 'fr', 'pt'];

    private const GREETINGS = ['Привет', 'Hello', 'Hallo', 'Ciao', 'Hola', 'Salut'];

    private \diDB $db;

    /** @return string[] six languages, the project's main one first */
    private static function languages(): array
    {
        $main = Config::getMainLanguage();
        $rest = array_values(array_diff(self::CANDIDATES, [$main]));

        return array_merge([$main], array_slice($rest, 0, 5));
    }

    /** @return string[] value columns, in the same order as languages() */
    private static function fields(): array
    {
        return array_map(function ($language) {
            return \diModel::getLocalizedFieldName('value', $language);
        }, self::languages());
    }

    private static function field(int $index): string
    {
        return self::fields()[$index];
    }

    private static function language(int $index): string
    {
        return self::languages()[$index];
    }

    /** A language the throwaway table has no column for. */
    private static function unknownLanguage(): string
    {
        return (string) current(array_diff(self::CANDIDATES, self::languages()));
    }

    /** @return array<string, string> language => greeting */
    private static function allSix(): array
    {
        return array_combine(self::languages(), self::GREETINGS);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = \diCore\Database\Connection::get()->getDb();

        $columns = array_map(function ($field) {
            return "`$field` TEXT";
        }, self::fields());

        $this->db->q('DROP TABLE IF EXISTS `' . self::TABLE . '`');
        $this->db->q(
            'CREATE TABLE `' .
                self::TABLE .
                '` (
                id BIGINT NOT NULL AUTO_INCREMENT,
                name VARCHAR(191) NOT NULL, ' .
                join(', ', $columns) .
                ',
                UNIQUE KEY name_idx (name),
                PRIMARY KEY (id)
            ) ENGINE=InnoDB ROW_FORMAT=DYNAMIC ' .
                Config::getDbCharsetClause()
        );
    }

    protected function tearDown(): void
    {
        $this->db->q('DROP TABLE IF EXISTS `' . self::TABLE . '`');

        parent::tearDown();
    }

    /**
     * @param string[]|null $valueFields null keeps whatever the model says
     */
    private function makeMigration(?array $valueFields = null): object
    {
        return new class (self::TABLE, $valueFields ?? self::fields()) extends
            LocalizationMigration
        {
            private string $table;
            private ?array $valueFields;
            public array $logged = [];

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

            protected function logLocalizationIssue(string $message): void
            {
                $this->logged[] = $message;
            }

            public function insert(array $values, bool $strict = true): void
            {
                $this->insertValues($values, $strict);
            }

            public function valueFields(): array
            {
                return $this->getValueFields();
            }

            public function remove(array $names): void
            {
                $this->names = $names;
                $this->down();
            }
        };
    }

    /** The migration whose value fields come from the model, not from the test. */
    private function makeModelDrivenMigration(): object
    {
        return new class (self::TABLE) extends LocalizationMigration {
            private string $table;

            public function __construct(string $table)
            {
                $this->table = $table;
            }

            public function up()
            {
            }

            protected function getLocalizationTable(): string
            {
                return $this->table;
            }

            public function valueFields(): array
            {
                return $this->getValueFields();
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

    /** Value of the i-th language of the row. */
    private function value(string $name, int $index)
    {
        return $this->row($name)->{self::field($index)};
    }

    private function rowCount(): int
    {
        return (int) $this->db->r(self::TABLE, '', 'COUNT(*) AS n')->n;
    }

    public function testEachLanguageLandsInItsOwnColumn(): void
    {
        $this->makeMigration()->insert(['greeting' => self::allSix()]);

        foreach (array_values(self::GREETINGS) as $i => $greeting) {
            $this->assertSame($greeting, $this->value('greeting', $i));
        }
    }

    public function testTheOrderOfTheInputDoesNotMatter(): void
    {
        $this->makeMigration()->insert([
            'greeting' => array_reverse(self::allSix(), true),
        ]);

        foreach (array_values(self::GREETINGS) as $i => $greeting) {
            $this->assertSame($greeting, $this->value('greeting', $i));
        }
    }

    public function testSeveralTokensAtOnce(): void
    {
        $one = array_combine(self::languages(), [
            'Один',
            'One',
            'Eins',
            'Uno',
            'Uno',
            'Un',
        ]);
        $two = array_combine(self::languages(), [
            'Два',
            'Two',
            'Zwei',
            'Due',
            'Dos',
            'Deux',
        ]);

        $this->makeMigration()->insert(['one' => $one, 'two' => $two]);

        $this->assertSame(2, $this->rowCount());
        $this->assertSame('One', $this->value('one', 1));
        $this->assertSame('Deux', $this->value('two', 5));
    }

    /** INSERT IGNORE: an editor's translation survives a re-run. */
    public function testExistingTokenIsLeftUntouched(): void
    {
        $m = $this->makeMigration();

        $m->insert(['greeting' => self::allSix()]);
        $m->insert(['greeting' => array_fill_keys(self::languages(), 'x')]);

        $this->assertSame(1, $this->rowCount());
        $this->assertSame(self::GREETINGS[0], $this->value('greeting', 0));
        $this->assertSame(self::GREETINGS[1], $this->value('greeting', 1));
    }

    public function testMissingLanguageIsRefusedInStrictMode(): void
    {
        $values = self::allSix();
        unset($values[self::language(4)]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(self::field(4));

        $this->makeMigration()->insert(['partial' => $values]);
    }

    public function testMissingLanguageIsAllowedWhenNotStrict(): void
    {
        $m = $this->makeMigration();

        $m->insert(
            [
                'partial' => [
                    self::language(0) => 'Только главный',
                    self::language(1) => 'And the second',
                ],
            ],
            false
        );

        $this->assertSame('Только главный', $this->value('partial', 0));
        $this->assertSame('And the second', $this->value('partial', 1));
        // written as '', not left to the column default
        $this->assertSame('', $this->value('partial', 2));
        $this->assertSame('', $this->value('partial', 5));
        $this->assertNotEmpty($m->logged, 'the shortfall must not be silent');
    }

    public function testUnknownLanguageIsRefusedInStrictMode(): void
    {
        $unknown = self::unknownLanguage();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($unknown);

        $this->makeMigration()->insert([
            'token' => self::allSix() + [$unknown => 'Olá'],
        ]);
    }

    /** A six-language migration must still install on a two-language database. */
    public function testUnknownLanguageIsSkippedWhenNotStrict(): void
    {
        $m = $this->makeMigration([self::field(0), self::field(1)]);

        $m->insert(['token' => self::allSix()], false);

        $row = $this->row('token');

        $this->assertSame(self::GREETINGS[0], $row->{self::field(0)});
        $this->assertSame(self::GREETINGS[1], $row->{self::field(1)});
        $this->assertNull(
            $row->{self::field(2)},
            'a column outside the list is untouched'
        );
        $this->assertNotEmpty($m->logged, 'a skipped language must be logged');
    }

    public function testPositionalListIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('keyed by language');

        $this->makeMigration()->insert(['greeting' => self::GREETINGS]);
    }

    public function testPositionalListIsRefusedInLenientModeToo(): void
    {
        // lenient is about completeness, not about accepting another shape
        $this->expectException(\InvalidArgumentException::class);

        $this->makeMigration()->insert(['greeting' => ['Привет']], false);
    }

    public function testEmptyTranslationListIsRefusedInBothModes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeMigration()->insert(['empty' => []], false);
    }

    /**
     * A bare string used to be accepted as the main language, but that only ever
     * worked in the lenient mode — on any table with two columns it fails the
     * completeness check anyway. Explicit is what the whole API is about.
     */
    public function testAScalarInsteadOfALanguageMapIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('keyed by');

        $this->makeMigration()->insert(['scalar' => 'Одно значение'], false);
    }

    /** (string) of an array is the literal "Array" — it must not be stored. */
    public function testANonScalarValueIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(self::language(1));

        $this->makeMigration()->insert([
            'token' => [self::language(1) => ['Hello', 'Hi']] + self::allSix(),
        ]);
    }

    /** down() with an empty $names degenerates into `name in ('')`. */
    public function testANamelessTokenIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no name');

        $this->makeMigration()->insert(['' => self::allSix()]);
    }

    public function testNothingIsWrittenWhenOneTokenOfTheBatchIsInvalid(): void
    {
        try {
            $this->makeMigration()->insert([
                'good' => self::allSix(),
                'bad' => [],
            ]);
        } catch (\InvalidArgumentException $e) {
        }

        $this->assertSame(0, $this->rowCount(), 'no half-applied migration');
    }

    /** Apostrophes are ordinary in en/fr/it prose; unescaped they break the SQL. */
    public function testQuotesAndBackslashesSurviveVerbatim(): void
    {
        $values = array_combine(self::languages(), [
            "Кавычка ' и \\обратный слэш",
            "don't",
            'Anführungszeichen "so"',
            "L'italiano",
            '¿Qué\\?',
            "L'amour",
        ]);

        $this->makeMigration()->insert(["it's.a\\token" => $values]);

        $this->assertNotFalse(
            $this->row("it's.a\\token"),
            'the token key itself must be escaped too'
        );

        foreach (array_values($values) as $i => $value) {
            $this->assertSame($value, $this->value("it's.a\\token", $i));
        }
    }

    public function testInjectionAttemptIsStoredAsPlainText(): void
    {
        $payload = "', 'x'); DROP TABLE `" . self::TABLE . '`; --';

        $this->makeMigration()->insert([
            'evil' => array_fill_keys(self::languages(), $payload),
        ]);

        $this->assertSame(1, $this->rowCount(), 'the table must still be there');
        $this->assertSame($payload, $this->value('evil', 0));
    }

    public function testEmptyInputInsertsNothing(): void
    {
        $this->makeMigration()->insert([]);

        $this->assertSame(0, $this->rowCount());
    }

    /** The default column set comes from the project's model, not a hard-coded list. */
    public function testDefaultValueFieldsComeFromTheModel(): void
    {
        $expected = \diCore\Entity\Localization\Model::create()::getValueFields();

        $this->assertSame(
            $expected,
            $this->makeModelDrivenMigration()->valueFields()
        );
        $this->assertContains('value', $expected);
    }

    public function testDownRemovesOnlyTheDeclaredTokens(): void
    {
        $m = $this->makeMigration();
        $m->insert(
            [
                'one' => [self::language(0) => 'Один'],
                'two' => [self::language(0) => 'Два'],
                'three' => [self::language(0) => 'Три'],
            ],
            false
        );

        $m->remove(['one', 'three']);

        $this->assertSame(1, $this->rowCount());
        $this->assertNotFalse($this->row('two'));
    }

    /** diDB::in() quotes its items but does not escape them. */
    public function testDownRemovesAQuotedTokenName(): void
    {
        $quoted = "it's.a\\token";
        $m = $this->makeMigration();

        $m->insert(
            [
                $quoted => [self::language(0) => 'Один'],
                'plain' => [self::language(0) => 'Два'],
            ],
            false
        );
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

        $m->insert([$quoted => [self::language(0) => 'Единственный']], false);
        $m->remove([$quoted]);

        $this->assertSame(0, $this->rowCount());
    }
}
