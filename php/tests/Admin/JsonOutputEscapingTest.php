<?php

namespace diCore\Tests\Admin;

use diCore\Admin\Form;
use PHPUnit\Framework\TestCase;

/**
 * A JSON column often holds text a visitor sent (a user agent, utm): the list's
 * `%field%` and the form's static JSON field must not print it as markup.
 */
class JsonOutputEscapingTest extends TestCase
{
    private const PAYLOAD = '<img src=x onerror=alert(1)>';

    public function testListEscapesJsonColumn(): void
    {
        $cell = $this->render(
            $this->listWith(['properties' => ['user_agent' => self::PAYLOAD]]),
            '%properties%'
        );

        $this->assertStringNotContainsString('<img', $cell);
        $this->assertStringContainsString('&lt;img', $cell);
    }

    /** One pass: a value carrying `%other%` must not pull in that column's raw value. */
    public function testSubstitutedValueIsNotSubstitutedAgain(): void
    {
        // `properties` first: str_replace substituted keys in this order.
        $list = $this->listWith([
            'properties' => ['user_agent' => '%note%'],
            'note' => self::PAYLOAD,
        ]);

        $cell = $this->render($list, '%properties%');

        $this->assertStringNotContainsString('<img', $cell);
        $this->assertStringContainsString('%note%', $cell);
    }

    public function testFormEscapesStaticJsonField(): void
    {
        $printed = Form::valuePrinterJson(
            json_encode(['user_agent' => self::PAYLOAD]),
            'properties'
        );

        $this->assertStringNotContainsString('<img', $printed);
        $this->assertStringContainsString('&lt;img', $printed);
    }

    /** Explicit ENT_QUOTES alone drops ENT_SUBSTITUTE: invalid UTF-8 blanked the field. */
    public function testFormKeepsFieldWithInvalidUtf8(): void
    {
        $this->assertStringContainsString(
            'ok',
            Form::valuePrinterJson(['ua' => "ok\xff"], 'properties')
        );
    }

    /** Visitor text from a callback: its «%column%» must not pull in the raw value. */
    public function testEscapedCallbackTextIsNotSubstituted(): void
    {
        $text = self::PAYLOAD . ' %content%';
        $list = $this->listWith(['content' => $text]);

        $cell = $this->render($list, \diAdminList::escapeText($text));

        $this->assertStringNotContainsString('<img', $cell);
        $this->assertStringContainsString('&#37;content&#37;', $cell);
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
        $prepare->invoke($list);

        return $list;
    }

    private function render(\diAdminList $list, string $template): string
    {
        return (new \ReflectionMethod(\diAdminList::class, 'replaceValues'))->invoke(
            $list,
            $template
        );
    }
}
