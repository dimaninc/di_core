<?php

namespace diCore\Tests\Tool\Code;

use diCore\Tool\Code\AdminPagesManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Column type from DB introspection → form field type of a generated admin page.
 * Postgres gives full type names («timestamp with time zone») and keeps every string in
 * text, so neither the type of a time column nor «text» may be read the MySQL way.
 */
class AdminPagesManagerFieldTypeTest extends TestCase
{
    private function tune(string $field, string $type, bool $postgres): string
    {
        $m = (new \ReflectionClass(AdminPagesManager::class))->newInstanceWithoutConstructor();
        $flag = new \ReflectionProperty(AdminPagesManager::class, 'textIsLongContent');
        $flag->setAccessible(true);
        $flag->setValue($m, !$postgres);
        $tune = new \ReflectionMethod(AdminPagesManager::class, 'tuneType');
        $tune->setAccessible(true);

        return $tune->invoke($m, $field, $type);
    }

    public static function postgresTemporalTypes(): array
    {
        return [
            'timestamp' => ['timestamp without time zone', 'datetime_str'],
            'timestamptz' => ['timestamp with time zone', 'datetime_str'],
            'timestamptz short' => ['timestamptz', 'datetime_str'],
            'time' => ['time without time zone', 'time_str'],
            'timetz' => ['time with time zone', 'time_str'],
            'upper case' => ['TIMESTAMP WITH TIME ZONE', 'datetime_str'],
        ];
    }

    #[DataProvider('postgresTemporalTypes')]
    public function testPostgresTemporalTypesByTypeNotByName(string $dbType, string $expected): void
    {
        // expired_at is not in the name lists – only the type can tell.
        $this->assertSame($expected, $this->tune('expired_at', $dbType, true));
    }

    public function testPostgresTextIsOneLineUnlessContentByName(): void
    {
        $this->assertSame('string', $this->tune('login', 'text', true));
        $this->assertSame('string', $this->tune('title', 'text', true));
        $this->assertSame('wysiwyg', $this->tune('content', 'text', true));
        $this->assertSame('wysiwyg', $this->tune('short_content', 'text', true));
    }

    public function testMysqlUnchanged(): void
    {
        $this->assertSame('wysiwyg', $this->tune('title', 'text', false));
        $this->assertSame('string', $this->tune('title', 'varchar(255)', false));
        $this->assertSame('datetime_str', $this->tune('expired_at', 'datetime', false));
        $this->assertSame('int', $this->tune('weight', 'int(10) unsigned', false));
    }
}
