<?php

namespace diCore\Tests\Database;

use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\TestCase;

/** Mongo `between` keeps values after the model has converted their field type. */
class MongoBetweenFilterTest extends TestCase
{
    public function testBetweenKeepsBsonDateBoundaries(): void
    {
        $filter = (new MongoBetweenCollectionProbe())
            ->filterBy('created_at', 'between', [
                '2026-08-25 00:00:00',
                '2026-08-31 23:59:59',
            ])
            ->queryWhere();

        $this->assertInstanceOf(
            UTCDateTime::class,
            $filter['created_at']['$gte']
        );
        $this->assertInstanceOf(
            UTCDateTime::class,
            $filter['created_at']['$lte']
        );
    }
}

class MongoBetweenCollectionProbe extends \diCollection
{
    public static function getConnection()
    {
        return new MongoBetweenConnectionProbe();
    }

    public static function getModelClass()
    {
        return MongoBetweenModelProbe::class;
    }

    public function queryWhere(): array
    {
        return $this->getQueryWhere();
    }
}

class MongoBetweenConnectionProbe
{
    public static function isMongo(): bool
    {
        return true;
    }
}

class MongoBetweenModelProbe
{
    public static function tuneFieldValueByTypeBeforeDb($field, $value)
    {
        if (is_array($value)) {
            return array_map(
                fn($item) => static::tuneFieldValueByTypeBeforeDb($field, $item),
                $value
            );
        }

        return new UTCDateTime((new \DateTimeImmutable($value))->getTimestamp() * 1000);
    }
}
