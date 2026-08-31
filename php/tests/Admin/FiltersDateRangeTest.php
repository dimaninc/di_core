<?php

namespace diCore\Tests\Admin;

use diCore\Database\Legacy\Mongo;
use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\TestCase;

/** Date-range defaults remain boundaries; they never become a scalar value. */
class FiltersDateRangeTest extends TestCase
{
    private const FROM = '2026-08-25';
    private const TO = '2026-08-31';

    /** @var mixed */
    private $originalDb;

    protected function setUp(): void
    {
        $this->originalDb = $this->dbProperty()->getValue();
    }

    protected function tearDown(): void
    {
        $this->dbProperty()->setValue(null, $this->originalDb);
    }

    public function testStringDefaultsProduceACompleteRange(): void
    {
        $from = \diDateTime::simpleDateFormat(self::FROM);
        $to = \diDateTime::simpleDateFormat(self::TO);

        foreach (['date_range', 'date_str_range'] as $type) {
            $value = $this->buildRange($type)->getData('created_at');

            $this->assertRange($from, $to, $value);
        }
    }

    public function testSubmittedRangeIsPreserved(): void
    {
        $value = $this
            ->buildRange('date_str_range', ['2026-08-01', '2026-08-02'])
            ->getData('created_at');

        $this->assertRange('2026-08-01', '2026-08-02', $value);
    }

    public function testUnexpectedScalarFallsBackToConfiguredBoundaries(): void
    {
        $value = $this
            ->buildRange('date_str_range', 'not-an-array')
            ->getData('created_at');

        $this->assertRange(
            \diDateTime::simpleDateFormat(self::FROM),
            \diDateTime::simpleDateFormat(self::TO),
            $value
        );
    }

    public function testMongoBsonBoundsCanRenderTheFilter(): void
    {
        $this->dbProperty()->setValue(null, new DateRangeMongoProbe());
        $html = $this->buildRange('date_str_range')->getInput('created_at');

        $this->assertStringContainsString(
            'admin_filter[created_at][1][dd]',
            $html
        );
        $this->assertStringContainsString(
            'admin_filter[created_at][2][dd]',
            $html
        );
    }

    private function buildRange(string $type, $input = null): \diAdminFilters
    {
        $reflection = new \ReflectionClass(\diAdminFilters::class);
        /** @var \diAdminFilters $filters */
        $filters = $reflection->newInstanceWithoutConstructor();
        $filters->reset = true;
        $filters->addFilter([
            'field' => 'created_at',
            'type' => $type,
            'default_value' => self::FROM,
            'default_value2' => self::TO,
            // Keep this unit test independent of a SQL connection. Mongo-backed
            // admin pages use the same callback-rule path.
            'rule' => static fn(array $props): callable => static function (
                \diCollection $collection
            ): void {
            },
        ]);

        if ($input !== null) {
            $filters->setPredefinedData('created_at', $input);
        }

        return $filters->buildQuery();
    }

    private function assertRange(string $from, string $to, array $value): void
    {
        $this->assertSame($from, $value[0]);
        $this->assertSame($to, $value[1]);
        $this->assertSame(
            \diDateTime::timestamp($from . ' 00:00:00'),
            $value['timestamp1']
        );
        $this->assertSame(
            \diDateTime::timestamp($to . ' 23:59:59'),
            $value['timestamp2']
        );
    }

    private function dbProperty(): \ReflectionProperty
    {
        return new \ReflectionProperty(\diAdminFilters::class, 'db');
    }
}

class DateRangeMongoProbe extends Mongo
{
    public function __construct()
    {
    }

    public function getAggregateValues(array $options)
    {
        return [
            'min' => new UTCDateTime(1788134400000),
            'max' => new UTCDateTime(1788220799000),
        ];
    }
}
