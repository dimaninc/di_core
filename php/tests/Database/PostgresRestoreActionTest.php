<?php

namespace diCore\Tests\Database;

use diCore\Controller\Db;
use diCore\Database\Legacy\Postgresql;
use diCore\Helper\ProcessHelper;
use diCore\Tests\Entity\AuthorizationPinTestDb;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Entity/AuthorizationPinTestDb.php';

/**
 * Восстановление дампа PostgreSQL со страницы: по умолчанию построчный парсер; psql (одной
 * транзакцией) только при system=1, его сбой – ошибка (не повтор парсером), а если psql нет –
 * парсер. Живой тест:
 * DI_CORE_TEST_DB=postgresql://…; для ветки psql нужен он сам (DI_CORE_PG_BIN – каталог).
 */
class PostgresRestoreActionTest extends TestCase
{
    /** @var string|null Свой временный каталог; null – setUp() ничего не создал */
    private $folder = null;

    public static function setUpBeforeClass(): void
    {
        // Пропуск до создания чего-либо: tearDown() после него не должен ничего трогать
        $dsn = getenv('DI_CORE_TEST_DB');

        if (!$dsn || parse_url($dsn, PHP_URL_SCHEME) !== 'postgresql') {
            self::markTestSkipped('Needs DI_CORE_TEST_DB=postgresql://…');
        }

        AuthorizationPinTestDb::open();
    }

    public static function tearDownAfterClass(): void
    {
        AuthorizationPinTestDb::close();
    }

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . '/di_core_restore_' . getmypid() . '_' . uniqid() . '/';
        mkdir($this->folder);
        $db = AuthorizationPinTestDb::db();
        $db->q('DROP TABLE IF EXISTS di_core_restore_t');
        $db->q('CREATE TABLE di_core_restore_t (id integer PRIMARY KEY, v text)');
    }

    protected function tearDown(): void
    {
        Postgresql::setRestoreCommand('psql');
        Postgresql::setDumpCommand('pg_dump');

        if ($this->folder === null) {
            return;
        }

        AuthorizationPinTestDb::db()->q('DROP TABLE IF EXISTS di_core_restore_t');

        // Только внутри собственного каталога
        if (strpos($this->folder, sys_get_temp_dir() . '/di_core_restore_') === 0) {
            foreach (glob($this->folder . '*') as $f) {
                is_dir($f) ? rmdir($f) : unlink($f);
            }

            rmdir($this->folder);
        }

        $this->folder = null;
    }

    private function restore(string $sql, string $name = 'd.sql', bool $system = false): array
    {
        file_put_contents($this->folder . $name, $sql);

        return $this->restoreFile($name, $system);
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
                "INSERT INTO di_core_restore_t VALUES (2, \$\$a;\nb\$\$);\n",
            'd.sql',
            true
        );

        $this->assertTrue($res['ok'], join("\n", $res['errors']));
        $this->assertSame([1 => 'C:\\', 2 => "a;\nb"], $this->rows());
    }

    public function testDefaultRestoreDoesNotUsePsql(): void
    {
        // psql, который всегда падает: без system=1 он не должен вызываться
        Postgresql::setRestoreCommand('/usr/bin/false');

        $res = $this->restore("INSERT INTO di_core_restore_t VALUES (1, 'a');\n");

        $this->assertTrue($res['ok'], join("\n", $res['errors']));
        $this->assertSame([1 => 'a'], $this->rows());
    }

    public function testSystemRestoreWithBrokenPsqlIsAnErrorNotAParserRetry(): void
    {
        Postgresql::setRestoreCommand('/usr/bin/false');

        $res = $this->restore("INSERT INTO di_core_restore_t VALUES (1, 'a');\n", 'd.sql', true);

        $this->assertFalse($res['ok']);
        $this->assertSame([], $this->rows());
    }

    public function testSystemRestoreWithoutPsqlFallsBackToTheParser(): void
    {
        Postgresql::setRestoreCommand('/nonexistent/psql');

        $res = $this->restore("INSERT INTO di_core_restore_t VALUES (1, 'a');\n", 'd.sql', true);

        $this->assertTrue($res['ok'], join("\n", $res['errors']));
        $this->assertSame([1 => 'a'], $this->rows());
    }

    public function testPsqlFailureIsAnErrorAndTheParserDoesNotRepeatTheFile(): void
    {
        $this->useLocalPsql();

        $res = $this->restore(
            "INSERT INTO di_core_restore_t VALUES (1, 'a');\n" .
                "INSERT INTO no_such_table VALUES (1);\n",
            'd.sql',
            true
        );

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('psql failed', join("\n", $res['errors']));
        // single-transaction откатил всё, парсер не применил первую строку заново
        $this->assertSame([], $this->rows());
    }

    /** $system – запрос с system=1 (psql); без него страница и миграции идут парсером */
    private function restoreFile(string $name, bool $system = false): array
    {
        $_GET['system'] = $system ? 1 : 0;

        $c = (new \ReflectionClass(Db::class))->newInstanceWithoutConstructor();

        foreach (['file' => $name, 'folder' => $this->folder] as $prop => $value) {
            $p = new \ReflectionProperty(Db::class, $prop);
            $p->setAccessible(true);
            $p->setValue($c, $value);
        }

        return $c->restoreAction();
    }

    /** Время без смещения читается одинаково через psql и через парсер (зона PHP) */
    public function testPsqlAndParserReadTimeWithoutOffsetAlike(): void
    {
        $db = AuthorizationPinTestDb::db();
        $zone = date_default_timezone_get();
        $db->q('DROP TABLE IF EXISTS di_core_restore_tz');
        $db->q('CREATE TABLE di_core_restore_tz (id integer PRIMARY KEY, t timestamptz)');

        try {
            // Сессия PHP и PGTZ – одна зона, не равная зоне сервера
            date_default_timezone_set('Asia/Tokyo');
            $db->q("SET TIME ZONE 'Asia/Tokyo'");
            $sql = "INSERT INTO di_core_restore_tz VALUES (%d, '2026-01-01 10:00:00');\n";

            Postgresql::setRestoreCommand('/nonexistent/psql');
            $this->assertTrue($this->restore(sprintf($sql, 1))['ok']);

            $this->useLocalPsql();
            $this->assertTrue($this->restore(sprintf($sql, 2), 'd2.sql', true)['ok']);

            $rs = $db->q('SELECT t FROM di_core_restore_tz ORDER BY id');
            $times = [];

            while ($r = $db->fetch_array($rs)) {
                $times[] = $r['t'];
            }

            $this->assertCount(2, $times);
            $this->assertSame($times[0], $times[1], 'psql and the parser disagree on the zone');
        } finally {
            date_default_timezone_set($zone);
            $db->q("SET TIME ZONE '$zone'");
            $db->q('DROP TABLE IF EXISTS di_core_restore_tz');
        }
    }

    public function testGzipDumpGoesThroughPsqlToo(): void
    {
        $this->useLocalPsql();

        file_put_contents(
            $this->folder . 'd.sql.gz',
            gzencode("INSERT INTO di_core_restore_t VALUES (1, 'z');\n")
        );

        $res = $this->restoreFile('d.sql.gz', true);
        $this->assertTrue($res['ok'], join("\n", $res['errors']));
        $this->assertSame([1 => 'z'], $this->rows());
    }

    /**
     * Любой дефект архива – ok=false и ничего не применено: иначе миграция записалась бы
     * применённой (Migration::assertRestored).
     */
    public function testBadGzipArchivesAreErrors(): void
    {
        $this->useLocalPsql();

        $good = gzencode(
            "INSERT INTO di_core_restore_t VALUES (1, 'z');\n" .
                str_repeat("-- padding line\n", 5000)
        );
        $corrupt = $good;
        $corrupt[strlen($good) - 6] = chr(ord($good[strlen($good) - 6]) ^ 0xff); // CRC

        $cases = [
            'missing.sql.gz' => null,
            'empty.sql.gz' => '',
            'truncated.sql.gz' => substr($good, 0, (int) (strlen($good) / 2)),
            'corrupt.sql.gz' => $corrupt,
            'notgz.sql.gz' => "INSERT INTO di_core_restore_t VALUES (1, 'z');\n",
        ];

        foreach ($cases as $name => $content) {
            if ($content !== null) {
                file_put_contents($this->folder . $name, $content);
            }

            $res = $this->restoreFile($name, true);
            $this->assertFalse($res['ok'], $name);
            $this->assertNotEmpty($res['errors'], $name);
            $this->assertSame([], $this->rows(), $name);
        }
    }

    private function dump(array $args): array
    {
        $c = (new \ReflectionClass(Db::class))->newInstanceWithoutConstructor();
        $m = new \ReflectionMethod($c, 'createPostgresDump');
        $m->setAccessible(true);

        return $m->invoke($c, ['ok' => false, 'text' => ''], ...$args);
    }

    /** Подменный pg_dump: пишет --file= и ничего больше */
    private function fakePgDump(bool $writes): void
    {
        $script = $this->folder . 'fake_pg_dump.sh';
        // pg_dump пишет дамп в stdout; пустой stdout – «ничего не написал»
        file_put_contents(
            $script,
            "#!/bin/sh\n" . ($writes ? "echo '-- dump'\n" : "exit 0\n")
        );
        chmod($script, 0755);
        Postgresql::setDumpCommand($script);
    }

    public function testDumpWithoutAFileAfterZeroExitIsAnError(): void
    {
        $this->fakePgDump(false);

        $res = $this->dump([$this->folder . 'x.sql.gz', [], 1]);

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('no dump file', $res['text']);
        $this->assertSame([], glob($this->folder . 'x.sql*'));
    }

    public function testFailedCompressionKeepsThePlainDumpAndLeavesNoArchive(): void
    {
        $this->fakePgDump(true);
        // Каталог на месте архива: gzopen() не откроет
        mkdir($this->folder . 'x.sql.gz');

        $res = $this->dump([$this->folder . 'x.sql.gz', [], 1]);

        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('Unable to compress', $res['text']);
        $this->assertFileExists($this->folder . 'x.sql', 'the intact plain dump stays');
    }

    public function testDumpFilesGetTheStandardMode(): void
    {
        $this->fakePgDump(true);

        foreach (['p.sql' => 0, 'z.sql.gz' => 1] as $name => $compress) {
            $res = $this->dump([$this->folder . $name, [], $compress]);

            $this->assertTrue($res['ok'], $res['text']);
            $this->assertSame(Db::CHMOD_FILE, fileperms($this->folder . $name) & 0777, $name);
        }
    }

    public function testRealPgDumpTakesTableNamesExactly(): void
    {
        $bin = rtrim((string) getenv('DI_CORE_PG_BIN'), '/');
        $pgDump = ($bin ? "$bin/" : '') . 'pg_dump';

        if (ProcessHelper::run([$pgDump, '--version'], 5.0)['code'] !== 0) {
            $this->markTestSkipped('pg_dump is not available');
        }

        $db = AuthorizationPinTestDb::db();
        $names = ['DiCoreMixedCase', 'di.core*dot'];

        try {
            foreach ($names as $name) {
                $db->q('CREATE TABLE "' . $name . '" (id integer)');
            }

            Postgresql::setDumpCommand($pgDump);
            $res = $this->dump([$this->folder . 'x.sql', $names, 0]);

            $this->assertTrue($res['ok'], $res['text']);

            $dump = file_get_contents($this->folder . 'x.sql');

            foreach ($names as $name) {
                $this->assertStringContainsString('CREATE TABLE public."' . $name . '"', $dump);
            }
        } finally {
            foreach ($names as $name) {
                $db->q('DROP TABLE IF EXISTS "' . $name . '"');
            }
        }
    }

    public function testCompressedDumpReplacesThePlainOne(): void
    {
        $this->fakePgDump(true);

        $res = $this->dump([$this->folder . 'x.sql.gz', [], 1]);

        $this->assertTrue($res['ok'], $res['text']);
        $this->assertFileDoesNotExist($this->folder . 'x.sql');
        $this->assertSame("-- dump\n", gzdecode(file_get_contents($this->folder . 'x.sql.gz')));
    }
}
