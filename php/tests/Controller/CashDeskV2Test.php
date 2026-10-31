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

    public $token = 'secret-token';
    public $body = '';
    public $authorization = 'Bearer secret-token';
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

    protected function cashDeskPaymentsPlace(): string
    {
        return 'https://shop.example';
    }

    protected function createCashDeskReceipt(array $row): Model
    {
        return new CashDeskTestReceipt($row);
    }

    protected function preloadCashDeskRelations(array $receipts): void
    {
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
        TestableCash::$useFiscalMark = false;
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
    public function testPhoneBecomesE164OrNothing(string $raw, string $expected): void
    {
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
     * A project pads getUserData() with its own address (1romantic: receipt@); the
     * contact must not see it, or a buyer's phone would never be used.
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
        ];

        foreach ($cases as $case => $body) {
            $e = $this->httpError(function () use ($body) {
                (new TestableCash())->call('receipt_fiscalized', $body);
            });

            $this->assertSame(400, $e->getCode(), $case);
        }

        $this->assertSame('', (string) $this->row($id)['fiscal_doc_id']);
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
        $this->assertSame('1romantic', $journal[0]['source']);
        $this->assertSame(91887, (int) $journal[0]['receipt_id']);
        $this->assertSame(71, (int) $journal[0]['document_number']);
        $this->assertSame('reported', $journal[0]['state']);
        $this->assertEquals(
            $this->entry(1201),
            json_decode($journal[0]['payload'], true)
        );
        $this->assertSame([], $c->calls);
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

    public function testMalformedJournalIsBadRequestAndStoresNothing(): void
    {
        $id = self::JOURNAL_ID;
        $cases = [
            'no journalId' => ['kkt' => ['online' => true]],
            'journalId not a UUID' => ['journalId' => 'sd-card-1', 'journal' => []],
            'journalId with a newline' => ['journalId' => "$id\n", 'journal' => []],
            'object' => ['journalId' => $id, 'journal' => ['seq' => 1]],
            'no seq' => [
                'journalId' => $id,
                'journal' => [['journalId' => $id, 'source' => 'x']],
            ],
            'zero seq' => [
                'journalId' => $id,
                'journal' => [['journalId' => $id, 'seq' => 0]],
            ],
            'scalar entry' => ['journalId' => $id, 'journal' => [1]],
            'entry without journalId' => [
                'journalId' => $id,
                'journal' => [['seq' => 1]],
            ],
            'entry of another journal' => [
                'journalId' => $id,
                'journal' => [['journalId' => self::NEW_JOURNAL_ID, 'seq' => 1]],
            ],
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
                return self::$db->escapeValue((string) $v);
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
            'queue' => ['1romantic' => ['pending' => 0, 'stoppedReason' => null]],
            'journal' => $journal,
        ];
    }

    private function entry(int $seq, string $journalId = self::JOURNAL_ID): array
    {
        return [
            'journalId' => $journalId,
            'seq' => $seq,
            'source' => '1romantic',
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
