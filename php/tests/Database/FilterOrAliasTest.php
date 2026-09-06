<?php

namespace diCore\Tests\Database;

use PHPUnit\Framework\TestCase;

/**
 * `filterOr()` квалифицирует поле алиасом так же, как `filterBy*`.
 *
 * Без этого голая колонка в запросе с JOIN на таблицу с такой же колонкой даёт
 * «Column ... in where clause is ambiguous» – падение запроса, а не тихую
 * ошибку, и проявляется оно у первого же вызывающего с джойном.
 */
class FilterOrAliasTest extends TestCase
{
    private function where(\diCollection $collection): string
    {
        $method = new \ReflectionMethod($collection, 'getBuiltQueryWhere');
        $method->setAccessible(true);

        return (string) $method->invoke($collection);
    }

    private function collection(): \diCollection
    {
        return \diCollection::create(\diTypes::content)->filterOr([
            'visible' => 1,
            'visible_top' => 1,
        ]);
    }

    public function testBothSidesOfTheOrCarryTheAlias(): void
    {
        $where = $this->where($this->collection());
        $alias = \diCollection::MAIN_TABLE_ALIAS;

        $this->assertStringContainsString("`$alias`.`visible`", $where);
        $this->assertStringContainsString("`$alias`.`visible_top`", $where);
    }

    /** Тот же алиас, что ставит `filterBy*` – иначе «одно правило» разъедется. */
    public function testAliasMatchesTheOneFilterByUses(): void
    {
        $expected = preg_replace(
            '/^WHERE\s+/',
            '',
            $this->where(
                \diCollection::create(\diTypes::content)->filterBy('visible', 1)
            )
        );

        $this->assertNotSame('', $expected);
        $this->assertStringContainsString(
            $expected,
            $this->where($this->collection())
        );
    }
}
