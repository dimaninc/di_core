<?php

namespace diCore\Tests\Tool\Code;

use diCore\Tool\Code\ModelsManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Тип колонки из интроспекции БД → тип поля генерируемой модели. У Postgres
 * information_schema отдаёт полные имена («timestamp with time zone»), и тип без своей
 * ветки молча становится string.
 */
class ModelsManagerFieldTypeTest extends TestCase
{
    public static function postgresTemporalTypes(): array
    {
        return [
            'timestamp' => ['timestamp without time zone', 'timestamp'],
            'timestamptz' => ['timestamp with time zone', 'timestamp'],
            'timestamptz short' => ['timestamptz', 'timestamp'],
            'time' => ['time without time zone', 'time'],
            'timetz' => ['time with time zone', 'time'],
            'timetz short' => ['timetz', 'time'],
            'upper case' => ['TIMESTAMP WITH TIME ZONE', 'timestamp'],
        ];
    }

    #[DataProvider('postgresTemporalTypes')]
    public function testPostgresTemporalTypesKeepTheirKind(
        string $dbType,
        string $expected
    ): void {
        $this->assertSame($expected, ModelsManager::tuneTypeForModel($dbType));
    }

    public function testMysqlTypesUnchanged(): void
    {
        $this->assertSame('timestamp', ModelsManager::tuneTypeForModel('timestamp'));
        $this->assertSame('datetime', ModelsManager::tuneTypeForModel('datetime'));
        $this->assertSame('int', ModelsManager::tuneTypeForModel('int(10) unsigned'));
        $this->assertSame('string', ModelsManager::tuneTypeForModel('varchar(255)'));
    }
}
