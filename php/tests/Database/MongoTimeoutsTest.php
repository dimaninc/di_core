<?php

namespace diCore\Tests\Database;

use diCore\Database\Legacy\Mongo;
use PHPUnit\Framework\TestCase;

/**
 * Timeout resolution for the Mongo connection.
 *
 * The library keeps the DRIVER's own defaults — retuning them for every consumer
 * would be a silent breaking change (a replica set needs server selection to wait
 * out a primary election; a managed/TLS cluster needs seconds to connect).
 * Tightening is a per-deployment decision, so it arrives through the connection
 * settings array.
 *
 * The MongoDB driver connects lazily, so constructing the class does no network
 * I/O — these assertions need no live server.
 */
class MongoTimeoutsTest extends TestCase
{
    private function make(array $settings = []): Mongo
    {
        return new TimeoutProbeMongo(
            array_merge(
                ['host' => '127.0.0.1', 'port' => 27017, 'dbname' => 'nope'],
                $settings
            )
        );
    }

    public function testDefaultsAreTheDriverDefaults(): void
    {
        $db = $this->make();

        $this->assertSame(30000, $db->getServerSelectionTimeoutMs());
        $this->assertSame(10000, $db->getConnectTimeoutMs());
        $this->assertSame(300000, $db->getSocketTimeoutMs());
    }

    public function testConstantsMatchTheDriverDefaults(): void
    {
        // Pinned: changing these silently retunes every consuming project.
        $this->assertSame(30000, Mongo::SERVER_SELECTION_TIMEOUT_MS);
        $this->assertSame(10000, Mongo::CONNECT_TIMEOUT_MS);
        $this->assertSame(300000, Mongo::SOCKET_TIMEOUT_MS);
    }

    public function testPerConnectionSettingsOverrideTheDefaults(): void
    {
        $db = $this->make([
            'serverSelectionTimeoutMS' => 7777,
            'connectTimeoutMS' => 6666,
            'socketTimeoutMS' => 4321,
        ]);

        $this->assertSame(7777, $db->getServerSelectionTimeoutMs());
        $this->assertSame(6666, $db->getConnectTimeoutMs());
        $this->assertSame(4321, $db->getSocketTimeoutMs());
    }

    public function testUnconfiguredTimeoutsAreLeftToTheDriver(): void
    {
        // Passing the constants would pin today's driver defaults forever; an
        // unset timeout must simply not appear in the client options.
        $options = $this->make()->getClientOptions();

        $this->assertArrayNotHasKey('serverSelectionTimeoutMS', $options);
        $this->assertArrayNotHasKey('connectTimeoutMS', $options);
        $this->assertArrayNotHasKey('socketTimeoutMS', $options);
    }

    public function testConfiguredTimeoutsAreHandedToTheDriver(): void
    {
        $options = $this->make(['socketTimeoutMS' => 4321])->getClientOptions();

        $this->assertSame(4321, $options['socketTimeoutMS']);
        $this->assertArrayNotHasKey('connectTimeoutMS', $options);
    }

    public function testNonNumericTimeoutIsIgnoredRatherThanCastToZero(): void
    {
        // (int) 'abc' is 0 — a real 0ms setting handed to the driver.
        $db = $this->make(['socketTimeoutMS' => 'abc']);

        $this->assertArrayNotHasKey('socketTimeoutMS', $db->getClientOptions());
        $this->assertSame(
            \diCore\Database\Legacy\Mongo::SOCKET_TIMEOUT_MS,
            $db->getSocketTimeoutMs()
        );
    }

    public function testCredentialsArePercentEncodedInTheUri(): void
    {
        // Unencoded, a password containing '@' or '/' makes the driver parse a
        // different host altogether — and lands verbatim in exception text.
        $uri = $this->make([
            'username' => 'user name',
            'password' => 'p@ss/w:rd',
        ])->uri();

        $this->assertStringContainsString('user%20name', $uri);
        $this->assertStringContainsString('p%40ss%2Fw%3Ard', $uri);
        $this->assertStringNotContainsString('p@ss', $uri);
    }

    public function testUsernameWithoutPasswordDoesNotTripTheNullDeprecation(): void
    {
        // diDB seeds password => null; rawurlencode(null) is deprecated on 8.1+.
        $uri = $this->make(['username' => 'kerberos_user'])->uri();

        $this->assertStringContainsString('kerberos_user:@', $uri);
    }

    public function testEachTimeoutFallsBackIndependently(): void
    {
        $db = $this->make(['socketTimeoutMS' => 4321]);

        $this->assertSame(4321, $db->getSocketTimeoutMs());
        $this->assertSame(
            Mongo::SERVER_SELECTION_TIMEOUT_MS,
            $db->getServerSelectionTimeoutMs(),
            'an unset timeout keeps its default'
        );
    }
}

/** Skips the real connect so no server is needed. */
class TimeoutProbeMongo extends Mongo
{
    public function uri(): string
    {
        return $this->getServerConnectionString();
    }

    protected function __connect()
    {
        return true;
    }
}
