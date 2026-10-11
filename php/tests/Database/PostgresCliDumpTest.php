<?php

namespace diCore\Tests\Database;

use diCore\Database\Legacy\Postgresql;
use diCore\Helper\ProcessHelper;
use diCore\Tests\Entity\AuthorizationPinTestDb;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Entity/AuthorizationPinTestDb.php';

/**
 * Дамп и восстановление PostgreSQL идут штатными pg_dump/psql: массив аргументов без шелла,
 * пароль только в окружении. Живая часть: DI_CORE_TEST_DB=postgresql://… и pg_dump/psql в PATH
 * (или DI_CORE_PG_BIN=/путь/к/каталогу с ними), иначе пропускается.
 */
class PostgresCliDumpTest extends TestCase
{
    private function makeDb(): Postgresql
    {
        $db = (new \ReflectionClass(Postgresql::class))->newInstanceWithoutConstructor();

        foreach (
            [
                'host' => 'db.example',
                'port' => 6543,
                'username' => 'app',
                'password' => 'p@ss w;rd',
                'dbname' => 'shop',
            ]
            as $name => $value
        ) {
            $p = new \ReflectionProperty(Postgresql::class, $name);
            $p->setAccessible(true);
            $p->setValue($db, $value);
        }

        return $db;
    }

    public function testDumpArgvHasNoPasswordAndTakesOptions(): void
    {
        $argv = $this->makeDb()->getDumpArgv([
            'filename' => '/tmp/x.sql',
            'tables' => ['a', 'b'],
            'drops' => 1,
            'schemaOnly' => true,
        ]);

        $this->assertSame('pg_dump', $argv[0]);
        $this->assertContains('--host=db.example', $argv);
        $this->assertContains('--port=6543', $argv);
        $this->assertContains('--username=app', $argv);
        $this->assertContains('--dbname=shop', $argv);
        $this->assertContains('--no-password', $argv);
        $this->assertContains('--file=/tmp/x.sql', $argv);
        $this->assertContains('--table=a', $argv);
        $this->assertContains('--table=b', $argv);
        $this->assertContains('--clean', $argv);
        $this->assertContains('--schema-only', $argv);
        $this->assertNotContains('--data-only', $argv);
        $this->assertStringNotContainsString('p@ss', join(' ', $argv));
    }

    public function testDataOnlyDumpDropsNoCleanFlags(): void
    {
        // pg_dump отвергает --clean вместе с --data-only
        $argv = $this->makeDb()->getDumpArgv([
            'filename' => '/tmp/x.sql',
            'drops' => 1,
            'dataOnly' => true,
        ]);

        $this->assertContains('--data-only', $argv);
        $this->assertNotContains('--clean', $argv);
        $this->assertNotContains('--if-exists', $argv);
    }

    public function testRestoreArgvStopsOnFirstError(): void
    {
        $argv = $this->makeDb()->getRestoreArgv('/tmp/x.sql');

        $this->assertContains('--single-transaction', $argv);

        $this->assertSame('psql', $argv[0]);
        $this->assertContains('--set=ON_ERROR_STOP=1', $argv);
        $this->assertContains('--file=/tmp/x.sql', $argv);
        $this->assertStringNotContainsString('p@ss', join(' ', $argv));
    }

    public function testPasswordTravelsInEnvironmentOnly(): void
    {
        $this->assertSame(
            'p@ss w;rd',
            $this->makeDb()->getCliEnv()['PGPASSWORD']
        );
    }

    public function testFailureAndTimeoutAreVisibleToTheCaller(): void
    {
        $failed = ProcessHelper::run(['sh', '-c', 'echo "no such db" 1>&2; exit 1'], 5.0);
        $this->assertSame(1, $failed['code']);
        $this->assertSame(['no such db'], $failed['output']);

        $timedOut = ProcessHelper::run(['sleep', '10'], 0.1);
        $this->assertTrue($timedOut['timedOut']);
        $this->assertNotSame(0, $timedOut['code']);
    }

    public function testLiveDumpAndRestoreRoundTrip(): void
    {
        $dsn = getenv('DI_CORE_TEST_DB');

        if (!$dsn || parse_url($dsn, PHP_URL_SCHEME) !== 'postgresql') {
            $this->markTestSkipped('Needs DI_CORE_TEST_DB=postgresql://…');
        }

        $bin = rtrim((string) getenv('DI_CORE_PG_BIN'), '/');
        $bin = $bin ? "$bin/" : '';

        if (ProcessHelper::run([$bin . 'pg_dump', '--version'], 5.0)['code'] !== 0) {
            $this->markTestSkipped('pg_dump is not available');
        }

        AuthorizationPinTestDb::open();

        try {
            $db = AuthorizationPinTestDb::db();
            $this->assertInstanceOf(Postgresql::class, $db);

            $db->q('DROP TABLE IF EXISTS di_core_dump_t');
            $db->q('CREATE TABLE di_core_dump_t (id integer PRIMARY KEY, v text)');
            $db->q("INSERT INTO di_core_dump_t VALUES (1, 'it''s'), (2, NULL)");

            $file = sys_get_temp_dir() . '/di_core_dump_' . getmypid() . '.sql';
            $env = $db->getCliEnv();
            $argv = $db->getDumpArgv([
                'filename' => $file,
                'tables' => ['di_core_dump_t'],
                'drops' => 1,
            ]);
            $argv[0] = $bin . $argv[0];

            $res = ProcessHelper::run($argv, 60.0, $env);
            $this->assertSame(0, $res['code'], join("\n", $res['output']));
            $this->assertStringContainsString('INSERT INTO public.di_core_dump_t', file_get_contents($file));

            $db->q('DELETE FROM di_core_dump_t');
            $argv = $db->getRestoreArgv($file);
            $argv[0] = $bin . $argv[0];
            $res = ProcessHelper::run($argv, 60.0, $env);
            $this->assertSame(0, $res['code'], join("\n", $res['output']));

            $rs = $db->q('SELECT v FROM di_core_dump_t ORDER BY id');
            $this->assertSame("it's", $db->fetch_array($rs)['v']);

            // Неверный пароль: сбой виден по коду, а не по пустому файлу
            $bad = ProcessHelper::run($argv, 60.0, ['PGPASSWORD' => 'definitely-wrong']);
            $this->assertNotSame(0, $bad['code']);

            $db->q('DROP TABLE di_core_dump_t');
            unlink($file);
        } finally {
            AuthorizationPinTestDb::close();
        }
    }
}
