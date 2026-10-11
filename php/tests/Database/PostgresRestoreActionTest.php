<?php

namespace diCore\Tests\Database;

use diCore\Controller\Db;
use diCore\Database\Legacy\Postgresql;
use diCore\Helper\ProcessHelper;
use diCore\Tests\Entity\AuthorizationPinTestDb;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Entity/AuthorizationPinTestDb.php';

/**
 * Восстановление дампа PostgreSQL со страницы: всегда psql одной транзакцией, при его сбое
 * ошибка (не повтор парсером), парсер – запасной путь, если psql нет. Живой тест:
 * DI_CORE_TEST_DB=postgresql://…; для ветки psql нужен он сам (DI_CORE_PG_BIN – каталог).
 */
class PostgresRestoreActionTest extends TestCase
{
    private $folder;

    public static function setUpBeforeClass(): void
    {
        AuthorizationPinTestDb::open();
    }

    public static function tearDownAfterClass(): void
    {
        AuthorizationPinTestDb::close();
    }

    protected function setUp(): void
    {
        $dsn = getenv('DI_CORE_TEST_DB');

        if (!$dsn || parse_url($dsn, PHP_URL_SCHEME) !== 'postgresql') {
            $this->markTestSkipped('Needs DI_CORE_TEST_DB=postgresql://…');
        }

        $this->folder = sys_get_temp_dir() . '/di_core_restore_' . getmypid() . '/';
        mkdir($this->folder);
        $db = AuthorizationPinTestDb::db();
        $db->q('DROP TABLE IF EXISTS di_core_restore_t');
        $db->q('CREATE TABLE di_core_restore_t (id integer PRIMARY KEY, v text)');
    }

    protected function tearDown(): void
    {
        Postgresql::setRestoreCommand('psql');
        AuthorizationPinTestDb::db()->q('DROP TABLE IF EXISTS di_core_restore_t');

        foreach (glob($this->folder . '*') as $f) {
            unlink($f);
        }

        rmdir($this->folder);
    }

    private function restore(string $sql, string $name = 'd.sql'): array
    {
        file_put_contents($this->folder . $name, $sql);

        $c = (new \ReflectionClass(Db::class))->newInstanceWithoutConstructor();

        foreach (['file' => $name, 'folder' => $this->folder] as $prop => $value) {
            $p = new \ReflectionProperty(Db::class, $prop);
            $p->setAccessible(true);
            $p->setValue($c, $value);
        }

        return $c->restoreAction();
    }

    private function rows(): array
    {
        $db = AuthorizationPinTestDb::db();
        $rs = $db->q('SELECT id, v FROM di_core_restore_t ORDER BY id');
        $res = [];

        while ($r = $db->fetch_array($rs)) {
            $res[(int) $r['id']] = $r['v'];
        }

        return $res;
    }

    private function useLocalPsql(): void
    {
        $bin = rtrim((string) getenv('DI_CORE_PG_BIN'), '/');
        $psql = ($bin ? "$bin/" : '') . 'psql';

        if (ProcessHelper::run([$psql, '--version'], 5.0)['code'] !== 0) {
            $this->markTestSkipped('psql is not available');
        }

        Postgresql::setRestoreCommand($psql);
    }

    public function testParserKeepsTheRestAfterAStringEndingWithABackslash(): void
    {
        // psql недоступен: работает запасной парсер
        Postgresql::setRestoreCommand('/nonexistent/psql');

        $res = $this->restore(
            "INSERT INTO di_core_restore_t VALUES (1, 'C:\\');\n" .
                "INSERT INTO di_core_restore_t VALUES (2, 'it''s');\n" .
                "INSERT INTO di_core_restore_t VALUES (3, 'after');\n"
        );

        $this->assertTrue($res['ok'], join("\n", $res['errors']));
        $this->assertSame([1 => 'C:\\', 2 => "it's", 3 => 'after'], $this->rows());
    }

    public function testParserReportsAnUnparsedTailInsteadOfDroppingItSilently(): void
    {
        Postgresql::setRestoreCommand('/nonexistent/psql');

        $res = $this->restore(
            "INSERT INTO di_core_restore_t VALUES (1, 'a');\n" .
                "INSERT INTO di_core_restore_t VALUES (2, 'b')\n"
        );

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('Unparsed tail', join("\n", $res['errors']));
    }

    public function testPsqlRestoresInOneTransaction(): void
    {
        $this->useLocalPsql();

        $res = $this->restore(
            "INSERT INTO di_core_restore_t VALUES (1, 'C:\\');\n" .
                "INSERT INTO di_core_restore_t VALUES (2, \$\$a;\nb\$\$);\n"
        );

        $this->assertTrue($res['ok'], join("\n", $res['errors']));
        $this->assertSame([1 => 'C:\\', 2 => "a;\nb"], $this->rows());
    }

    public function testPsqlFailureIsAnErrorAndTheParserDoesNotRepeatTheFile(): void
    {
        $this->useLocalPsql();

        $res = $this->restore(
            "INSERT INTO di_core_restore_t VALUES (1, 'a');\n" .
                "INSERT INTO no_such_table VALUES (1);\n"
        );

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('psql failed', join("\n", $res['errors']));
        // single-transaction откатил всё, парсер не применил первую строку заново
        $this->assertSame([], $this->rows());
    }

    public function testGzipDumpGoesThroughPsqlToo(): void
    {
        $this->useLocalPsql();

        $name = 'd.sql.gz';
        file_put_contents(
            $this->folder . $name,
            gzencode("INSERT INTO di_core_restore_t VALUES (1, 'z');\n")
        );
        $c = (new \ReflectionClass(Db::class))->newInstanceWithoutConstructor();

        foreach (['file' => $name, 'folder' => $this->folder] as $prop => $value) {
            $p = new \ReflectionProperty(Db::class, $prop);
            $p->setAccessible(true);
            $p->setValue($c, $value);
        }

        $res = $c->restoreAction();
        $this->assertTrue($res['ok'], join("\n", $res['errors']));
        $this->assertSame([1 => 'z'], $this->rows());
    }
}
