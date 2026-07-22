<?php

namespace diCore\Tests\Database;

use diCore\Database\Connection;
use diCore\Database\Engine;
use PHPUnit\Framework\TestCase;

/**
 * `$extraOptions` carries settings the caller cannot express otherwise (a DSN has
 * no query part). Two properties matter:
 *
 *  - $connData wins: this cannot REPLACE a value the DSN/array provided (it can
 *    still supply one that is absent — null placeholders are fillable on purpose);
 *  - a LIST of fallback connection records stays a list. Merging into the list
 *    itself turns it into one malformed record — ArrayHelper::isAssoc() then reads
 *    the mixed array as a single connection and the whole bootstrap dies. That is
 *    exactly the mistake this helper exists to prevent, so it is pinned here.
 *
 * Mongo is used because its driver connects lazily — no server needed.
 */
class ConnectionExtraOptionsTest extends TestCase
{
    private static int $seq = 0;

    private function open($connData, array $extraOptions = []): Connection
    {
        return Connection::open(
            $connData,
            Engine::MONGO,
            'extra_probe_' . ++self::$seq,
            $extraOptions
        );
    }

    private function variants(Connection $conn): array
    {
        $prop = (new \ReflectionClass(Connection::class))->getProperty(
            'dataVariants'
        );
        $prop->setAccessible(true);

        return $prop->getValue($conn);
    }

    public function testExtraOptionsReachAnAssocRecord(): void
    {
        $conn = $this->open(
            ['host' => 'localhost', 'database' => 'db'],
            ['socketTimeoutMS' => 4321]
        );

        $this->assertSame(
            4321,
            $conn->getConnData()->getOtherOptions('socketTimeoutMS')
        );
        $this->assertSame('localhost', $conn->getConnData()->getHost());
    }

    public function testConnDataWinsOverExtraOptions(): void
    {
        $conn = $this->open(
            ['host' => 'real.host', 'database' => 'db'],
            ['host' => 'evil.host', 'socketTimeoutMS' => 4321]
        );

        $this->assertSame(
            'real.host',
            $conn->getConnData()->getHost(),
            'a host that was actually provided is never replaced'
        );
    }

    public function testAListOfFallbackVariantsStaysAList(): void
    {
        $conn = $this->open(
            [
                ['host' => 'h1', 'port' => 1111, 'database' => 'db'],
                ['host' => 'h2', 'port' => 2222, 'database' => 'db'],
            ],
            ['socketTimeoutMS' => 4321]
        );

        $this->assertCount(
            2,
            $this->variants($conn),
            'merging into the list would collapse it into one malformed record'
        );
    }

    public function testEveryVariantGetsTheExtraOptions(): void
    {
        $conn = $this->open(
            [
                ['host' => 'h1', 'port' => 1111, 'database' => 'db'],
                ['host' => 'h2', 'port' => 2222, 'database' => 'db'],
            ],
            ['socketTimeoutMS' => 4321]
        );

        foreach ($this->variants($conn) as $variant) {
            $this->assertSame(4321, $variant->getOtherOptions('socketTimeoutMS'));
        }
    }

    public function testEmptyConnDataStillReceivesExtraOptions(): void
    {
        // isAssoc([]) is false, so a naive "it's a list" branch would map over
        // nothing and drop $extraOptions on the floor.
        $conn = $this->open(
            [],
            ['host' => 'localhost', 'database' => 'db', 'socketTimeoutMS' => 4321]
        );

        $this->assertSame('localhost', $conn->getConnData()->getHost());
        $this->assertSame(
            4321,
            $conn->getConnData()->getOtherOptions('socketTimeoutMS')
        );
    }

    public function testDsnGetsExtraOptionsAndKeepsItsParsedValues(): void
    {
        $conn = Connection::openByDsn(
            'mongodb://localhost:27017/parsed_db',
            'extra_probe_dsn_' . ++self::$seq,
            ['host' => 'evil.host', 'socketTimeoutMS' => 4321]
        );

        $this->assertSame('localhost', $conn->getConnData()->getHost());
        $this->assertSame('parsed_db', $conn->getConnData()->getDatabase());
        $this->assertSame(
            4321,
            $conn->getConnData()->getOtherOptions('socketTimeoutMS')
        );
    }

    public function testNullPlaceholdersDoNotShadowExtraOptions(): void
    {
        // A DSN with no path leaves `database` null; the escape hatch must still
        // be able to supply it.
        $conn = Connection::openByDsn(
            'mongodb://localhost:27017',
            'extra_probe_nodb_' . ++self::$seq,
            ['database' => 'from_extra']
        );

        $this->assertSame('from_extra', $conn->getConnData()->getDatabase());
    }

    public function testEncodedDsnCredentialsAreNotDoubleEncoded(): void
    {
        // parse_url() does not decode, and the URI builder re-encodes — keeping
        // the raw percent-escapes would turn p%40ss into p%2540ss and break auth.
        $conn = Connection::openByDsn(
            'mongodb://user:p%40ss@localhost:27017/db',
            'extra_probe_enc_' . ++self::$seq
        );

        $this->assertSame('p@ss', $conn->getConnData()->getPassword());
    }

    public function testExtraOptionsTravelAllTheWayToTheDriverOptions(): void
    {
        // The whole point of the plumbing: MongoConnection must hand
        // ConnectionData's other options to Mongo::getClientOptions().
        $conn = $this->open(
            ['host' => 'localhost', 'database' => 'db'],
            ['socketTimeoutMS' => 4321]
        );

        $options = $conn->getDb()->getClientOptions();

        $this->assertSame(4321, $options['socketTimeoutMS']);
    }
}
