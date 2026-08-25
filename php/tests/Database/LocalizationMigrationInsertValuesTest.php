<?php

namespace diCore\Tests\Database;

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
 * Self-contained: throwaway table, and the columns are declared per migration so
 * the suite behaves the same in a two-language project and in a six-language one.
 */
class LocalizationMigrationInsertValuesTest extends TestCase
{
    private const TABLE = '_di_core_test_localization';

    /** The order means nothing — the input is keyed. */
    private const FIELDS = [
        'value',
        'en_value',
        'de_value',
        'it_value',
        'es_value',
        'fr_value',
    ];

    private const ALL_SIX = [
        'ru' => 'Привет',
        'en' => 'Hello',
        'de' => 'Hallo',
        'it' => 'Ciao',
        'es' => 'Hola',
        'fr' => 'Salut',
    ];

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
     * @param string[]|null $valueFields null keeps whatever the model says
     */
    private function makeMigration(?array $valueFields = self::FIELDS): object
    {
        return new class (self::TABLE, $valueFields) extends LocalizationMigration {
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

    public function testEachLanguageLandsInItsOwnColumn(): void
    {
        $this->makeMigration()->insert(['greeting' => self::ALL_SIX]);

        $row = $this->row('greeting');

        $this->assertSame('Привет', $row->value);
        $this->assertSame('Hello', $row->en_value);
        $this->assertSame('Hallo', $row->de_value);
        $this->assertSame('Ciao', $row->it_value);
        $this->assertSame('Hola', $row->es_value);
        $this->assertSame('Salut', $row->fr_value);
    }

    public function testTheOrderOfTheInputDoesNotMatter(): void
    {
        $this->makeMigration()->insert([
            'greeting' => [
                'fr' => 'Salut',
                'ru' => 'Привет',
                'es' => 'Hola',
                'en' => 'Hello',
                'it' => 'Ciao',
                'de' => 'Hallo',
            ],
        ]);

        $row = $this->row('greeting');

        $this->assertSame('Привет', $row->value);
        $this->assertSame('Ciao', $row->it_value);
        $this->assertSame('Hola', $row->es_value);
    }

    public function testSeveralTokensAtOnce(): void
    {
        $this->makeMigration()->insert([
            'one' => [
                'ru' => 'Один',
                'en' => 'One',
                'de' => 'Eins',
                'it' => 'Uno',
                'es' => 'Uno',
                'fr' => 'Un',
            ],
            'two' => [
                'ru' => 'Два',
                'en' => 'Two',
                'de' => 'Zwei',
                'it' => 'Due',
                'es' => 'Dos',
                'fr' => 'Deux',
            ],
        ]);

        $this->assertSame(2, $this->rowCount());
        $this->assertSame('One', $this->row('one')->en_value);
        $this->assertSame('Deux', $this->row('two')->fr_value);
    }

    /** INSERT IGNORE: an editor's translation survives a re-run. */
    public function testExistingTokenIsLeftUntouched(): void
    {
        $m = $this->makeMigration();

        $m->insert(['greeting' => self::ALL_SIX]);
        $m->insert(['greeting' => array_fill_keys(array_keys(self::ALL_SIX), 'x')]);

        $this->assertSame(1, $this->rowCount());
        $this->assertSame('Привет', $this->row('greeting')->value);
        $this->assertSame('Hello', $this->row('greeting')->en_value);
    }

    public function testMissingLanguageIsRefusedInStrictMode(): void
    {
        $values = self::ALL_SIX;
        unset($values['es']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('es_value');

        $this->makeMigration()->insert(['partial' => $values]);
    }

    public function testMissingLanguageIsAllowedWhenNotStrict(): void
    {
        $m = $this->makeMigration();

        $m->insert(
            ['partial' => ['ru' => 'Только русский', 'en' => 'English only']],
            false
        );

        $row = $this->row('partial');

        $this->assertSame('Только русский', $row->value);
        $this->assertSame('English only', $row->en_value);
        // written as '', not left to the column default
        $this->assertSame('', $row->de_value);
        $this->assertSame('', $row->it_value);
        $this->assertSame('', $row->es_value);
        $this->assertSame('', $row->fr_value);
        $this->assertNotEmpty($m->logged, 'the shortfall must not be silent');
    }

    public function testUnknownLanguageIsRefusedInStrictMode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('pt');

        $this->makeMigration()->insert(['token' => self::ALL_SIX + ['pt' => 'Olá']]);
    }

    /** A six-language migration must still install on a two-language database. */
    public function testUnknownLanguageIsSkippedWhenNotStrict(): void
    {
        $m = $this->makeMigration(['value', 'en_value']);

        $m->insert(['token' => self::ALL_SIX], false);

        $row = $this->row('token');

        $this->assertSame('Привет', $row->value);
        $this->assertSame('Hello', $row->en_value);
        $this->assertNull($row->de_value, 'a column outside the list is untouched');
        $this->assertNotEmpty($m->logged, 'a skipped language must be logged');
    }

    public function testPositionalListIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('keyed by language');

        $this->makeMigration()->insert([
            'greeting' => ['Привет', 'Hello', 'Hallo', 'Ciao', 'Hola', 'Salut'],
        ]);
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

    public function testScalarBecomesTheMainLanguage(): void
    {
        $m = $this->makeMigration();

        $m->insert(['scalar' => 'Одно значение'], false);

        $this->assertSame('Одно значение', $this->row('scalar')->value);
        $this->assertSame('', $this->row('scalar')->en_value);
    }

    public function testNothingIsWrittenWhenOneTokenOfTheBatchIsInvalid(): void
    {
        try {
            $this->makeMigration()->insert([
                'good' => self::ALL_SIX,
                'bad' => [],
            ]);
        } catch (\InvalidArgumentException $e) {
        }

        $this->assertSame(0, $this->rowCount(), 'no half-applied migration');
    }

    /** Apostrophes are ordinary in en/fr/it prose; unescaped they break the SQL. */
    public function testQuotesAndBackslashesSurviveVerbatim(): void
    {
        $values = [
            'ru' => "Кавычка ' и \\обратный слэш",
            'en' => "don't",
            'de' => 'Anführungszeichen "so"',
            'it' => "L'italiano",
            'es' => '¿Qué\\?',
            'fr' => "L'amour",
        ];

        $this->makeMigration()->insert(["it's.a\\token" => $values]);

        $row = $this->row("it's.a\\token");

        $this->assertNotFalse($row, 'the token key itself must be escaped too');
        $this->assertSame($values['ru'], $row->value);
        $this->assertSame($values['en'], $row->en_value);
        $this->assertSame($values['de'], $row->de_value);
        $this->assertSame($values['it'], $row->it_value);
        $this->assertSame($values['es'], $row->es_value);
        $this->assertSame($values['fr'], $row->fr_value);
    }

    public function testInjectionAttemptIsStoredAsPlainText(): void
    {
        $payload = "', 'x'); DROP TABLE `" . self::TABLE . '`; --';

        $this->makeMigration()->insert([
            'evil' => array_fill_keys(array_keys(self::ALL_SIX), $payload),
        ]);

        $this->assertSame(1, $this->rowCount(), 'the table must still be there');
        $this->assertSame($payload, $this->row('evil')->value);
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

        $this->assertSame($expected, $this->makeMigration(null)->valueFields());
        $this->assertContains('value', $expected);
    }

    public function testDownRemovesOnlyTheDeclaredTokens(): void
    {
        $m = $this->makeMigration();
        $m->insert(
            [
                'one' => ['ru' => 'Один', 'en' => 'One'],
                'two' => ['ru' => 'Два', 'en' => 'Two'],
                'three' => ['ru' => 'Три', 'en' => 'Three'],
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
                $quoted => ['ru' => 'Один', 'en' => 'One'],
                'plain' => ['ru' => 'Два', 'en' => 'Two'],
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

        $m->insert(
            [$quoted => ['ru' => 'Единственный', 'en' => 'The only one']],
            false
        );
        $m->remove([$quoted]);

        $this->assertSame(0, $this->rowCount());
    }
}
