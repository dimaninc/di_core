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
        $db->q("INSERT INTO di_core_fu VALUES (1, 'anna@example.com'), (2, 'bob@test.org')");
        $db->q('INSERT INTO di_core_fp VALUES (10, 1), (11, 2), (12, 0)');

        try {
            $where = \diAdminFilters::getUserWhere(['email'], 'di_core_fu');
            $ids = function ($value) use ($db, $where) {
                $rs = $db->q(
                    'SELECT id FROM di_core_fp WHERE ' .
                        $where('user_id', $value) .
                        ' ORDER BY id'
                );
                $res = [];

                while ($r = $db->fetch_array($rs)) {
                    $res[] = (int) $r['id'];
                }

                return $res;
            };

            $this->assertSame([11], $ids('2'), 'by user id');
            $this->assertSame([10], $ids('anna'), 'by part of email');
            $this->assertSame([11], $ids("test.org"));
            $this->assertSame([], $ids("x'y"), 'quote is escaped');
        } finally {
            $db->q('DROP TABLE IF EXISTS di_core_fu');
            $db->q('DROP TABLE IF EXISTS di_core_fp');
        }
    }
}
