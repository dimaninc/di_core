<?php

namespace diCore\Tests\Admin;

use diCore\Tests\Entity\AuthorizationPinTestDb;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Entity/AuthorizationPinTestDb.php';

/**
 * Фильтр по пользователю (diAdminFilters::getUserWhere, им же пользуется PaymentDrafts)
 * должен исполняться на любом движке: id или часть email, без INSTR и backticks.
 * Живой тест: DI_CORE_TEST_DB=postgresql://… или mysql://…
 */
class UserWhereFilterTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        AuthorizationPinTestDb::open();
    }

    public static function tearDownAfterClass(): void
    {
        AuthorizationPinTestDb::close();
    }

    public function testLiveFindsByIdAndByPartOfEmail(): void
    {
        AuthorizationPinTestDb::need($this);

        $db = AuthorizationPinTestDb::db();
        $property = new \ReflectionProperty(\diAdminFilters::class, 'db');
        $property->setAccessible(true);
        $property->setValue(null, $db);

        $db->q('DROP TABLE IF EXISTS di_core_fu');
        $db->q('DROP TABLE IF EXISTS di_core_fp');
        $db->q('CREATE TABLE di_core_fu (id integer PRIMARY KEY, email varchar(100))');
        $db->q('CREATE TABLE di_core_fp (id integer PRIMARY KEY, user_id integer)');
        $db->q("INSERT INTO di_core_fu VALUES (1, 'anna@example.com'), (2, 'bob@test.org'), (3, 'o''brien@x.org')");
        $db->q('INSERT INTO di_core_fp VALUES (10, 1), (11, 2), (12, 3)');

        // Без global $db: экранировать должно соединение самих фильтров
        $savedGlobalDb = $GLOBALS['db'] ?? null;
        $GLOBALS['db'] = null;
        $_GET['admin_filter'] = [];

        try {
            // Через buildQuery(), как на странице: он экранирует значение сам (StringHelper::in),
            // и повторное экранирование ломало бы поиск по кавычке
            $ids = function ($value) use ($db) {
                $_GET['admin_filter']['user_id'] = $value;

                $filters = new \diAdminFilters('admins');
                $filters->addFilter([
                    'field' => 'user_id',
                    'type' => 'string',
                    'where_tpl' => \diAdminFilters::getUserWhere(['email'], 'di_core_fu'),
                ]);
                $filters->buildQuery();

                $rs = $db->q(
                    'SELECT id FROM di_core_fp ' . $filters->getQuery() . ' ORDER BY id'
                );
                $res = [];

                while ($r = $db->fetch_array($rs)) {
                    $res[] = (int) $r['id'];
                }

                return $res;
            };

            $this->assertSame([11], $ids('2'), 'by user id');
            $this->assertSame([10], $ids('anna'), 'by part of email');
            $this->assertSame([11], $ids('test.org'));
            $this->assertSame([12], $ids("o'brien"), 'a quote is escaped once');
            $this->assertSame([], $ids("x' OR '1'='1"), 'injection finds nothing');
            // На PostgreSQL без escape соединения это закрыло бы литерал (\' + standard_conforming_strings)
            $this->assertSame([], $ids("' OR 1=1 --"), 'a backslash-quote injection finds nothing');
            $this->assertSame([], $ids('2abc'), '"2abc" is not user #2');
            $this->assertSame([10], $ids('ANNA@'), 'email search ignores case');
        } finally {
            unset($_GET['admin_filter']);
            $GLOBALS['db'] = $savedGlobalDb;
            $db->q('DROP TABLE IF EXISTS di_core_fu');
            $db->q('DROP TABLE IF EXISTS di_core_fp');
        }
    }
}
