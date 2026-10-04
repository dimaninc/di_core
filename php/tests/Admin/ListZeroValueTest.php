<?php

namespace diCore\Tests\Admin;

use PHPUnit\Framework\TestCase;

/**
 * diAdminList renders a cell through prepareReplaceAr() (`%field%`) and replaceValues()
 * (every cell, including a `value` callback's result). Both tested truthiness, so a zero –
 * an option titled "0", 0 points – rendered as an empty cell.
 */
class ListZeroValueTest extends TestCase
{
    public function testZeroIsAValue(): void
    {
        $list = $this->listWith([
            'title' => '0',
            'points' => 0,
            'none' => null,
            'off' => false,
            'on' => true,
            'tags' => ['a'],
        ]);

        $this->assertSame('0', $this->render($list, '%title%'));
        $this->assertSame('0', $this->render($list, '%points%'));
        $this->assertSame('', $this->render($list, '%none%'));
        $this->assertSame('', $this->render($list, '%off%'));
        $this->assertSame('1', $this->render($list, '%on%'));
        $this->assertSame('["a"]', $this->render($list, '%tags%'));
    }

    public function testCallbackZeroSurvivesReplace(): void
    {
        $list = $this->listWith([]);

        $this->assertSame('0', $this->render($list, '0'));
        $this->assertSame('', $this->render($list, ''));
        $this->assertSame('', $this->render($list, null));
    }

    private function listWith(array $row): \diAdminList
    {
        // The row directly, no model: diModel asks the DB connection for field types.
        $list = new class ($row) extends \diAdminList {
            public function __construct(private array $row)
            {
            }

            public function getCurRec()
            {
                return (object) $this->row;
            }
        };

        $prepare = new \ReflectionMethod(\diAdminList::class, 'prepareReplaceAr');
        $prepare->setAccessible(true);
        $prepare->invoke($list);

        return $list;
    }

    private function render(\diAdminList $list, $template): string
    {
        $replace = new \ReflectionMethod(\diAdminList::class, 'replaceValues');
        $replace->setAccessible(true);

        return $replace->invoke($list, $template);
    }
}
