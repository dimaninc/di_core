<?php

namespace diCore\Tests\Admin;

use diCore\Admin\Form;
use diCore\Database\FieldType;
use PHPUnit\Framework\TestCase;

/**
 * A JSON column often holds text a visitor sent (a user agent, utm): the list's
 * `%field%` and the form's static JSON field must not print it as markup.
 */
class JsonOutputEscapingTest extends TestCase
{
    private const PAYLOAD = '<img src=x onerror=alert(1)>';

    /** From SQL a JSON column arrives as a string: the field type decides, not the shape. */
    public function testListEscapesJsonColumnFromSql(): void
    {
        $cell = $this->render(
            $this->listWith([
                'properties' => json_encode(['user_agent' => self::PAYLOAD]),
            ]),
            '%properties%'
        );

        $this->assertStringNotContainsString('<img', $cell);
        $this->assertStringContainsString('&lt;img', $cell);
    }

    /** Mongo hands over the decoded document. */
    public function testListEscapesDecodedJsonColumn(): void
    {
        $cell = $this->render(
            $this->listWith(['properties' => ['user_agent' => self::PAYLOAD]]),
            '%properties%'
        );

        $this->assertStringNotContainsString('<img', $cell);
    }

    /** Any non-scalar value is data, declared JSON or not (an undeclared Mongo array). */
    public function testListEscapesUndeclaredArray(): void
    {
        $this->assertSame(
            '[&quot;&lt;b&gt;&quot;]',
            $this->render($this->listWith(['tags' => ['<b>']]), '%tags%')
        );
    }

    /** Ready entities are text already: kept as they are, the rest escaped. */
    public function testEscapeTextKeepsReadyEntities(): void
    {
        $this->assertSame(
            'Акция &laquo;Весна&raquo; &amp; &lt;b&gt; &#37;id&#37;',
            \diAdminList::escapeText('Акция &laquo;Весна&raquo; & <b> %id%')
        );
    }

    /** A scalar column the model does not declare JSON keeps going in raw. */
    public function testUndeclaredScalarColumnStaysRaw(): void
    {
        $this->assertSame(
            '<b>x</b>',
            $this->render($this->listWith(['note' => '<b>x</b>']), '%note%')
        );
    }

    /** One pass: a value carrying `%other%` must not pull in that column's raw value. */
    public function testSubstitutedValueIsNotSubstitutedAgain(): void
    {
        // `properties` first: str_replace substituted keys in this order.
        $list = $this->listWith([
            'properties' => json_encode(['user_agent' => '%note%']),
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
        // The row as SQL returns it, and a model declaring `properties` JSON; no DB.
        $list = new class ($row) extends \diAdminList {
            public function __construct(private array $row)
            {
            }

            public function getCurRec()
            {
                return (object) $this->row;
            }

            public function getCurModel()
            {
                return new class extends \diModel {
                    protected static $fieldTypes = [
                        'properties' => FieldType::json,
                    ];

                    public function __construct()
                    {
                    }
                };
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
