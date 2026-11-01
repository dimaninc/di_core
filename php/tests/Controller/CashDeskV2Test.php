<?php

namespace diCore\Tests\Controller;

use diCore\Base\Exception\HttpException;
use diCore\Controller\Cash;
use diCore\Database\Connection;
use diCore\Entity\PaymentReceipt\Model;
use diCore\Tests\Entity\AuthorizationPinTestDb;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Entity/AuthorizationPinTestDb.php';

class CashDeskTestReceipt extends Model
{
    /** @var array<int, array{email?: string, phone?: string}> by receipt id */
    public static $contacts = [];
    /** @var array<int, array> by receipt id: items to serve instead of the default */
    public static $items = [];
    /** @var \diModel|null */
    public $fakeUser;
    /** @var int|null */
    public static $failingId;

    public function getUser()
    {
        return $this->fakeUser ?: new \diModel();
    }

    protected function cashDeskBuyerEmail(): string
    {
        return $this->fakeUser
            ? parent::cashDeskBuyerEmail()
            : (string) (self::$contacts[$this->getId()]['email'] ?? '');
    }

    protected function cashDeskBuyerPhone(): string
    {
        return $this->fakeUser
            ? parent::cashDeskBuyerPhone()
            : (string) (self::$contacts[$this->getId()]['phone'] ?? '');
    }

    protected function cashDeskItemName(): string
    {
        return 'Музыкальная открытка';
    }

    protected function cashDeskItems(int $total): array
    {
        return self::$items[$this->getId()] ?? parent::cashDeskItems($total);
    }
}

/**
 * Throwaway tables instead of the project's, the request body and headers as
 * properties, every hook recorded instead of reported.
 */
class TestableCash extends Cash
{
    const RECEIPTS = '_di_core_test_cash_receipts';
    const HEARTBEATS = '_di_core_test_cash_heartbeat';
    const JOURNAL = '_di_core_test_cash_journal';

    public static $useFiscalMark = false;
    /** @var string a throwaway folder: the default marker lives in the consumer's cache */
    public static $markerFolder = '';

    public $token = 'secret-token';
    public $body = '';
    public $authorization = 'Bearer secret-token';
    /** @var int|null declared Content-Length */
    public $length;
    public $calls = [];
    /** @var string|null a document another request writes between our read and our UPDATE */
    public $competingDocumentNumber;

    public function __construct()
    {
        // No parent constructor: it starts a session.
    }

    protected static function receiptsTable(): string
    {
        return self::RECEIPTS;
    }

    protected static function heartbeatTable(): string
    {
        return self::HEARTBEATS;
    }

    protected static function journalTable(): string
    {
        return self::JOURNAL;
    }

    protected static function useFiscalMarkFromCashDesk()
    {
        return self::$useFiscalMark;
    }

    protected function cashDeskToken(): string
    {
        return $this->token;
    }

    protected function cashDeskRequestBody(): string
    {
        return $this->body;
    }

    protected function cashDeskAuthorizationHeader(): string
    {
        return $this->authorization;
    }

    protected function cashDeskRequestLength(): ?int
    {
        return $this->length;
    }

    protected static function brokenReceiptMarkerFolder(): string
    {
        return self::$markerFolder;
    }

    protected function cashDeskPaymentsPlace(): string
    {
        return 'https://shop.example';
    }

    protected function createCashDeskReceipt(array $row): Model
    {
        return new CashDeskTestReceipt($row);
    }

    protected function preloadCashDeskRelations(array $receipts): array
    {
        return [];
    }

    /** @var string[] what the default code would have logged */
    public static $logged = [];

    protected static function logCashDesk(string $text): void
    {
        self::$logged[] = $text;
    }

    protected function onUnexpectedReceiptReport(
        int $receiptId,
        string $reason,
        array $fiscal
    ): void {
        $this->calls[] = ['unexpected', $receiptId, $reason];
    }

    protected function onJournalStoreFailure(string $journalId, \Throwable $e): void
    {
        $this->calls[] = ['journal_store', $journalId, get_class($e)];
    }

    protected function writeFiscalData(int $receiptId, array $fiscal): bool
    {
        if ($this->competingDocumentNumber !== null) {
            static::cashDeskDb()->q(
                'UPDATE ' .
                    self::RECEIPTS .
                    " SET fiscal_doc_id = '{$this->competingDocumentNumber}', date_uploaded = '" .
                    \diDateTime::sqlFormat() .
                    "' WHERE id = $receiptId"
            );
        }

        return parent::writeFiscalData($receiptId, $fiscal);
    }

    protected function onDoubleFiscalization(
        int $receiptId,
        string $storedDocumentNumber,
        array $fiscal
    ): void {
        $this->calls[] = [
            'double',
            $receiptId,
            $storedDocumentNumber,
            $fiscal['documentNumber'],
        ];
    }

    protected function onFiscalTotalMismatch(
        int $receiptId,
        int $expectedKopecks,
        int $fiscalKopecks,
        array $fiscal
    ): void {
        $this->calls[] = ['mismatch', $receiptId, $expectedKopecks, $fiscalKopecks];
    }

    protected function onBrokenReceipt(Model $receipt, string $problem): void
    {
        $this->calls[] = ['broken', (int) $receipt->getId(), $problem];
    }

    protected function onJournalConflict(
        string $journalId,
        int $seq,
        array $stored,
        array $incoming
    ): void {
        $this->calls[] = ['journal', $journalId, $seq];
    }

    protected function reportCashDeskProblem(string $text): void
    {
        $this->calls[] = ['report', $text];
    }

    /**
     * v1's real filter over the throwaway table: the collection is bound to the
     * model's table, so its WHERE is run here by hand.
     *
     * @return int[]
     */
    public function v1ReceiptIds(): array
    {
        $db = static::cashDeskDb();
        $rs = $db->q(
            'SELECT id FROM ' .
                self::RECEIPTS .
                ' main_table ' .
                $this->getReceipts()->getFullQuery()
        );
        $ids = [];

        while ($row = $db->fetch_array($rs)) {
            $ids[] = (int) $row['id'];
        }

        sort($ids);

        return $ids;
    }

    public function call(string $action, ?array $body = null): array
    {
        $this->body = $body === null ? '' : json_encode($body);
        $method = '_post' . camelize($action . '_action');

        return $this->$method();
    }
}

class TempFolderCash extends Cash
{
    public static function reportFolder(string $cacheFolder, string $temp): string
    {
        return static::reportMarkerFolderIn($cacheFolder, $temp);
    }
}

class V1Cash extends Cash
{
    public static $expected;
    public static $given;

    public function __construct()
    {
    }

    public static function secret()
    {
        return self::$expected;
    }

    public static function getSecret()
    {
        return self::$given;
    }

    public function check()
    {
        return $this->checkSecret();
    }
}

/** The default reporter, untouched: only the tables and the request are replaced. */
class ReportingCash extends TestableCash
{
    protected function onBrokenReceipt(Model $receipt, string $problem): void
    {
        Cash::onBrokenReceipt($receipt, $problem);
    }

    protected function reportCashDeskProblem(string $text): void
    {
        Cash::reportCashDeskProblem($text);
    }
}

class CashDeskV2Test extends TestCase
{
    const FISCAL = [
        'documentNumber' => 71,
        'sign' => '1494325660',
        'dateTime' => '2026-10-31T12:00:05+03:00',
        'shiftNumber' => 12,
        'receiptNumber' => 1,
        'fnNumber' => '9961440300243452',
        'registrationNumber' => '0000000001002292',
        'total' => 29000,
    ];
    const JOURNAL_ID = '5f0c6a2e-8d1b-4c3a-9e7f-0a1b2c3d4e5f';
    const NEW_JOURNAL_ID = 'a3e1b9c0-4f2d-4e8a-b6c7-d8e9f0a1b2c3';

    /** @var \diDB */
    private static $db;

    public static function setUpBeforeClass(): void
    {
        // A live server from DI_CORE_TEST_DB (Postgres or MySQL) when given, else the consumer's own
        AuthorizationPinTestDb::open();
        self::$db = Connection::get()->getDb();
        $folder =
            __DIR__ .
            '/../../../sql/' .
            (Connection::get()::isPostgres() ? 'postgres/' : '');

        $tables = [
            'payment_receipts' => TestableCash::RECEIPTS,
            Cash::HEARTBEAT_TABLE => TestableCash::HEARTBEATS,
            Cash::JOURNAL_TABLE => TestableCash::JOURNAL,
        ];

        foreach ($tables as $dump => $table) {
            self::$db->q("DROP TABLE IF EXISTS $table");
            $sql = str_replace($dump, $table, file_get_contents("$folder$dump.sql"));

            foreach (explode(';', $sql) as $statement) {
                if (trim($statement)) {
                    self::assertNotFalse(self::$db->q($statement), $statement);
                }
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (
            [TestableCash::RECEIPTS, TestableCash::HEARTBEATS, TestableCash::JOURNAL]
            as $table
        ) {
            self::$db->q("DROP TABLE IF EXISTS $table");
        }

        AuthorizationPinTestDb::close();
    }

    protected function setUp(): void
    {
        foreach (
            [TestableCash::RECEIPTS, TestableCash::HEARTBEATS, TestableCash::JOURNAL]
            as $table
        ) {
            self::$db->q("DELETE FROM $table");
        }

        CashDeskTestReceipt::$contacts = [];
        CashDeskTestReceipt::$items = [];
        CashDeskTestReceipt::$failingId = null;
        TestableCash::$logged = [];
        TestableCash::$useFiscalMark = false;
        TestableCash::$markerFolder =
            sys_get_temp_dir() . '/di-core-cashdesk-' . getmypid() . '/';
    }

    protected function tearDown(): void
    {
        foreach (glob(TestableCash::$markerFolder . '*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir(TestableCash::$markerFolder)) {
            rmdir(TestableCash::$markerFolder);
        }
    }

    /**
     * The router tries `_post<Action>` first, then `<action>Action` for any method:
     * a second name would open the action on GET.
     */
    public function testV2ActionsAnswerOnlyToPost(): void
    {
        foreach (['PendingReceipts', 'ReceiptFiscalized', 'Heartbeat'] as $name) {
            $this->assertTrue(method_exists(Cash::class, "_post{$name}Action"));
            $this->assertFalse(
                method_exists(Cash::class, lcfirst($name) . 'Action')
            );
        }
    }

    /**
     * Through the real dispatcher (`act()`) of a built-in web server: under CLI it
     * picks `_cli…` methods, so only a real request shows what GET reaches.
     */
    public function testGetIsRefusedByTheRealDispatcher(): void
    {
        $core = realpath(__DIR__ . '/../../..');
        $router = TestableCash::$markerFolder . 'router.php';
        @mkdir(TestableCash::$markerFolder, 0775, true);
        file_put_contents(
            $router,
            '<?php
foreach (' .
                var_export(
                    [
                        dirname($core, 2) . '/autoload.php',
                        "$core/vendor/autoload.php",
                    ],
                    true
                ) .
                ' as $a) {
    if (is_file($a)) { require_once $a; break; }
}
require_once ' .
                var_export("$core/php/lib/diLib.php", true) .
                ';
require_once ' .
                var_export("$core/php/functions.php", true) .
                ';
$c = new class extends \\diCore\\Controller\\Cash {
    public function __construct() {}
    protected function cashDeskToken(): string { return "secret-token"; }
};
try {
    $c->act("pending_receipts");
    $code = 200;
} catch (\\diCore\\Base\\Exception\\HttpException $e) {
    $code = $e->getCode();
}
echo "code=$code";
'
        );

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(
            strrchr(stream_socket_get_name($socket, false), ':'),
            1
        );
        fclose($socket);
        $server = proc_open(
            [
                PHP_BINARY,
                '-d',
                'display_errors=stderr',
                '-d',
                'error_reporting=-1',
                '-d',
                'html_errors=0',
                '-S',
                "127.0.0.1:$port",
                $router,
            ],
            [
                1 => ['file', '/dev/null', 'w'],
                2 => ['file', TestableCash::$markerFolder . 'server.log', 'w'],
            ],
            $pipes
        );

        try {
            $request = function (string $method, string $auth) use ($port): string {
                for ($i = 0; $i < 50; $i++) {
                    $answer = @file_get_contents(
                        "http://127.0.0.1:$port/",
                        false,
                        stream_context_create([
                            'http' => [
                                'method' => $method,
                                'header' => $auth,
                                'ignore_errors' => true,
                            ],
                        ])
                    );

                    if ($answer !== false) {
                        return $answer;
                    }

                    usleep(100000);
                }

                return 'no answer';
            };

            // the right token over GET finds no action
            $this->assertSame(
                'code=404',
                $request('GET', 'Authorization: Bearer secret-token')
            );
            // POST reaches the action: refused by the token check, not by routing
            $this->assertSame('code=401', $request('POST', ''));
        } finally {
            proc_terminate($server);
            proc_close($server);
            unlink($router);
        }
    }

    /** v1 is fail closed too: no secret configured refuses everyone. */
    public function testV1WithoutSecretRefusesEveryone(): void
    {
        $cases = [
            'null expected, null given' => [null, null],
            'empty expected, empty given' => ['', ''],
            'array given' => ['s3cret', ['s3cret']],
            'wrong given' => ['s3cret', 's3cre'],
        ];

        foreach ($cases as $case => [$expected, $given]) {
            V1Cash::$expected = $expected;
            V1Cash::$given = $given;

            $this->assertSame(
                403,
                $this->httpError(function () {
                    (new V1Cash())->check();
                })->getCode(),
                $case
            );
        }

        V1Cash::$expected = V1Cash::$given = 's3cret';
        $c = new V1Cash();
        $this->assertSame($c, $c->check());
    }

    /* --- authorization --------------------------------------------------------- */

    public static function refusedAuthorizations(): array
    {
        return [
            'no header' => ['secret-token', ''],
            'wrong token' => ['secret-token', 'Bearer other-token'],
            'prefix of the token' => ['secret-token', 'Bearer secret'],
            'other scheme' => ['secret-token', 'Basic secret-token'],
            'bare token' => ['secret-token', 'secret-token'],
            'empty expected, empty given' => ['', 'Bearer '],
            'empty expected, any given' => ['', 'Bearer anything'],
            'expected with a space inside' => ['secret token', 'Bearer secret'],
        ];
    }

    /**
     * @dataProvider refusedAuthorizations
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refusedAuthorizations')]
    public function testEveryActionRefusesWithoutTheRightToken(
        string $expected,
        string $header
    ): void {
        $this->addReceipt(['amount' => 290]);

        foreach (
            ['pending_receipts', 'receipt_fiscalized', 'heartbeat']
            as $action
        ) {
            $c = new TestableCash();
            $c->token = $expected;
            $c->authorization = $header;

            $e = $this->httpError(function () use ($c, $action) {
                // A malformed body must not get a 400 past the missing token
                $c->body = '{broken';
                $c->{'_post' . camelize($action . '_action')}();
            });

            $this->assertSame(401, $e->getCode(), "$action, $header");
            $this->assertSame(['error' => 'unauthorized'], $e->getBody());
        }

        $this->assertSame(0, $this->rowCount(TestableCash::HEARTBEATS));
        $this->assertSame(
            1,
            $this->rowCount(TestableCash::RECEIPTS, 'date_uploaded IS NULL')
        );
    }

    public function testEmptyExpectedTokenIsReported(): void
    {
        $c = new TestableCash();
        $c->token = '';

        $this->httpError(function () use ($c) {
            $c->call('pending_receipts');
        });

        $this->assertSame('report', $c->calls[0][0]);
        $this->assertStringContainsString('CASHDESK_TOKEN', $c->calls[0][1]);
    }

    /** A token with a space can never match `Bearer \\S+`: refused and reported like an empty one. */
    public function testTokenWithWhitespaceIsReportedAsMisconfigured(): void
    {
        $c = new TestableCash();
        $c->token = "secret\ttoken";

        $this->assertSame(
            401,
            $this->httpError(function () use ($c) {
                $c->call('pending_receipts');
            })->getCode()
        );
        $this->assertSame('report', $c->calls[0][0] ?? null);
        $this->assertStringContainsString('whitespace', $c->calls[0][1]);
    }

    public function testRightTokenPassesAndSchemeIsCaseInsensitive(): void
    {
        $c = new TestableCash();
        $c->authorization = 'bearer secret-token';

        $this->assertSame(['receipts' => []], $c->call('pending_receipts'));
    }

    public function testMalformedBodyIsBadRequest(): void
    {
        $c = new TestableCash();
        $c->body = '[1, {broken';

        $this->assertSame(
            400,
            $this->httpError(function () use ($c) {
                $c->_postPendingReceiptsAction();
            })->getCode()
        );
    }

    /**
     * A JSON list decodes to an array too, and every field read from it is then
     * "missing" instead of the request being refused for its shape.
     */
    public function testJsonListIsNotAnObject(): void
    {
        foreach (['[1, 2]', '[]', '[{"limit": 1}]'] as $body) {
            $c = new TestableCash();
            $c->body = $body;

            $e = $this->httpError(function () use ($c) {
                $c->_postPendingReceiptsAction();
            });

            $this->assertSame(400, $e->getCode(), $body);
            $this->assertSame(
                'body must be a JSON object',
                $e->getBody()['message']
            );
        }
    }

    public function testOversizedBodyIsRefusedBeforeParsing(): void
    {
        $c = new TestableCash();
        $c->length = Cash::BODY_MAX_BYTES + 1;
        $c->body = '{}';

        $this->assertSame(
            413,
            $this->httpError(function () use ($c) {
                $c->_postHeartbeatAction();
            })->getCode(),
            'declared length'
        );

        // no Content-Length (chunked) or a false one: the body itself is measured
        $c = new TestableCash();
        $c->body =
            '{"journalId": "' .
            self::JOURNAL_ID .
            '", "pad": "' .
            str_repeat('x', Cash::BODY_MAX_BYTES) .
            '"}';
        $c->length = 10;

        $this->assertSame(
            413,
            $this->httpError(function () use ($c) {
                $c->_postHeartbeatAction();
            })->getCode(),
            'actual length'
        );
        $this->assertSame(0, $this->rowCount(TestableCash::HEARTBEATS));
    }

    /* --- kopecks --------------------------------------------------------------- */

    public static function amounts(): array
    {
        return [
            [0.01, 1],
            [0.29, 29], // 0.29 * 100 = 28.999…
            [0.57, 57], // 56.999…
            [1.15, 115], // 114.999…
            [19.99, 1999],
            [69.5, 6950],
            [290, 29000],
            ['290', 29000],
            ['149.9', 14990],
            // what a MySQL FLOAT holding 149.9 reads back as with native types
            [149.89999389648438, 14990],
            [10000.01, 1000001],
            [0, 0],
            [null, 0],
            ['', 0],
        ];
    }

    /**
     * @dataProvider amounts
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('amounts')]
    public function testAmountBecomesWholeKopecks($amount, int $kopecks): void
    {
        $this->assertSame($kopecks, Model::toKopecks($amount));
    }

    /* --- pending_receipts -------------------------------------------------------- */

    public function testPendingReceiptFormat(): void
    {
        $paidAt = \diDateTime::sqlFormat('-1 hour');
        $id = $this->addReceipt(['amount' => 290, 'date_payed' => $paidAt]);
        CashDeskTestReceipt::$contacts[$id] = ['email' => 'buyer@example.com'];

        $c = new TestableCash();
        $answer = $c->call('pending_receipts', ['limit' => 10]);

        $this->assertSame(
            [
                'receipts' => [
                    [
                        'id' => $id,
                        'type' => 'sell',
                        'originalId' => null,
                        'paidAt' => (new \DateTime($paidAt))->format(DATE_ATOM),
                        'total' => 29000,
                        'contact' => 'buyer@example.com',
                        'paymentsPlace' => 'https://shop.example',
                        'items' => [
                            [
                                'name' => 'Музыкальная открытка',
                                'price' => 29000,
                                'quantity' => 1,
                                'sum' => 29000,
                                'paymentObject' => 'service',
                                'paymentMethod' => 'fullPayment',
                            ],
                        ],
                        'payments' => [['type' => 'electronically', 'sum' => 29000]],
                    ],
                ],
            ],
            $answer
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $answer['receipts'][0]['paidAt']
        );
        $this->assertSame([], $c->calls);
    }

    public function testContactIsEmailThenPhoneThenNull(): void
    {
        $email = $this->addReceipt(['amount' => 10]);
        $phone = $this->addReceipt(['amount' => 10]);
        $none = $this->addReceipt(['amount' => 10]);
        $shortPhone = $this->addReceipt(['amount' => 10]);
        CashDeskTestReceipt::$contacts = [
            $email => [
                'email' => ' buyer@example.com ',
                'phone' => '+7 916 000-00-00',
            ],
            $phone => ['email' => 'not-an-email', 'phone' => '8 (916) 123-45-67'],
            $none => ['email' => '', 'phone' => ''],
            $shortPhone => ['phone' => '123-45'],
        ];

        $contacts = array_column(
            (new TestableCash())->call('pending_receipts')['receipts'],
            'contact',
            'id'
        );

        $this->assertSame(
            [
                $email => 'buyer@example.com',
                $phone => '+79161234567',
                $none => null,
                $shortPhone => null,
            ],
            $contacts
        );
    }

    /** Tag 1008 holds 64 characters: a longer address goes to the phone, else none. */
    public function testContactLongerThanTheTagIsNotUsed(): void
    {
        $email = str_repeat('a', 53) . '@example.com';
        $this->assertSame(65, strlen($email));
        $withPhone = $this->addReceipt(['amount' => 10]);
        $withoutPhone = $this->addReceipt(['amount' => 10]);
        $fits = $this->addReceipt(['amount' => 10]);
        CashDeskTestReceipt::$contacts = [
            $withPhone => ['email' => $email, 'phone' => '8 916 123-45-67'],
            $withoutPhone => ['email' => $email, 'phone' => ''],
            $fits => ['email' => substr($email, 1), 'phone' => '8 916 123-45-67'],
        ];

        $this->assertSame(
            [
                $withPhone => '+79161234567',
                $withoutPhone => null,
                $fits => substr($email, 1),
            ],
            array_column(
                (new TestableCash())->call('pending_receipts')['receipts'],
                'contact',
                'id'
            )
        );
    }

    public static function phones(): array
    {
        return [
            'leading 8' => ['8 (916) 123-45-67', '+79161234567'],
            'ten digits on 9' => ['9161234567', '+79161234567'],
            'leading 7' => ['79161234567', '+79161234567'],
            '+7' => ['+7 (495) 123-45-67', '+74951234567'],
            '00 prefix' => ['0079161234567', '+79161234567'],
            'explicit foreign' => ['+44 20 7946 0958', '+442079460958'],
            'foreign via 00' => ['00380 44 123 4567', '+380441234567'],
            // 11 digits on 8 and 10 on 9 look Russian to Phone::clean()
            'Japan, 11 digits on 8' => ['+81 3 1234 5678', '+81312345678'],
            'Lebanon, 10 digits on 9' => ['+961 1 234 567', '+9611234567'],
            'Japan via 00' => ['0081 3 1234 5678', '+81312345678'],
            // no country code starts with 89: a Russian 8 with a stray plus
            '+8 and a Russian mobile' => ['+8 999 123-45-67', '+79991234567'],
            '008 and a Russian mobile' => ['008 916 123 45 67', '+79161234567'],
            'Vietnam is not touched' => ['+84 95 123 4567', '+84951234567'],
            // a toll-free number is the shop's, never the buyer's
            '8-800' => ['8 800 555-35-35', ''],
            '+7 800' => ['+7 800 555 35 35', ''],
            '+ eight digits' => ['+372 5123 456', '+3725123456'],
            '+ sixteen digits' => ['+1 234 567 890 123 456', ''],
            '+ leading zero' => ['+0 916 123 45 67', ''],
            '+ with extension' => ['+7 916 123-45-67 доб. 12', ''],
            'ten digits not on 9' => ['4951234567', ''],
            'foreign without +' => ['442079460958', ''],
            'extension' => ['8 916 123-45-67 доб. 12', ''],
            'too short' => ['123-45', ''],
            '+ too short' => ['+1 234 567', ''],
            'empty' => ['', ''],
        ];
    }

    /**
     * A wrong number sends the receipt to a stranger: ambiguous means no phone.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('phones')]
    public function testPhoneBecomesE164OrNothing(
        string $raw,
        string $expected
    ): void {
        $receipt = new class (['id' => 5, 'amount' => 10]) extends
            CashDeskTestReceipt
        {
            public function normalize(string $phone): string
            {
                return $this->normalizeCashDeskPhone($phone);
            }
        };

        $this->assertSame($expected, $receipt->normalize($raw));
    }

    /**
     * A project may pad getUserData() with its own fallback address; the contact
     * must not see it, or a buyer's phone would never be used.
     */
    public function testDefaultContactReadsTheUserNotGetUserData(): void
    {
        $receipt = new class (['id' => 5, 'amount' => 10]) extends
            CashDeskTestReceipt
        {
            public function getUserData()
            {
                return ['email' => 'shop@example.com', 'phone' => ''];
            }
        };
        $receipt->fakeUser = new \diModel([
            'email' => '',
            'phone' => '+7 916 123 45 67',
        ]);

        $this->assertSame('+79161234567', $receipt->cashDeskContact());

        $receipt->fakeUser = new \diModel([
            'email' => 'user@example.com',
            'phone' => '+79161234567',
        ]);

        $this->assertSame('user@example.com', $receipt->cashDeskContact());
    }

    public function testQueueIsUnreportedOldestFirstWithinTheWindow(): void
    {
        $newer = $this->addReceipt([
            'date_payed' => \diDateTime::sqlFormat('-1 hour'),
        ]);
        $older = $this->addReceipt([
            'date_payed' => \diDateTime::sqlFormat('-2 hours'),
        ]);
        // reported
        $this->addReceipt([
            'date_payed' => \diDateTime::sqlFormat('-3 hours'),
            'date_uploaded' => \diDateTime::sqlFormat('-2 hours'),
        ]);
        // has a document but no report: serving it again would print it twice
        $this->addReceipt([
            'date_payed' => \diDateTime::sqlFormat('-3 hours'),
            'fiscal_doc_id' => '70',
        ]);
        // years-old unreported rows exist in real tables: never served
        $this->addReceipt(['date_payed' => \diDateTime::sqlFormat('-2 months')]);

        $this->assertSame(
            [$older, $newer],
            array_column(
                (new TestableCash())->call('pending_receipts')['receipts'],
                'id'
            )
        );
        $this->assertSame(
            [$older],
            array_column(
                (new TestableCash())->call('pending_receipts', ['limit' => 1])[
                    'receipts'
                ],
                'id'
            )
        );
    }

    /**
     * Quarantined receipts are skipped by the server, so they don't take the batch
     * the cash desk asked for: the ones after them are served within the limit.
     */
    public function testExcludedReceiptsAreSkippedAndTheNextOnesServed(): void
    {
        $first = $this->addReceipt([
            'date_payed' => \diDateTime::sqlFormat('-4 hours'),
        ]);
        $second = $this->addReceipt([
            'date_payed' => \diDateTime::sqlFormat('-3 hours'),
        ]);
        $third = $this->addReceipt([
            'date_payed' => \diDateTime::sqlFormat('-2 hours'),
        ]);
        $fourth = $this->addReceipt([
            'date_payed' => \diDateTime::sqlFormat('-1 hour'),
        ]);
        $ids = function (array $body) {
            return array_column(
                (new TestableCash())->call('pending_receipts', $body)['receipts'],
                'id'
            );
        };

        $this->assertSame(
            [$second, $fourth],
            $ids(['limit' => 2, 'exclude' => [$first, $third]])
        );
        $this->assertSame(
            [$third],
            $ids(['limit' => 1, 'exclude' => [$first, (string) $second, $first]])
        );
        $this->assertSame([$first, $second], $ids(['limit' => 2, 'exclude' => []]));
    }

    public static function malformedExcludes(): array
    {
        return [
            'not a list' => [['a' => 1]],
            'a number' => [5],
            'a string' => ['1,2'],
            'zero' => [[0]],
            'negative' => [[-3]],
            'a float' => [[1.5]],
            'a numeric junk' => [['12abc']],
            'a nested list' => [[[1]]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformedExcludes')]
    public function testMalformedExcludeIsBadRequest($exclude): void
    {
        $this->addReceipt([]);
        $c = new TestableCash();

        $e = $this->httpError(function () use ($c, $exclude) {
            $c->call('pending_receipts', ['exclude' => $exclude]);
        });

        $this->assertSame(400, $e->getCode());
        $this->assertSame([], $c->calls);
    }

    /** The client stops a source itself past the cap: a longer list is its failure. */
    public function testExcludeOverTheCapIsBadRequestAndReported(): void
    {
        $c = new TestableCash();

        $e = $this->httpError(function () use ($c) {
            $c->call('pending_receipts', [
                'exclude' => range(1, Cash::PENDING_EXCLUDE_MAX + 1),
            ]);
        });

        $this->assertSame(400, $e->getCode());
        $this->assertSame('report', $c->calls[0][0] ?? null);
        $this->assertStringContainsString('exclude', $c->calls[0][1]);

        // every poll sends it again: the report is hourly
        $this->httpError(function () use ($c) {
            $c->call('pending_receipts', [
                'exclude' => range(1, Cash::PENDING_EXCLUDE_MAX + 1),
            ]);
        });
        $this->assertCount(1, $c->calls);
    }

    public function testExcludeAtTheCapIsAccepted(): void
    {
        $id = $this->addReceipt([]);

        $this->assertSame(
            [$id],
            array_column(
                (new TestableCash())->call('pending_receipts', [
                    'exclude' => range($id + 1, $id + Cash::PENDING_EXCLUDE_MAX),
                ])['receipts'],
                'id'
            )
        );
    }

    /**
     * A rollback to the old app: what v2 fiscalized but didn't mark as reported
     * must not be served by v1 either, or the old cash desk prints it again.
     */
    public function testV1ServesWhatV2Serves(): void
    {
        $pending = $this->addReceipt([]);
        $this->addReceipt(['fiscal_doc_id' => '70']);
        $this->addReceipt(['date_uploaded' => \diDateTime::sqlFormat()]);
        $this->addReceipt(['date_payed' => \diDateTime::sqlFormat('-2 months')]);

        $this->assertSame([$pending], (new TestableCash())->v1ReceiptIds());
        $this->assertSame(
            [$pending],
            array_column(
                (new TestableCash())->call('pending_receipts')['receipts'],
                'id'
            )
        );
    }

    public function testLimitDefaultsAndIsCapped(): void
    {
        for ($i = 0; $i < Cash::PENDING_LIMIT_MAX + 5; $i++) {
            $this->addReceipt([]);
        }

        $c = new TestableCash();

        $this->assertCount(
            Cash::PENDING_LIMIT_DEFAULT,
            $c->call('pending_receipts')['receipts']
        );
        $this->assertCount(
            Cash::PENDING_LIMIT_MAX,
            $c->call('pending_receipts', ['limit' => 1000])['receipts']
        );

        foreach ([0, -1, 1.5, 'ten', true] as $limit) {
            $this->assertSame(
                400,
                $this->httpError(function () use ($c, $limit) {
                    $c->call('pending_receipts', ['limit' => $limit]);
                })->getCode(),
                json_encode($limit)
            );
        }
    }

    public function testBrokenReceiptsAreSkippedReportedAndDoNotStallTheQueue(): void
    {
        $free = $this->addReceipt([
            'amount' => 0,
            'date_payed' => \diDateTime::sqlFormat('-5 hours'),
        ]);
        $mismatched = $this->addReceipt([
            'amount' => 290,
            'date_payed' => \diDateTime::sqlFormat('-4 hours'),
        ]);
        $good = [
            $this->addReceipt([
                'amount' => 100,
                'date_payed' => \diDateTime::sqlFormat('-3 hours'),
            ]),
            $this->addReceipt([
                'amount' => 100,
                'date_payed' => \diDateTime::sqlFormat('-2 hours'),
            ]),
        ];
        CashDeskTestReceipt::$items[$mismatched] = [
            [
                'name' => 'x',
                'price' => 28000,
                'quantity' => 1,
                'sum' => 28000,
                'paymentObject' => 'service',
                'paymentMethod' => 'fullPayment',
            ],
        ];

        $c = new TestableCash();

        $this->assertSame(
            [$good[0], $good[1]],
            array_column(
                $c->call('pending_receipts', ['limit' => 2])['receipts'],
                'id'
            )
        );
        $this->assertSame(
            [
                ['broken', $free, 'total is not a positive number of kopecks'],
                ['broken', $mismatched, 'items sum 28000 != total 29000'],
            ],
            $c->calls
        );
    }

    /**
     * More broken receipts at the head than one page: the queue is read on by
     * keyset, otherwise the good ones behind them are never served.
     */
    public function testLongRunOfBrokenReceiptsDoesNotStallTheQueue(): void
    {
        $broken = Cash::PENDING_LIMIT_DEFAULT + Cash::PENDING_SCAN_EXTRA + 15;
        $this->addReceipts($broken, [
            'amount' => 0,
            'date_payed' => \diDateTime::sqlFormat('-5 hours'),
        ]);
        $good = [
            $this->addReceipt(['date_payed' => \diDateTime::sqlFormat('-2 hours')]),
            $this->addReceipt(['date_payed' => \diDateTime::sqlFormat('-1 hours')]),
        ];

        $c = new TestableCash();

        $this->assertSame(
            $good,
            array_column($c->call('pending_receipts')['receipts'], 'id')
        );
        $this->assertCount($broken, $c->calls);
    }

    /** A page boundary inside one date_payed must neither skip nor repeat a row. */
    public function testKeysetPagingWithinOneTimestamp(): void
    {
        $at = \diDateTime::sqlFormat('-3 hours');
        $this->addReceipts(
            Cash::PENDING_LIMIT_DEFAULT + Cash::PENDING_SCAN_EXTRA + 3,
            [
                'amount' => 0,
                'date_payed' => $at,
            ]
        );
        $good = $this->addReceipt(['date_payed' => $at]);

        $c = new TestableCash();

        $this->assertSame(
            [$good],
            array_column($c->call('pending_receipts')['receipts'], 'id')
        );
        $ids = array_column($c->calls, 1);
        $this->assertSame(count($ids), count(array_unique($ids)));
    }

    /** One poll reads at most PENDING_SCAN_MAX rows, however long the broken run. */
    public function testScanIsCapped(): void
    {
        $this->addReceipts(Cash::PENDING_SCAN_MAX + 5, [
            'amount' => 0,
            'date_payed' => \diDateTime::sqlFormat('-5 hours'),
        ]);
        $this->addReceipt([]);

        $c = new TestableCash();

        $this->assertSame([], $c->call('pending_receipts')['receipts']);
        $this->assertCount(Cash::PENDING_SCAN_MAX, $c->calls);
    }

    /** One receipt failing to name its related models is broken; the rest go out. */
    public function testRelatedIdsFailureBreaksOnlyThatReceipt(): void
    {
        $bad = $this->addReceipt([]);
        $good = $this->addReceipt([]);
        $c = new class extends TestableCash {
            protected function createCashDeskReceipt(array $row): Model
            {
                return new class ($row) extends CashDeskTestReceipt {
                    public function cashDeskRelatedIds(): array
                    {
                        if ((int) $this->getId() === (int) self::$failingId) {
                            throw new \RuntimeException('no target');
                        }

                        return [];
                    }
                };
            }

            protected function preloadCashDeskRelations(array $receipts): array
            {
                return Cash::preloadCashDeskRelations($receipts);
            }
        };
        CashDeskTestReceipt::$failingId = $bad;

        $this->assertSame(
            [$good],
            array_column($c->call('pending_receipts')['receipts'], 'id')
        );
        $this->assertSame('broken', $c->calls[0][0]);
        $this->assertSame($bad, $c->calls[0][1]);
        $this->assertStringContainsString('no target', $c->calls[0][2]);
    }

    /**
     * The real factory and preload: the consumer's receipt class through
     * `Model::create`, its related ids into `CollectionCache` one collection per type.
     */
    /** A failed preload is only slower, but it must leave a trace. */
    public function testPreloadFailureIsLogged(): void
    {
        $c = new class extends TestableCash {
            public function preload(array $receipts): array
            {
                return Cash::preloadCashDeskRelations($receipts);
            }
        };
        $receipt = new class (['id' => 1]) extends CashDeskTestReceipt {
            public function cashDeskRelatedIds(): array
            {
                return [[987654, 1]];
            }
        };

        $this->assertSame([], $c->preload([$receipt]));
        $this->assertStringContainsString(
            'preload',
            implode("\n", TestableCash::$logged)
        );
    }

    public function testRealFactoryAndPreload(): void
    {
        $c = new class extends Cash {
            public function __construct()
            {
            }

            public function make(array $row): Model
            {
                return $this->createCashDeskReceipt($row);
            }

            public function preload(array $receipts): array
            {
                return $this->preloadCashDeskRelations($receipts);
            }
        };
        $receipts = [
            $c->make([
                'id' => 1,
                'user_id' => 501,
                'target_type' => 0,
                'target_id' => 0,
            ]),
            $c->make([
                'id' => 2,
                'user_id' => 502,
                'target_type' => 0,
                'target_id' => 0,
            ]),
        ];
        $class = \diModel::existsFor(\diTypes::payment_receipt);
        $userType = \diTypes::getId('user');

        $this->assertInstanceOf($class, $receipts[0]);
        \diCore\Tool\CollectionCache::remove([$userType]);

        try {
            $this->assertSame([], $c->preload($receipts));
            $users = \diCore\Tool\CollectionCache::get($userType);
            $this->assertNotNull($users, 'users preloaded as one collection');
            $this->assertMatchesRegularExpression(
                "/IN \\('?501'?, ?'?502'?\\)/i",
                $users->getFullQuery()
            );
        } finally {
            \diCore\Tool\CollectionCache::remove([$userType]);
        }
    }

    public function testReceiptProblems(): void
    {
        $c = new class extends TestableCash {
            public function problem(array $r): ?string
            {
                return $this->cashDeskReceiptProblem($r);
            }
        };
        $ok = (new CashDeskTestReceipt([
            'id' => 1,
            'amount' => 290,
        ]))->asArrayForCashDeskV2('https://s');

        $this->assertNull($c->problem($ok));

        $broken = [
            'payments sum' => [
                'payments' => [['type' => 'electronically', 'sum' => 100]],
            ],
            'no payments' => ['payments' => []],
            'unknown type' => ['type' => 'refund'],
            'return without original' => ['type' => 'sellReturn'],
            'empty place' => ['paymentsPlace' => ''],
            'contact over tag 1008' => [
                'contact' => str_repeat('a', 53) . '@example.com',
            ],
            'float total' => ['total' => 290.0],
            'long name' => [
                'items' => [
                    array_merge($ok['items'][0], ['name' => str_repeat('я', 129)]),
                ],
            ],
            'price × quantity' => [
                'items' => [array_merge($ok['items'][0], ['quantity' => 2])],
            ],
            'no payment method' => [
                'items' => [array_merge($ok['items'][0], ['paymentMethod' => ''])],
            ],
        ];

        foreach ($broken as $case => $patch) {
            $this->assertNotNull($c->problem(array_merge($ok, $patch)), $case);
        }

        $return = array_merge($ok, ['type' => 'sellReturn', 'originalId' => 7]);
        $this->assertNull($c->problem($return));
    }

    /* --- receipt_fiscalized ------------------------------------------------------ */

    public function testFiscalizedWritesEveryFieldOnce(): void
    {
        $id = $this->addReceipt(['amount' => 290, 'fiscal_mark' => 'from-imap']);
        $c = new TestableCash();

        $this->assertSame(
            ['ok' => true],
            $c->call('receipt_fiscalized', ['id' => $id, 'fiscal' => self::FISCAL])
        );

        $row = $this->row($id);
        $this->assertSame('71', $row['fiscal_doc_id']);
        $this->assertSame('1494325660', $row['fiscal_mark_kkt']);
        $this->assertSame(
            'from-imap',
            $row['fiscal_mark'],
            'fiscal_mark stays with IMAP'
        );
        $this->assertSame('9961440300243452', $row['fiscal_fn_number']);
        $this->assertSame('0000000001002292', $row['fiscal_kkt_reg_number']);
        $this->assertSame(29000, (int) $row['fiscal_total']);
        $this->assertSame(12, (int) $row['fiscal_session']);
        $this->assertSame(1, (int) $row['fiscal_number']);
        $this->assertSame(
            (new \DateTime(self::FISCAL['dateTime']))->getTimestamp(),
            strtotime($row['fiscal_date'])
        );
        $this->assertNotEmpty($row['date_uploaded']);
        $this->assertSame([], $c->calls);

        // and the receipt has left the queue
        $this->assertSame(['receipts' => []], $c->call('pending_receipts'));
    }

    public function testFiscalMarkFromTheCashDeskWhenTheProjectSaysSo(): void
    {
        TestableCash::$useFiscalMark = true;
        $id = $this->addReceipt(['amount' => 290]);

        (new TestableCash())->call('receipt_fiscalized', [
            'id' => $id,
            'fiscal' => self::FISCAL,
        ]);

        $this->assertSame('1494325660', $this->row($id)['fiscal_mark']);
    }

    public function testRepeatWithTheSameDocumentIsOkAndChangesNothing(): void
    {
        $id = $this->addReceipt(['amount' => 290]);
        $c = new TestableCash();
        $c->call('receipt_fiscalized', ['id' => $id, 'fiscal' => self::FISCAL]);
        $before = $this->row($id);

        $repeat = array_merge(self::FISCAL, ['sign' => '1', 'total' => 1]);

        $this->assertSame(
            ['ok' => true],
            $c->call('receipt_fiscalized', ['id' => $id, 'fiscal' => $repeat])
        );
        $this->assertSame($before, $this->row($id));
        $this->assertSame([], $c->calls, 'a repeat is not a mismatch either');
    }

    public function testAnotherDocumentIsConflict(): void
    {
        $id = $this->addReceipt(['amount' => 290]);
        $c = new TestableCash();
        $c->call('receipt_fiscalized', ['id' => $id, 'fiscal' => self::FISCAL]);

        $e = $this->httpError(function () use ($c, $id) {
            $c->call('receipt_fiscalized', [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, ['documentNumber' => 72]),
            ]);
        });

        $this->assertSame(409, $e->getCode());
        $this->assertSame(
            ['error' => 'conflict', 'documentNumber' => 71],
            $e->getBody()
        );
        $this->assertSame([['double', $id, '71', 72]], $c->calls);
        $this->assertSame('71', $this->row($id)['fiscal_doc_id']);
    }

    /**
     * Another report lands between our read and our write: the guard in the UPDATE
     * keeps its document, a read-then-write would have replaced it.
     */
    public function testParallelWriteIsNotOverwritten(): void
    {
        $id = $this->addReceipt(['amount' => 290]);
        $c = new TestableCash();
        $c->competingDocumentNumber = '80';

        $e = $this->httpError(function () use ($c, $id) {
            $c->call('receipt_fiscalized', ['id' => $id, 'fiscal' => self::FISCAL]);
        });

        $this->assertSame(409, $e->getCode());
        $this->assertSame(
            ['error' => 'conflict', 'documentNumber' => 80],
            $e->getBody()
        );
        $this->assertSame('80', $this->row($id)['fiscal_doc_id']);
        $this->assertSame('', (string) $this->row($id)['fiscal_mark_kkt']);
        $this->assertSame([['double', $id, '80', 71]], $c->calls);

        // the same number from the competitor is a repeat, not a conflict
        $c->competingDocumentNumber = '71';
        $other = $this->addReceipt(['amount' => 290]);
        $this->assertSame(
            ['ok' => true],
            $c->call('receipt_fiscalized', [
                'id' => $other,
                'fiscal' => self::FISCAL,
            ])
        );
    }

    public function testReportedWithoutDocumentCountsAsFiscalized(): void
    {
        $id = $this->addReceipt([
            'amount' => 290,
            'date_uploaded' => \diDateTime::sqlFormat('-1 hour'),
        ]);
        $c = new TestableCash();

        $e = $this->httpError(function () use ($c, $id) {
            $c->call('receipt_fiscalized', ['id' => $id, 'fiscal' => self::FISCAL]);
        });

        $this->assertSame(409, $e->getCode());
        $this->assertSame(
            ['error' => 'conflict', 'documentNumber' => null],
            $e->getBody()
        );
        $this->assertSame([['double', $id, '', 71]], $c->calls);
    }

    /**
     * The document is recorded but the report is missing (OFD mail, by hand): the
     * repeat marks it reported, otherwise it stays overdue forever.
     */
    public function testRepeatOfARecordedButUnreportedDocumentMarksItReported(): void
    {
        $id = $this->addReceipt([
            'amount' => 290,
            'fiscal_doc_id' => '71',
            'fiscal_fn_number' => self::FISCAL['fnNumber'],
        ]);
        $c = new TestableCash();

        $this->assertSame(
            ['ok' => true],
            $c->call('receipt_fiscalized', ['id' => $id, 'fiscal' => self::FISCAL])
        );
        $this->assertNotEmpty($this->row($id)['date_uploaded']);
        $this->assertSame([], $c->calls);
    }

    /**
     * The OFD mail parser may have filled part of the record: the cash desk's data
     * goes only into what is still empty, and the total is checked as on first write.
     */
    public function testRepeatOfARecordedDocumentFillsOnlyEmptyFields(): void
    {
        $id = $this->addReceipt([
            'amount' => 290,
            'fiscal_doc_id' => '71',
            'fiscal_mark_kkt' => '777',
            'fiscal_session' => 5,
        ]);
        $c = new TestableCash();

        $this->assertSame(
            ['ok' => true],
            $c->call('receipt_fiscalized', [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, ['total' => 28900]),
            ])
        );

        $row = $this->row($id);
        $this->assertNotEmpty($row['date_uploaded']);
        $this->assertSame('777', $row['fiscal_mark_kkt'], 'filled by OFD mail');
        $this->assertSame(5, (int) $row['fiscal_session'], 'filled by OFD mail');
        $this->assertSame('9961440300243452', $row['fiscal_fn_number']);
        $this->assertSame('0000000001002292', $row['fiscal_kkt_reg_number']);
        $this->assertSame(28900, (int) $row['fiscal_total']);
        $this->assertSame(1, (int) $row['fiscal_number']);
        $this->assertSame(
            (new \DateTime(self::FISCAL['dateTime']))->getTimestamp(),
            strtotime($row['fiscal_date'])
        );
        $this->assertSame('', (string) $row['fiscal_mark'], 'stays with IMAP');
        $this->assertSame([['mismatch', $id, 29000, 28900]], $c->calls);

        // a further repeat changes nothing and reports nothing
        $c->calls = [];
        $c->call('receipt_fiscalized', [
            'id' => $id,
            'fiscal' => array_merge(self::FISCAL, ['total' => 28900]),
        ]);
        $this->assertSame($row, $this->row($id));
        $this->assertSame([], $c->calls);
    }

    /**
     * Numbering restarts on a new fiscal drive: document 71 of another drive is
     * another document.
     */
    public function testSameNumberOnAnotherFiscalDriveIsConflict(): void
    {
        $id = $this->addReceipt(['amount' => 290]);
        $c = new TestableCash();
        $c->call('receipt_fiscalized', ['id' => $id, 'fiscal' => self::FISCAL]);
        $before = $this->row($id);

        $e = $this->httpError(function () use ($c, $id) {
            $c->call('receipt_fiscalized', [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, [
                    'fnNumber' => '9961440300999999',
                ]),
            ]);
        });

        $this->assertSame(409, $e->getCode());
        $this->assertSame([['double', $id, '71', 71]], $c->calls);
        $this->assertSame($before, $this->row($id));
    }

    public function testRowWithoutFiscalDriveIsComparedByNumberAlone(): void
    {
        $id = $this->addReceipt([
            'amount' => 290,
            'fiscal_doc_id' => '71',
            'date_uploaded' => \diDateTime::sqlFormat('-1 hour'),
        ]);
        $c = new TestableCash();

        $this->assertSame(
            ['ok' => true],
            $c->call('receipt_fiscalized', ['id' => $id, 'fiscal' => self::FISCAL])
        );
        $this->assertSame([], $c->calls);
    }

    public function testTotalMismatchIsWrittenAndReported(): void
    {
        $id = $this->addReceipt(['amount' => 290]);
        $c = new TestableCash();

        $this->assertSame(
            ['ok' => true],
            $c->call('receipt_fiscalized', [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, ['total' => 28900]),
            ])
        );
        $this->assertSame('71', $this->row($id)['fiscal_doc_id']);
        $this->assertSame(28900, (int) $this->row($id)['fiscal_total']);
        $this->assertSame([['mismatch', $id, 29000, 28900]], $c->calls);
    }

    /** No payment, no receipt: the cash desk blocks this one, the operator looks. */
    public function testUnpaidReceiptIsNotFoundAndReported(): void
    {
        $id = $this->addReceipt(['amount' => 290, 'date_payed' => null]);
        $c = new TestableCash();

        $e = $this->httpError(function () use ($c, $id) {
            $c->call('receipt_fiscalized', ['id' => $id, 'fiscal' => self::FISCAL]);
        });

        $this->assertSame(404, $e->getCode());
        $this->assertSame(['error' => 'not_found'], $e->getBody());
        $this->assertSame('unexpected', $c->calls[0][0] ?? null);
        $this->assertSame($id, $c->calls[0][1]);
        $this->assertSame('', (string) $this->row($id)['fiscal_doc_id']);
    }

    /** A late report is legitimate: written, but the operator hears of it. */
    public function testReportOutsideTheWindowIsAcceptedAndReported(): void
    {
        $id = $this->addReceipt([
            'amount' => 290,
            'date_payed' => \diDateTime::sqlFormat('-40 days'),
        ]);
        $c = new TestableCash();

        $this->assertSame(
            ['ok' => true],
            $c->call('receipt_fiscalized', ['id' => $id, 'fiscal' => self::FISCAL])
        );
        $this->assertSame('71', $this->row($id)['fiscal_doc_id']);
        $this->assertSame('unexpected', $c->calls[0][0] ?? null);
    }

    /** OFD mail may store the number with leading zeros. */
    public function testStoredNumberWithLeadingZerosIsTheSameDocument(): void
    {
        $id = $this->addReceipt([
            'amount' => 290,
            'fiscal_doc_id' => '071',
            'fiscal_fn_number' => self::FISCAL['fnNumber'],
        ]);

        $this->assertSame(
            ['ok' => true],
            (new TestableCash())->call('receipt_fiscalized', [
                'id' => $id,
                'fiscal' => self::FISCAL,
            ])
        );
        $this->assertNotEmpty($this->row($id)['date_uploaded']);
    }

    public function testUnknownReceiptIsNotFound(): void
    {
        $e = $this->httpError(function () {
            (new TestableCash())->call('receipt_fiscalized', [
                'id' => 999999,
                'fiscal' => self::FISCAL,
            ]);
        });

        $this->assertSame(404, $e->getCode());
    }

    /**
     * The client reads "no such receipt" only from 404 with exactly this body (a
     * route 404 has another): rendered by `makeResponse()`, the function
     * `autoError()` answers an escaped exception with.
     */
    public function testNotFoundGoesOutAsTheContractBody(): void
    {
        $e = $this->httpError(function () {
            (new TestableCash())->call('receipt_fiscalized', [
                'id' => 999999,
                'fiscal' => self::FISCAL,
            ]);
        });

        ob_start();

        try {
            TestableCash::makeResponse($e);
        } finally {
            $out = ob_get_clean();
        }

        $this->assertSame(404, $e->getCode());
        $this->assertSame('{"error":"not_found"}', trim($out));
    }

    public function testMalformedFiscalIsBadRequestAndWritesNothing(): void
    {
        $id = $this->addReceipt(['amount' => 290]);
        $cases = [
            'no id' => ['fiscal' => self::FISCAL],
            'no fiscal' => ['id' => $id],
            'zero document' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, ['documentNumber' => 0]),
            ],
            'float document' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, ['documentNumber' => 71.5]),
            ],
            'sign with letters' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, ['sign' => '12ab']),
            ],
            'sign too long' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, ['sign' => '12345678901']),
            ],
            'time without zone' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, [
                    'dateTime' => '2026-10-31T12:00:05',
                ]),
            ],
            'no total' => [
                'id' => $id,
                'fiscal' => array_diff_key(self::FISCAL, ['total' => 1]),
            ],
            'negative total' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, ['total' => -1]),
            ],
            // `$` would let a trailing newline through, `\z` does not
            'document with a newline' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, ['documentNumber' => "71\n"]),
            ],
            'time with a newline' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, [
                    'dateTime' => "2026-10-31T12:00:05+03:00\n",
                ]),
            ],
            'document over uint32' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, [
                    'documentNumber' => Cash::DOCUMENT_NUMBER_MAX + 1,
                ]),
            ],
            'document of 11 digits as a string' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, [
                    'documentNumber' => '10000000000',
                ]),
            ],
            'total over the INT column' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, [
                    'total' => Cash::INT_COLUMN_MAX + 1,
                ]),
            ],
            'shift over the INT column' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, [
                    'shiftNumber' => Cash::INT_COLUMN_MAX + 1,
                ]),
            ],
            'receipt number over the INT column' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, [
                    'receiptNumber' => '99999999999',
                ]),
            ],
            // a JSON number loses leading zeros, and a 16-digit FN is past 2^53 in JS
            'FN number as a JSON number' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, [
                    'fnNumber' => (int) self::FISCAL['fnNumber'],
                ]),
            ],
            'registration number as a JSON number' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, [
                    'registrationNumber' => (int) self::FISCAL['registrationNumber'],
                ]),
            ],
            'sign as a JSON number' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, [
                    'sign' => (int) self::FISCAL['sign'],
                ]),
            ],
            'sign over uint32' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, ['sign' => '4294967296']),
            ],
            'nonexistent date' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, [
                    'dateTime' => '2026-02-30T12:00:05+03:00',
                ]),
            ],
            'nonexistent time' => [
                'id' => $id,
                'fiscal' => array_merge(self::FISCAL, [
                    'dateTime' => '2026-10-31T24:00:05+03:00',
                ]),
            ],
        ];

        foreach ($cases as $case => $body) {
            $e = $this->httpError(function () use ($body) {
                (new TestableCash())->call('receipt_fiscalized', $body);
            });

            $this->assertSame(400, $e->getCode(), $case);
        }

        $this->assertSame('', (string) $this->row($id)['fiscal_doc_id']);
    }

    public function testLargestValuesThatFitAreAccepted(): void
    {
        $id = $this->addReceipt(['amount' => 290]);
        $c = new TestableCash();

        $c->call('receipt_fiscalized', [
            'id' => $id,
            'fiscal' => array_merge(self::FISCAL, [
                'documentNumber' => Cash::DOCUMENT_NUMBER_MAX,
                'sign' => '4294967295',
                'total' => Cash::INT_COLUMN_MAX,
                'shiftNumber' => Cash::INT_COLUMN_MAX,
                'receiptNumber' => Cash::INT_COLUMN_MAX,
            ]),
        ]);

        $row = $this->row($id);
        $this->assertSame('4294967295', $row['fiscal_doc_id']);
        $this->assertSame('4294967295', $row['fiscal_mark_kkt']);
        $this->assertSame(Cash::INT_COLUMN_MAX, (int) $row['fiscal_total']);
        $this->assertSame(Cash::INT_COLUMN_MAX, (int) $row['fiscal_session']);
        $this->assertSame(Cash::INT_COLUMN_MAX, (int) $row['fiscal_number']);
    }

    public function testZuluTimeIsAccepted(): void
    {
        $id = $this->addReceipt(['amount' => 290]);
        (new TestableCash())->call('receipt_fiscalized', [
            'id' => $id,
            'fiscal' => array_merge(self::FISCAL, [
                'dateTime' => '2026-10-31T09:00:05.000Z',
            ]),
        ]);

        $this->assertSame(
            (new \DateTime(self::FISCAL['dateTime']))->getTimestamp(),
            strtotime($this->row($id)['fiscal_date'])
        );
    }

    /* --- heartbeat ------------------------------------------------------------- */

    public function testHeartbeatStoresStatusAndJournalAndAcks(): void
    {
        $c = new TestableCash();
        $body = $this->heartbeat([$this->entry(1202), $this->entry(1201)]);

        $this->assertSame(
            ['ok' => true, 'journalAckSeq' => 1202],
            $c->call('heartbeat', $body)
        );

        $last = TestableCash::lastHeartbeat();
        $this->assertEquals(
            array_diff_key($body, ['journal' => 1]),
            $last['payload']
        );
        $this->assertLessThan(5, $last['ageSeconds']);
        $this->assertLessThan(5, TestableCash::lastHeartbeatAgeSeconds());

        $journal = $this->rows(TestableCash::JOURNAL, 'ORDER BY seq');
        $this->assertSame(
            ['1201', '1202'],
            array_map('strval', array_column($journal, 'seq'))
        );
        $this->assertSame(self::JOURNAL_ID, $journal[0]['journal_id']);
        $this->assertSame('shop', $journal[0]['source']);
        $this->assertSame(91887, (int) $journal[0]['receipt_id']);
        $this->assertSame(71, (int) $journal[0]['document_number']);
        $this->assertSame('reported', $journal[0]['state']);
        $this->assertEquals(
            $this->entry(1201),
            json_decode($journal[0]['payload'], true)
        );
        $this->assertSame([], $c->calls);
    }

    /** Postgres refuses NUL in jsonb and text: one such byte must not stall heartbeats. */
    public function testNulBytesAreStrippedBeforeStoring(): void
    {
        $body = $this->heartbeat([
            array_merge($this->entry(1), [
                'source' => "sh\u{0}op",
                'state' => "re\u{0}ported",
                "no\u{0}te" => "a\u{0}b",
            ]),
        ]);
        $body['kkt']['lastError'] = "bad\u{0}byte";

        $answer = (new TestableCash())->call('heartbeat', $body);

        $this->assertSame(1, $answer['journalAckSeq']);
        $this->assertSame(
            'badbyte',
            TestableCash::lastHeartbeat()['payload']['kkt']['lastError']
        );
        $journal = $this->rows(TestableCash::JOURNAL)[0];
        $this->assertSame('shop', $journal['source']);
        $this->assertSame('reported', $journal['state']);
        $this->assertSame('ab', json_decode($journal['payload'], true)['note']);
    }

    /** The status matters more to the watchdog; the journal is resent without an ack. */
    public function testJournalFailureKeepsTheStatusAndAnswers500(): void
    {
        $c = new class extends TestableCash {
            protected function storeJournal(
                string $journalId,
                array $entries,
                string $now
            ): ?int {
                throw new \diDatabaseException('journal down');
            }
        };

        $e = $this->httpError(function () use ($c) {
            $c->call('heartbeat', $this->heartbeat([$this->entry(1)]));
        });

        $this->assertSame(500, $e->getCode());
        $this->assertArrayNotHasKey('journalAckSeq', (array) $e->getBody());
        $this->assertSame(1, $this->rowCount(TestableCash::HEARTBEATS));
        $this->assertSame(
            [['journal_store', self::JOURNAL_ID, 'diDatabaseException']],
            $c->calls
        );
    }

    public function testRepeatedSeqIsIgnoredAndAcked(): void
    {
        $c = new TestableCash();
        $c->call('heartbeat', $this->heartbeat([$this->entry(1201)]));

        $this->assertSame(
            ['ok' => true, 'journalAckSeq' => 1202],
            $c->call(
                'heartbeat',
                $this->heartbeat([$this->entry(1201), $this->entry(1202)])
            )
        );
        $this->assertSame(2, $this->rowCount(TestableCash::JOURNAL));
        $this->assertSame(2, $this->rowCount(TestableCash::HEARTBEATS));
        $this->assertSame([], $c->calls);
    }

    public function testRepeatedSeqWithOtherContentsIsReportedAndKeepsTheFirst(): void
    {
        $c = new TestableCash();
        $c->call('heartbeat', $this->heartbeat([$this->entry(5)]));
        $other = array_merge($this->entry(5), ['receiptId' => 1]);

        $this->assertSame(
            ['ok' => true, 'journalAckSeq' => 5],
            $c->call('heartbeat', $this->heartbeat([$other]))
        );
        $this->assertSame([['journal', self::JOURNAL_ID, 5]], $c->calls);
        $this->assertSame(
            91887,
            (int) $this->rows(TestableCash::JOURNAL)[0]['receipt_id']
        );
    }

    /**
     * A new SD card starts its journal with seq 1 under a new journalId: stored
     * beside the old one, acked within its own journal, no conflict.
     */
    public function testNewJournalStartsOverWithoutConflict(): void
    {
        $c = new TestableCash();
        $c->call(
            'heartbeat',
            $this->heartbeat([$this->entry(1), $this->entry(2), $this->entry(3)])
        );

        $fresh = array_merge($this->entry(1, self::NEW_JOURNAL_ID), [
            'receiptId' => 91900,
        ]);

        $this->assertSame(
            ['ok' => true, 'journalAckSeq' => 1],
            $c->call('heartbeat', $this->heartbeat([$fresh], self::NEW_JOURNAL_ID))
        );
        $this->assertSame([], $c->calls);
        $this->assertSame(
            1,
            $this->rowCount(
                TestableCash::JOURNAL,
                "journal_id = '" . self::NEW_JOURNAL_ID . "' AND seq = 1"
            )
        );
        $this->assertSame(4, $this->rowCount(TestableCash::JOURNAL));
    }

    public function testJournalIdIsCaseInsensitive(): void
    {
        $c = new TestableCash();
        $c->call('heartbeat', $this->heartbeat([$this->entry(1)]));
        $upper = strtoupper(self::JOURNAL_ID);

        $c->call('heartbeat', $this->heartbeat([$this->entry(1, $upper)], $upper));

        $this->assertSame(1, $this->rowCount(TestableCash::JOURNAL));
        $this->assertSame(
            self::JOURNAL_ID,
            $this->rows(TestableCash::JOURNAL)[0]['journal_id']
        );
    }

    public function testEmptyJournalAcksNothing(): void
    {
        $c = new TestableCash();
        $c->call('heartbeat', $this->heartbeat([$this->entry(9)]));

        $this->assertSame(
            ['ok' => true, 'journalAckSeq' => null],
            $c->call('heartbeat', $this->heartbeat([]))
        );
        $this->assertSame(
            ['ok' => true, 'journalAckSeq' => null],
            $c->call('heartbeat', [
                'journalId' => self::JOURNAL_ID,
                'kkt' => ['online' => false],
            ])
        );
    }

    /** Without a journal id there is nothing to ack against: 400, nothing stored. */
    public function testMissingJournalIdIsBadRequestAndStoresNothing(): void
    {
        $id = self::JOURNAL_ID;
        $cases = [
            'no journalId' => ['kkt' => ['online' => true]],
            'journalId not a UUID' => ['journalId' => 'sd-card-1', 'journal' => []],
            'journalId with a newline' => ['journalId' => "$id\n", 'journal' => []],
        ];

        foreach ($cases as $case => $body) {
            $e = $this->httpError(function () use ($body) {
                (new TestableCash())->call('heartbeat', $body);
            });

            $this->assertSame(400, $e->getCode(), $case);
        }

        $this->assertSame(0, $this->rowCount(TestableCash::HEARTBEATS));
        $this->assertSame(0, $this->rowCount(TestableCash::JOURNAL));
    }

    /**
     * One bad journal entry must not cost the status (the watchdog would read the
     * cash desk as offline): the status is stored, the entry skipped – acked when
     * it has a seq, so the ack moves on – and reported once an hour.
     */
    public function testMalformedJournalEntriesAreSkippedAndTheStatusStored(): void
    {
        $id = self::JOURNAL_ID;
        $body = $this->heartbeat([
            $this->entry(1),
            ['journalId' => $id, 'source' => 'no seq'],
            ['journalId' => $id, 'seq' => 0],
            1,
            ['seq' => 4],
            ['journalId' => self::NEW_JOURNAL_ID, 'seq' => 5],
            $this->entry(2),
        ]);
        $c = new TestableCash();

        $this->assertSame(5, $c->call('heartbeat', $body)['journalAckSeq']);
        $this->assertSame(1, $this->rowCount(TestableCash::HEARTBEATS));
        $this->assertSame(
            ['1', '2'],
            array_map(
                'strval',
                array_column(
                    $this->rows(TestableCash::JOURNAL, 'ORDER BY seq'),
                    'seq'
                )
            )
        );
        $this->assertCount(1, $c->calls);
        $this->assertSame('report', $c->calls[0][0]);
        $this->assertStringContainsString('skipped 5', $c->calls[0][1]);

        // hourly: the same bad entries resent next minute are not reported again
        $c->call('heartbeat', $body);
        $this->assertCount(1, $c->calls);
    }

    /** Not a list at all: the status is still stored, nothing acked. */
    public function testJournalThatIsNotAListIsSkipped(): void
    {
        foreach ([['seq' => 1], ['a' => $this->entry(1)], 'x'] as $journal) {
            $body = $this->heartbeat([]);
            $body['journal'] = $journal;

            $this->assertNull(
                (new TestableCash())->call('heartbeat', $body)['journalAckSeq']
            );
        }

        $this->assertSame(3, $this->rowCount(TestableCash::HEARTBEATS));
        $this->assertSame(0, $this->rowCount(TestableCash::JOURNAL));
    }

    /** The status is a few KB; a huge one is a client bug, not a status. */
    public function testOversizedStatusIsRefused(): void
    {
        $body = $this->heartbeat([$this->entry(1)]);
        $body['kkt']['lastError'] = str_repeat('x', Cash::STATUS_MAX_BYTES);

        $this->assertSame(
            413,
            $this->httpError(function () use ($body) {
                (new TestableCash())->call('heartbeat', $body);
            })->getCode()
        );
        $this->assertSame(0, $this->rowCount(TestableCash::HEARTBEATS));
        $this->assertSame(0, $this->rowCount(TestableCash::JOURNAL));

        // the journal itself does not count: it is capped on its own
        $body = $this->heartbeat([]);
        $body['journal'] = array_fill(0, 3, [
            'journalId' => self::JOURNAL_ID,
            'seq' => 1,
            'note' => str_repeat('y', Cash::STATUS_MAX_BYTES),
        ]);
        $this->assertSame(
            1,
            (new TestableCash())->call('heartbeat', $body)['journalAckSeq']
        );
    }

    public function testJournalBatchIsCappedAndAcksOnlyWhatWasStored(): void
    {
        $entries = [];

        for ($seq = Cash::JOURNAL_MAX_ENTRIES + 10; $seq >= 1; $seq--) {
            $entries[] = ['journalId' => self::JOURNAL_ID, 'seq' => $seq];
        }

        $answer = (new TestableCash())->call('heartbeat', [
            'journalId' => self::JOURNAL_ID,
            'journal' => $entries,
        ]);

        $this->assertSame(Cash::JOURNAL_MAX_ENTRIES, $answer['journalAckSeq']);
        $this->assertSame(
            Cash::JOURNAL_MAX_ENTRIES,
            $this->rowCount(TestableCash::JOURNAL)
        );
    }

    public function testOldHeartbeatsArePurged(): void
    {
        self::$db->q(
            'INSERT INTO ' .
                TestableCash::HEARTBEATS .
                " (payload, received_at) VALUES ('{}', '" .
                \diDateTime::sqlFormat(
                    '-' . (Cash::HEARTBEAT_RETENTION_DAYS + 1) . ' days'
                ) .
                "')"
        );
        (new TestableCash())->call('heartbeat', $this->heartbeat([]));

        $this->assertSame(1, $this->rowCount(TestableCash::HEARTBEATS));
    }

    public function testNoHeartbeatYet(): void
    {
        $this->assertNull(TestableCash::lastHeartbeat());
        $this->assertNull(TestableCash::lastHeartbeatAgeSeconds());
    }

    /* --- watchdog ---------------------------------------------------------------- */

    public function testOverdueReceiptsAreTheQueueOlderThanTheThreshold(): void
    {
        $overdue = $this->addReceipt([
            'date_payed' => \diDateTime::sqlFormat('-20 minutes'),
        ]);
        $older = $this->addReceipt([
            'date_payed' => \diDateTime::sqlFormat('-3 hours'),
        ]);
        $this->addReceipt(['date_payed' => \diDateTime::sqlFormat('-5 minutes')]);
        $this->addReceipt([
            'date_payed' => \diDateTime::sqlFormat('-1 hour'),
            'date_uploaded' => \diDateTime::sqlFormat('-50 minutes'),
        ]);
        $this->addReceipt(['date_payed' => \diDateTime::sqlFormat('-2 months')]);

        $this->assertSame([$older, $overdue], TestableCash::overdueReceiptIds(15));
        $this->assertSame([$older], TestableCash::overdueReceiptIds(15, 1));
        // excluded in SQL, so the limit is not spent on them
        $this->assertSame(
            [$overdue],
            TestableCash::overdueReceiptIds(15, 1, [$older])
        );
    }

    /**
     * "--5 minutes" is a date in the future to strtotime(): a negative threshold
     * would call a receipt paid a moment ago overdue.
     */
    public function testNegativeThresholdMeansNow(): void
    {
        $this->addReceipt(['date_payed' => \diDateTime::sqlFormat('+3 minutes')]);

        $this->assertSame([], TestableCash::overdueReceiptIds(-5));
    }

    /** SQL goes to the log; the exception (and so Sentry, the answer) gets none of it. */
    public function testFailedQueryKeepsTheSqlOutOfTheException(): void
    {
        $c = new class extends TestableCash {
            protected static function receiptsTable(): string
            {
                return '_di_core_test_no_such_table';
            }
        };
        TestableCash::$logged = [];

        try {
            $c->call('pending_receipts');
            $this->fail('an exception expected');
        } catch (\diDatabaseException $e) {
            $this->assertStringNotContainsString('SELECT', $e->getMessage());
            $this->assertStringNotContainsString('_di_core_test', $e->getMessage());
        }

        $this->assertStringContainsString(
            '_di_core_test_no_such_table',
            implode("\n", TestableCash::$logged)
        );
    }

    /** The queue, v1 and the models must read one database. */
    public function testCashDeskReadsTheReceiptModelsConnection(): void
    {
        $model = \diModel::create(\diTypes::payment_receipt);
        $modelDb = (new \ReflectionMethod($model, 'getDb'))->invoke($model);
        $cashDb = (new \ReflectionMethod(Cash::class, 'cashDeskDb'))->invoke(null);

        $this->assertSame($modelDb, $cashDb);
    }

    /* --- default hooks ------------------------------------------------------------ */

    public function testDefaultHooksWarnNotSilently(): void
    {
        $this->addReceipt(['amount' => 0]);
        $warnings = [];
        set_error_handler(function ($no, $text) use (&$warnings) {
            $warnings[] = [$no, $text];

            return true;
        });

        try {
            (new ReportingCash())->call('pending_receipts');
        } finally {
            restore_error_handler();
        }

        $this->assertCount(1, $warnings);
        $this->assertSame(E_USER_WARNING, $warnings[0][0]);
        $this->assertStringContainsString(
            'not served to the cash desk',
            $warnings[0][1]
        );
    }

    /**
     * The cash desk polls every minute: a default hook reporting each poll is a
     * warning a minute per broken receipt.
     */
    public function testDefaultBrokenReceiptReportIsHourly(): void
    {
        $broken = $this->addReceipt(['amount' => 0]);
        $other = $this->addReceipt(['amount' => 0]);
        $warnings = [];
        set_error_handler(function ($no, $text) use (&$warnings) {
            if ($no === E_USER_WARNING) {
                $warnings[] = $text;
            }

            return true;
        });

        try {
            (new ReportingCash())->call('pending_receipts');
            (new ReportingCash())->call('pending_receipts');
            $this->assertCount(2, $warnings, 'once per receipt');

            // an hour later it is reported again
            touch(
                TestableCash::$markerFolder . $broken,
                time() - Cash::BROKEN_RECEIPT_REPORT_INTERVAL_SEC - 1
            );
            (new ReportingCash())->call('pending_receipts');
        } finally {
            restore_error_handler();
        }

        $this->assertCount(3, $warnings);
        $this->assertStringContainsString("#$broken ", $warnings[2]);
        $this->assertStringContainsString("#$other ", implode("\n", $warnings));
    }

    /** A receipt out of the queue window is never served again: its marker goes. */
    public function testStaleMarkersAreRemovedWhenANewOneIsWritten(): void
    {
        mkdir(TestableCash::$markerFolder, 0775, true);
        $stale = TestableCash::$markerFolder . '999999';
        $recent = TestableCash::$markerFolder . '999998';
        touch($stale, strtotime(Cash::dates) - 60);
        touch($recent, time() - 2 * Cash::BROKEN_RECEIPT_REPORT_INTERVAL_SEC);
        $this->addReceipt(['amount' => 0]);

        set_error_handler(function () {
            return true;
        });

        try {
            (new ReportingCash())->call('pending_receipts');
        } finally {
            restore_error_handler();
        }

        $this->assertFileDoesNotExist($stale);
        $this->assertFileExists($recent);
    }

    /** No token configured: every poll is refused, the report is hourly. */
    public function testMissingTokenIsReportedHourly(): void
    {
        $warnings = [];
        set_error_handler(function ($no, $text) use (&$warnings) {
            if ($no === E_USER_WARNING) {
                $warnings[] = $text;
            }

            return true;
        });

        $poll = function () {
            $c = new ReportingCash();
            $c->token = '';
            $this->assertSame(
                401,
                $this->httpError(function () use ($c) {
                    $c->call('pending_receipts');
                })->getCode()
            );
        };

        try {
            $poll();
            $poll();
            $this->assertCount(1, $warnings);

            foreach (glob(TestableCash::$markerFolder . '*') as $marker) {
                touch(
                    $marker,
                    time() - Cash::BROKEN_RECEIPT_REPORT_INTERVAL_SEC - 1
                );
            }
            $poll();
        } finally {
            restore_error_handler();
        }

        $this->assertCount(2, $warnings);
        $this->assertStringContainsString('CASHDESK_TOKEN', $warnings[1]);
    }

    /**
     * No cache folder configured: the marker must not land in the cwd (under cron
     * that is home or /), it goes to the temp folder and still throttles.
     */
    public function testWithoutCacheFolderMarkersGoToTheTempFolder(): void
    {
        $base = TestableCash::$markerFolder;
        $cwd = $base . 'cwd/';
        $temp = $base . 'temp/';
        mkdir($cwd, 0775, true);
        mkdir($temp, 0775, true);
        $this->addReceipt(['amount' => 0]);
        $warnings = 0;
        set_error_handler(function ($no) use (&$warnings) {
            $warnings += $no === E_USER_WARNING ? 1 : 0;

            return true;
        });
        $oldCwd = getcwd();
        chdir($cwd);

        try {
            $folder = TempFolderCash::reportFolder('', $temp);
            TestableCash::$markerFolder = $folder;
            (new ReportingCash())->call('pending_receipts');
            (new ReportingCash())->call('pending_receipts');
        } finally {
            chdir($oldCwd);
            restore_error_handler();
            TestableCash::$markerFolder = $base;
        }

        $this->assertStringStartsWith($temp . 'di_core-cashdesk-', $folder);
        $this->assertSame([], glob($cwd . '*'));
        $this->assertCount(1, glob($folder . '*'));
        $this->assertSame(1, $warnings);

        // an unusable cache folder is the same as none; a usable one is used
        $this->assertSame(
            $folder,
            TempFolderCash::reportFolder($base . 'missing/', $temp)
        );
        $this->assertSame(
            $cwd . 'cashdesk/broken-receipts/',
            TempFolderCash::reportFolder($cwd, $temp)
        );

        foreach ([$folder, dirname($folder) . '/', $temp, $cwd] as $dir) {
            array_map('unlink', glob($dir . '*') ?: []);
            rmdir($dir);
        }
    }

    /** A marker that can't be written must not silence the report. */
    public function testUnwritableMarkerStillReports(): void
    {
        $this->addReceipt(['amount' => 0]);
        $file = tempnam(sys_get_temp_dir(), 'di-core-cashdesk-');
        // a file where the folder should be: neither mkdir nor touch can succeed
        TestableCash::$markerFolder = "$file/";
        $warnings = 0;
        set_error_handler(function ($no) use (&$warnings) {
            // @-suppressed mkdir/touch failures reach the handler too
            $warnings += $no === E_USER_WARNING ? 1 : 0;

            return true;
        });

        try {
            (new ReportingCash())->call('pending_receipts');
            (new ReportingCash())->call('pending_receipts');
        } finally {
            restore_error_handler();
            unlink($file);
        }

        $this->assertSame(2, $warnings);
    }

    /* --- helpers ------------------------------------------------------------------ */

    private function addReceipt(array $values): int
    {
        $values = array_merge(
            [
                'target_type' => 1,
                'target_id' => 1,
                'user_id' => 0,
                'pay_system' => 1,
                'currency' => 1,
                'amount' => 100,
                'outer_number' => 'test',
                'date_payed' => \diDateTime::sqlFormat('-1 hour'),
            ],
            $values
        );

        $fields = implode(', ', array_keys($values));
        $sql = implode(
            ', ',
            array_map(function ($v) {
                return $v === null ? 'NULL' : self::$db->escapeValue((string) $v);
            }, $values)
        );

        $this->assertNotFalse(
            self::$db->q(
                'INSERT INTO ' . TestableCash::RECEIPTS . " ($fields) VALUES ($sql)"
            )
        );

        return (int) $this->rows(
            TestableCash::RECEIPTS,
            'ORDER BY id DESC'
        )[0]['id'];
    }

    private function addReceipts(int $count, array $values): void
    {
        $values = array_merge(
            [
                'target_type' => 1,
                'target_id' => 1,
                'user_id' => 0,
                'pay_system' => 1,
                'currency' => 1,
                'amount' => 100,
                'outer_number' => 'test',
            ],
            $values
        );
        $tuple =
            '(' .
            implode(
                ', ',
                array_map(function ($v) {
                    return self::$db->escapeValue((string) $v);
                }, $values)
            ) .
            ')';

        $this->assertNotFalse(
            self::$db->q(
                'INSERT INTO ' .
                    TestableCash::RECEIPTS .
                    ' (' .
                    implode(', ', array_keys($values)) .
                    ') VALUES ' .
                    implode(', ', array_fill(0, $count, $tuple))
            )
        );
    }

    private function row(int $id): array
    {
        return $this->rows(TestableCash::RECEIPTS, "WHERE id = $id")[0];
    }

    private function rows(string $table, string $tail = ''): array
    {
        $rs = self::$db->q("SELECT * FROM $table $tail");
        $rows = [];

        while ($row = self::$db->fetch_array($rs)) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function rowCount(string $table, string $where = '1 = 1'): int
    {
        return (int) self::$db->fetch_array(
            self::$db->q("SELECT COUNT(*) AS c FROM $table WHERE $where")
        )['c'];
    }

    private function heartbeat(
        array $journal,
        string $journalId = self::JOURNAL_ID
    ): array {
        return [
            'journalId' => $journalId,
            'app' => [
                'version' => '2.0.0',
                'startedAt' => '2026-10-31T09:00:00+03:00',
            ],
            'kkt' => [
                'online' => true,
                'shift' => ['state' => 'opened', 'number' => 152],
                'paperPresent' => true,
                'ofd' => ['notSentCount' => 0, 'notSentFirstDocDateTime' => null],
            ],
            'queue' => ['shop' => ['pending' => 0, 'stoppedReason' => null]],
            'journal' => $journal,
        ];
    }

    private function entry(int $seq, string $journalId = self::JOURNAL_ID): array
    {
        return [
            'journalId' => $journalId,
            'seq' => $seq,
            'source' => 'shop',
            'receiptId' => 91887,
            'state' => 'reported',
            'fnDocumentBefore' => 70,
            'documentNumber' => 71,
            'at' => '2026-10-31T12:00:06+03:00',
        ];
    }

    private function httpError(callable $fn): HttpException
    {
        try {
            $fn();
        } catch (HttpException $e) {
            return $e;
        }

        $this->fail('HttpException expected');
    }
}
