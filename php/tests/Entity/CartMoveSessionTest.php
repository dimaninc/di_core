<?php

namespace diCore\Tests\Entity;

use diCore\Entity\Cart\Model as Cart;
use PHPUnit\Framework\TestCase;

/**
 * The guest cart is keyed on the raw session id, which is rotated at sign-in:
 * looked up by the new id, it was lost.
 */
class CartMoveSessionTest extends TestCase
{
    private ?\diDB $db = null;
    private string $table = '';

    protected function setUp(): void
    {
        $model = \diModel::create(Cart::type);
        $this->table = $model->getTable();
        $conn = $model::getConnection();

        // never touch a real cart: the suite also runs inside consumer projects
        if (in_array($this->table, $conn->getTableNames(), true)) {
            $this->markTestSkipped("table '$this->table' exists: not a throwaway one");
        }

        $id = match (Cart::getConnectionEngine()) {
            \diCore\Database\Engine::SQLITE => 'id INTEGER PRIMARY KEY AUTOINCREMENT',
            \diCore\Database\Engine::POSTGRESQL => 'id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY',
            \diCore\Database\Engine::MYSQL,
            \diCore\Database\Engine::MYSQL_OLD => 'id BIGINT AUTO_INCREMENT PRIMARY KEY',
            default => null,
        };

        if ($id === null) {
            $this->markTestSkipped('no throwaway DDL for this engine');
        }

        $this->db = $conn->getDb();
        $this->db->q(
            "CREATE TABLE {$this->db->escapeTable($this->table)} (
                $id,
                session_id VARCHAR(64) DEFAULT '',
                user_id BIGINT
            )"
        );
    }

    protected function tearDown(): void
    {
        $this->db?->q("DROP TABLE IF EXISTS {$this->db->escapeTable($this->table)}");
    }

    private function sessionIds(): array
    {
        $rs = $this->db->q(
            "SELECT session_id FROM {$this->db->escapeTable($this->table)} ORDER BY id"
        );
        $ids = [];

        while ($rs && ($r = $this->db->fetch($rs))) {
            $ids[] = $r->session_id;
        }

        return $ids;
    }

    public function testGuestCartFollowsTheNewId(): void
    {
        $this->db->insert($this->table, ['session_id' => 'old-id']);
        $this->db->insert($this->table, ['session_id' => 'someone-else']);

        Cart::moveSession('old-id', 'new-id');

        $this->assertSame(['new-id', 'someone-else'], $this->sessionIds());
    }
}
