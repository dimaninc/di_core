<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08/11/2018
 * Time: 18:30
 */

namespace diCore\Controller;

use diCore\Base\Exception\HttpException;
use diCore\Data\Config;
use diCore\Data\Types;
use diCore\Database\Connection;
use diCore\Entity\PaymentReceipt\Collection;
use diCore\Entity\PaymentReceipt\Model;
use diCore\Tool\CollectionCache;
use diCore\Tool\Logger;

/**
 * Two protocols with the cash desk. v1 (`get_new_receipts`, `set_receipt_uploaded`)
 * is kept as is for a rollback to the old cash desk app. v2 (`pending_receipts`,
 * `receipt_fiscalized`, `heartbeat`): JSON bodies, `Authorization: Bearer`, money in
 * kopecks, idempotent reporting – see CLAUDE.md, "Cash desk contract v2".
 */
class Cash extends \diBaseController
{
    const secret = null;
    const maxReceiptsAtOnce = null;
    // the queue window for both protocols: older unreported receipts are never served
    const dates = '-1 month';

    const CASHDESK_TOKEN_ENV = 'CASHDESK_TOKEN';
    const PENDING_LIMIT_DEFAULT = 10;
    const PENDING_LIMIT_MAX = 50;
    // a broken receipt is skipped, not served: a page reads this many rows over the limit
    const PENDING_SCAN_EXTRA = 50;
    // pages go on past a run of broken receipts, but one poll reads at most this many rows
    const PENDING_SCAN_MAX = 500;
    const JOURNAL_MAX_ENTRIES = 500;
    // quarantined ids a cash desk may ask to skip
    const PENDING_EXCLUDE_MAX = 500;
    const BODY_MAX_BYTES = 1048576;
    // fiscal_total, fiscal_session, fiscal_number are signed INT in MySQL
    const INT_COLUMN_MAX = 2147483647;
    // a fiscal document number is uint32
    const DOCUMENT_NUMBER_MAX = 4294967295;
    // FPD (tag 1077) is uint32 too
    const FISCAL_SIGN_MAX = 4294967295;
    const BROKEN_RECEIPT_REPORT_INTERVAL_SEC = 3600;
    const HEARTBEAT_RETENTION_DAYS = 7;
    const HEARTBEAT_TABLE = 'cashdesk_heartbeat';
    const JOURNAL_TABLE = 'cashdesk_journal';
    const LOG_MODULE = 'cashdesk';
    // tag 1030 (item name) of FFD 1.2
    const ITEM_NAME_MAX_LENGTH = 128;
    const RECEIPT_TYPES = ['sell', 'sellReturn'];

    public static function secret()
    {
        return static::secret;
    }

    public static function getSecret()
    {
        return \diRequest::post('secret');
    }

    protected function checkSecret()
    {
        if (static::secret() !== static::getSecret()) {
            throw HttpException::forbidden('Credentials not match');
        }

        return $this;
    }

    public function _postGetNewReceiptsAction()
    {
        $receiptsCol = $this->checkSecret()->getReceipts();

        return [
            'receipts' => $this->processReceipts($receiptsCol),
        ];
    }

    public function _postSetReceiptUploadedAction()
    {
        $receiptId = \diRequest::rawPost('id', 0);
        $fiscal = \diRequest::rawPost('lastReceipt', []);

        return $this->checkSecret()->setReceiptUploaded($receiptId, $fiscal);
    }

    protected function getMaxReceiptsCount()
    {
        return \diRequest::post('limit', 0) ?: static::maxReceiptsAtOnce;
    }

    protected function processReceipts(Collection $receipts)
    {
        CollectionCache::addManual(Types::user, 'id', $receipts->map('user_id'));

        $ar = $receipts->map(function (Model $r) {
            return $r->asArrayForCashDesk();
        });

        return $ar;
    }

    /**
     * @return Collection
     * @throws \Exception
     */
    protected function getReceipts()
    {
        // A recorded document excludes the receipt as in v2: after a rollback the old
        // app would otherwise print what v2 fiscalized but didn't mark as reported
        $receipts = Collection::create()
            ->filterByDatePayed(\diDateTime::sqlFormat(static::dates), '>=')
            ->filterByDateUploaded(null)
            ->filterManual(static::noFiscalDocumentCondition());

        if ($this->getMaxReceiptsCount()) {
            $receipts->setPageSize($this->getMaxReceiptsCount());
        }

        return $receipts;
    }

    protected static function useFiscalMarkFromCashDesk()
    {
        return true;
    }

    protected function setReceiptUploaded($receiptId, $fiscal = [])
    {
        $receipt = Model::createById($receiptId);

        if (!$receipt->exists()) {
            throw HttpException::notFound("Receipt ID=$receipt not found");
        }

        if (!empty($fiscal['docId'])) {
            $receipt->setFiscalDocId($fiscal['docId']);
        }

        if (!empty($fiscal['mark']) && static::useFiscalMarkFromCashDesk()) {
            $receipt->setFiscalMark($fiscal['mark']);
        }

        if (!empty($fiscal['date'])) {
            $receipt->setFiscalDate(\diDateTime::sqlFormat($fiscal['date']));
        }

        if (!empty($fiscal['session'])) {
            $receipt->setFiscalSession($fiscal['session']);
        }

        if (!empty($fiscal['number'])) {
            $receipt->setFiscalNumber($fiscal['number']);
        }

        $receipt->setDateUploaded(\diDateTime::sqlFormat())->save();

        return [
            'ok' => true,
            'id' => $receiptId,
        ];
    }

    /* ---------------------------------------------------------------------------
     * Contract v2
     * ------------------------------------------------------------------------- */

    public function _postPendingReceiptsAction(): array
    {
        $body = $this->authorizedCashDeskBody();
        $limit = $this->pendingLimit($body);
        $exclude = $this->pendingExclude($body);
        $paymentsPlace = $this->cashDeskPaymentsPlace();

        $receipts = [];
        $scanned = 0;
        $after = null;

        while (count($receipts) < $limit && $scanned < static::PENDING_SCAN_MAX) {
            $pageSize = min(
                $limit + static::PENDING_SCAN_EXTRA,
                static::PENDING_SCAN_MAX - $scanned
            );
            $rows = static::fetchPendingReceiptRows($pageSize, $exclude, $after);
            $scanned += count($rows);
            $models = array_map([$this, 'createCashDeskReceipt'], $rows);
            $this->preloadCashDeskRelations($models);

            foreach ($models as $receipt) {
                try {
                    $data = $receipt->asArrayForCashDeskV2($paymentsPlace);
                    $problem = $this->cashDeskReceiptProblem($data);
                } catch (\Throwable $e) {
                    $problem = get_class($e) . ': ' . $e->getMessage();
                }

                if ($problem !== null) {
                    $this->onBrokenReceipt($receipt, $problem);

                    continue;
                }

                $receipts[] = $data;

                if (count($receipts) >= $limit) {
                    break 2;
                }
            }

            if (count($rows) < $pageSize) {
                break;
            }

            $last = end($rows);
            $after = [(string) $last['date_payed'], (int) $last['id']];
        }

        return [
            'receipts' => $receipts,
        ];
    }

    /**
     * Idempotent: the guard lives in the UPDATE itself and the decision is made by
     * the number of changed rows, so two parallel reports can't both "win".
     */
    public function _postReceiptFiscalizedAction(): array
    {
        $body = $this->authorizedCashDeskBody();
        $receiptId = static::positiveInt($body['id'] ?? null);

        if ($receiptId === null) {
            throw static::badCashDeskRequest('id must be a positive integer');
        }

        $fiscal = $this->parseFiscal($body['fiscal'] ?? null);
        $row = static::readReceiptRow($receiptId);

        if (!$row) {
            throw HttpException::notFound(['error' => 'not_found']);
        }

        if (!$this->writeFiscalData($receiptId, $fiscal)) {
            // Lost to an earlier write: read what it stored
            $row = static::readReceiptRow($receiptId);

            if (!$row) {
                throw HttpException::notFound(['error' => 'not_found']);
            }

            $stored = (string) ($row['fiscal_doc_id'] ?? '');

            if (static::isSameFiscalDocument($row, $fiscal)) {
                if (empty($row['date_uploaded'])) {
                    $this->markSameDocumentReported($receiptId, $row, $fiscal);
                }

                return ['ok' => true];
            }

            $this->onDoubleFiscalization($receiptId, $stored, $fiscal);

            throw HttpException::conflict([
                'error' => 'conflict',
                'documentNumber' => ctype_digit($stored) ? (int) $stored : null,
            ]);
        }

        $expected = Model::toKopecks($row['amount'] ?? 0);

        if ($expected !== $fiscal['total']) {
            $this->onFiscalTotalMismatch(
                $receiptId,
                $expected,
                $fiscal['total'],
                $fiscal
            );
        }

        return ['ok' => true];
    }

    public function _postHeartbeatAction(): array
    {
        $body = $this->authorizedCashDeskBody();
        $journalId = static::journalId($body['journalId'] ?? null);

        if ($journalId === null) {
            throw static::badCashDeskRequest('journalId must be a UUID');
        }

        $entries = $this->parseJournal($body['journal'] ?? [], $journalId);
        $now = \diDateTime::sqlFormat();

        // The journal first: it is the copy worth keeping, the status is overwritten next minute
        $ack = $this->storeJournal($journalId, $entries, $now);

        $status = $body;
        unset($status['journal']);
        $this->storeHeartbeat($status, $now);

        return [
            'ok' => true,
            'journalAckSeq' => $ack,
        ];
    }

    /* --- watchdog queries ---------------------------------------------------- */

    /**
     * @return array{receivedAt: string, ageSeconds: int, payload: array}|null null – never received
     */
    public static function lastHeartbeat(): ?array
    {
        $db = static::cashDeskDb();
        $row =
            static::fetchRows(
                sprintf(
                    'SELECT payload, received_at FROM %s ORDER BY received_at DESC, id DESC%s',
                    $db->escapeTable(static::heartbeatTable()),
                    $db->limitOffset(1)
                )
            )[0] ?? null;

        if (!$row) {
            return null;
        }

        $receivedAt = (string) $row['received_at'];
        $payload = json_decode((string) $row['payload'], true);

        return [
            'receivedAt' => $receivedAt,
            'ageSeconds' => max(0, time() - (int) strtotime($receivedAt)),
            'payload' => is_array($payload) ? $payload : [],
        ];
    }

    public static function lastHeartbeatAgeSeconds(): ?int
    {
        $last = static::lastHeartbeat();

        return $last ? $last['ageSeconds'] : null;
    }

    /**
     * Receipts the cash desk should have taken but hasn't reported for this long,
     * within the same window the queue serves – oldest first.
     *
     * @return int[]
     */
    public static function overdueReceiptIds(
        int $olderThanMinutes,
        int $limit = 100
    ): array {
        $db = static::cashDeskDb();
        $rows = static::fetchRows(
            sprintf(
                'SELECT id FROM %s WHERE %s AND date_payed <= %s ORDER BY date_payed ASC, id ASC%s',
                $db->escapeTable(static::receiptsTable()),
                static::pendingReceiptsCondition(),
                $db->escapeValue(
                    \diDateTime::sqlFormat(
                        '-' . max(0, $olderThanMinutes) . ' minutes'
                    )
                ),
                $db->limitOffset(max(1, $limit))
            )
        );

        return array_map(function (array $row) {
            return (int) $row['id'];
        }, $rows);
    }

    /* --- overridable settings ------------------------------------------------ */

    /**
     * Empty means "not configured" and refuses every request.
     */
    protected function cashDeskToken(): string
    {
        $token = \diRequest::env(static::CASHDESK_TOKEN_ENV);

        if (!$token) {
            $token = getenv(static::CASHDESK_TOKEN_ENV);
        }

        return is_string($token) ? trim($token) : '';
    }

    /**
     * Tag 1187: the site the payment was made on.
     */
    protected function cashDeskPaymentsPlace(): string
    {
        return Config::getMainProtocol() . Config::getMainDomain();
    }

    protected static function receiptsTable(): string
    {
        return Model::table;
    }

    protected static function heartbeatTable(): string
    {
        return static::HEARTBEAT_TABLE;
    }

    protected static function journalTable(): string
    {
        return static::JOURNAL_TABLE;
    }

    protected static function pendingReceiptsNotBefore(): string
    {
        return \diDateTime::sqlFormat(static::dates);
    }

    protected static function cashDeskDb(): \diDB
    {
        return Connection::get()->getDb();
    }

    protected function cashDeskRequestBody(): string
    {
        return (string) \diRequest::rawPost();
    }

    /**
     * Declared size, checked before the body is read; null when not sent.
     */
    protected function cashDeskRequestLength(): ?int
    {
        $length = \diRequest::server('CONTENT_LENGTH');

        return is_numeric($length) ? (int) $length : null;
    }

    /**
     * Apache with mod_php drops `Authorization` from `$_SERVER` unless a rewrite
     * rule or `CGIPassAuth On` passes it: getallheaders() still sees it there.
     */
    protected function cashDeskAuthorizationHeader(): string
    {
        $header =
            \diRequest::server('HTTP_AUTHORIZATION') ?:
            \diRequest::server('REDIRECT_HTTP_AUTHORIZATION');

        if (!$header && function_exists('getallheaders')) {
            foreach ((array) getallheaders() as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    $header = $value;

                    break;
                }
            }
        }

        return is_string($header) ? $header : '';
    }

    /**
     * Where the default onBrokenReceipt() and onMissingCashDeskToken() keep their
     * hourly markers (one per receipt id, one for the token).
     */
    protected static function brokenReceiptMarkerFolder(): string
    {
        return static::reportMarkerFolderIn(
            (string) Config::getCacheFolder(),
            sys_get_temp_dir()
        );
    }

    /**
     * An empty cache folder would put markers into the cwd (under cron – home or /),
     * so without a usable one they go to the temp folder, one per installation.
     */
    protected static function reportMarkerFolderIn(
        string $cacheFolder,
        string $tempFolder
    ): string {
        if (
            $cacheFolder !== '' &&
            is_dir($cacheFolder) &&
            is_writable($cacheFolder)
        ) {
            return rtrim($cacheFolder, '/') . '/cashdesk/broken-receipts/';
        }

        return rtrim($tempFolder, '/') .
            '/di_core-cashdesk-' .
            substr(md5(__DIR__), 0, 12) .
            '/broken-receipts/';
    }

    protected function createCashDeskReceipt(array $row): Model
    {
        return Model::create(\diTypes::payment_receipt, $row);
    }

    /**
     * One query per related type instead of one per receipt: the cash desk polls
     * every few seconds. What to load is the receipt's `cashDeskRelatedIds()`.
     *
     * @param Model[] $receipts
     */
    protected function preloadCashDeskRelations(array $receipts): void
    {
        $byType = [];

        foreach ($receipts as $receipt) {
            foreach ($receipt->cashDeskRelatedIds() as [$type, $id]) {
                if ($type && $id) {
                    $byType[$type][$id] = $id;
                }
            }
        }

        foreach ($byType as $type => $ids) {
            try {
                CollectionCache::addManual($type, 'id', array_values($ids));
            } catch (\Throwable $e) {
                // not preloaded is only slower: each receipt then loads its own model
            }
        }
    }

    /* --- hooks: a project sends these to its monitoring ----------------------- */

    /**
     * Another document is already recorded for the receipt: the buyer was charged
     * one payment and got two fiscal documents.
     */
    protected function onDoubleFiscalization(
        int $receiptId,
        string $storedDocumentNumber,
        array $fiscal
    ): void {
        $this->reportCashDeskProblem(
            "Receipt #$receiptId fiscalized twice: stored document " .
                ($storedDocumentNumber !== '' ? $storedDocumentNumber : '(none)') .
                ", reported {$fiscal['documentNumber']} (FN {$fiscal['fnNumber']})"
        );
    }

    protected function onFiscalTotalMismatch(
        int $receiptId,
        int $expectedKopecks,
        int $fiscalKopecks,
        array $fiscal
    ): void {
        $this->reportCashDeskProblem(
            "Receipt #$receiptId: fiscal total $fiscalKopecks != payment $expectedKopecks kopecks " .
                "(document {$fiscal['documentNumber']})"
        );
    }

    /**
     * Called on every poll while the receipt stays broken. The default reports a
     * receipt at most once per BROKEN_RECEIPT_REPORT_INTERVAL_SEC (a file marker),
     * or a poll a minute floods the log; an override decides on its own.
     */
    protected function onBrokenReceipt(Model $receipt, string $problem): void
    {
        $id = (int) $receipt->getId();

        if ($this->claimHourlyReport((string) $id)) {
            $this->reportCashDeskProblem(
                "Receipt #$id not served to the cash desk: $problem"
            );
        }
    }

    /**
     * No token configured: every cash desk request is refused. The default reports
     * at most once an hour, like onBrokenReceipt(); an override decides on its own.
     */
    protected function onMissingCashDeskToken(): void
    {
        if ($this->claimHourlyReport('missing-token')) {
            $this->reportCashDeskProblem(
                static::CASHDESK_TOKEN_ENV .
                    ' is not set: cash desk requests are refused'
            );
        }
    }

    /**
     * True when the marker is missing or older than an hour (and is renewed now).
     * A marker that can't be written means reporting every time, never silence.
     */
    protected function claimHourlyReport(string $name): bool
    {
        $folder = static::brokenReceiptMarkerFolder();
        $marker = $folder . $name;
        $mtime = is_file($marker) ? filemtime($marker) : false;

        if (
            $mtime !== false &&
            time() - $mtime < static::BROKEN_RECEIPT_REPORT_INTERVAL_SEC
        ) {
            return false;
        }

        if (is_dir($folder) || @mkdir($folder, 0775, true)) {
            @touch($marker);
            $this->removeStaleReportMarkers($folder);
        }

        return true;
    }

    /**
     * A marker not renewed for the whole queue window belongs to a receipt that is
     * never served again (or was fixed): without this the folder only grows.
     */
    protected function removeStaleReportMarkers(string $folder): void
    {
        $staleBefore = strtotime(static::dates);

        foreach (glob($folder . '*') ?: [] as $file) {
            $mtime = @filemtime($file);

            if ($mtime !== false && $mtime < $staleBefore) {
                @unlink($file);
            }
        }
    }

    /**
     * A seq of the same journal already stored with other contents: the cash desk
     * rewrote its own history (a new journal comes with a new journalId).
     */
    protected function onJournalConflict(
        string $journalId,
        int $seq,
        array $stored,
        array $incoming
    ): void {
        $this->reportCashDeskProblem(
            "Cash desk journal $journalId seq $seq already stored with other contents: " .
                json_encode($stored, JSON_UNESCAPED_UNICODE) .
                ' vs ' .
                json_encode($incoming, JSON_UNESCAPED_UNICODE)
        );
    }

    protected function reportCashDeskProblem(string $text): void
    {
        Logger::getInstance()->log($text, static::LOG_MODULE);

        // Skipped only where PHP would print it into the JSON answer
        if (\diRequest::isCli() || !ini_get('display_errors')) {
            trigger_error($text, E_USER_WARNING);
        }
    }

    /* --- internals ------------------------------------------------------------ */

    /**
     * Authorization first, so a request without a token learns nothing about the
     * body it sent.
     */
    protected function authorizedCashDeskBody(): array
    {
        $this->assertCashDeskAuthorized();

        $length = $this->cashDeskRequestLength();

        if ($length !== null && $length > static::BODY_MAX_BYTES) {
            throw static::tooLargeCashDeskRequest();
        }

        $raw = $this->cashDeskRequestBody();

        // Content-Length may be absent (chunked) or lie
        if (strlen($raw) > static::BODY_MAX_BYTES) {
            throw static::tooLargeCashDeskRequest();
        }

        $raw = trim($raw);

        if ($raw === '') {
            return [];
        }

        $body = json_decode($raw, true);

        // a JSON list decodes to an array too, but it is not the object the contract asks for
        if (!is_array($body) || $raw[0] !== '{') {
            throw static::badCashDeskRequest('body must be a JSON object');
        }

        return $body;
    }

    protected function assertCashDeskAuthorized(): void
    {
        $expected = $this->cashDeskToken();

        if ($expected === '') {
            $this->onMissingCashDeskToken();

            throw HttpException::unauthorized(['error' => 'unauthorized']);
        }

        $given = preg_match(
            '/^Bearer\s+(\S+)\s*$/i',
            $this->cashDeskAuthorizationHeader(),
            $m
        )
            ? $m[1]
            : '';

        if ($given === '' || !hash_equals($expected, $given)) {
            throw HttpException::unauthorized(['error' => 'unauthorized']);
        }
    }

    protected function pendingLimit(array $body): int
    {
        if (!isset($body['limit'])) {
            return static::PENDING_LIMIT_DEFAULT;
        }

        $limit = static::positiveInt($body['limit']);

        if ($limit === null) {
            throw static::badCashDeskRequest('limit must be a positive integer');
        }

        return min($limit, static::PENDING_LIMIT_MAX);
    }

    /**
     * Ids the cash desk holds in quarantine: serving them again only fills its
     * batch with receipts it won't print.
     *
     * @return int[]
     */
    protected function pendingExclude(array $body): array
    {
        if (!isset($body['exclude'])) {
            return [];
        }

        $exclude = $body['exclude'];

        if (
            !is_array($exclude) ||
            !array_is_list($exclude) ||
            count($exclude) > static::PENDING_EXCLUDE_MAX
        ) {
            throw static::badCashDeskRequest(
                'exclude must be a list of at most ' .
                    static::PENDING_EXCLUDE_MAX .
                    ' positive integers'
            );
        }

        $ids = [];

        foreach ($exclude as $value) {
            $id = static::positiveInt($value);

            if ($id === null) {
                throw static::badCashDeskRequest(
                    'exclude must be a list of positive integers'
                );
            }

            $ids[$id] = $id;
        }

        return array_values($ids);
    }

    /**
     * Unreported (`date_uploaded`) and without a fiscal document within the queue
     * window. A recorded document excludes a receipt even without `date_uploaded`:
     * serving it again would print it twice.
     */
    protected static function pendingReceiptsCondition(): string
    {
        $db = static::cashDeskDb();

        return 'date_uploaded IS NULL AND ' .
            static::noFiscalDocumentCondition() .
            ' AND date_payed >= ' .
            $db->escapeValue(static::pendingReceiptsNotBefore());
    }

    protected static function noFiscalDocumentCondition(): string
    {
        return "(fiscal_doc_id IS NULL OR fiscal_doc_id = '')";
    }

    /**
     * @param int[] $exclude validated positive ints
     * @param array{0: string, 1: int}|null $after keyset of the previous page's last row: (date_payed, id)
     */
    protected static function fetchPendingReceiptRows(
        int $limit,
        array $exclude = [],
        ?array $after = null
    ): array {
        $db = static::cashDeskDb();
        $notIn = $exclude
            ? ' AND id NOT IN (' . implode(', ', array_map('intval', $exclude)) . ')'
            : '';

        if ($after !== null) {
            $date = $db->escapeValue($after[0]);
            $notIn .= sprintf(
                ' AND (date_payed > %s OR (date_payed = %s AND id > %d))',
                $date,
                $date,
                $after[1]
            );
        }

        return static::fetchRows(
            sprintf(
                'SELECT * FROM %s WHERE %s%s ORDER BY date_payed ASC, id ASC%s',
                $db->escapeTable(static::receiptsTable()),
                static::pendingReceiptsCondition(),
                $notIn,
                $db->limitOffset($limit)
            )
        );
    }

    protected static function readReceiptRow(int $receiptId): ?array
    {
        $db = static::cashDeskDb();

        return static::fetchRows(
            sprintf(
                'SELECT id, amount, fiscal_doc_id, fiscal_fn_number, date_uploaded FROM %s WHERE id = %d',
                $db->escapeTable(static::receiptsTable()),
                $receiptId
            )
        )[0] ?? null;
    }

    /**
     * A failed read throws: "couldn't read" must not look like "nothing there".
     */
    protected static function fetchRows(string $sql): array
    {
        $db = static::cashDeskDb();
        $rs = $db->q($sql);

        if ($rs === false) {
            throw new \diDatabaseException("Cash desk query failed: $sql");
        }

        $rows = [];

        while ($row = $db->fetch_array($rs)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Writes only into a receipt with no document and no report yet. A receipt
     * reported without a document (v1, by hand) counts as fiscalized too: a new
     * document for it is a double one, not a first one.
     */
    protected function writeFiscalData(int $receiptId, array $fiscal): bool
    {
        $db = static::cashDeskDb();

        $values = [
            'fiscal_doc_id' => (string) $fiscal['documentNumber'],
            'fiscal_mark_kkt' => $fiscal['sign'],
            'fiscal_date' => $fiscal['dateTimeSql'],
            'fiscal_session' => $fiscal['shiftNumber'],
            'fiscal_number' => $fiscal['receiptNumber'],
            'fiscal_fn_number' => $fiscal['fnNumber'],
            'fiscal_kkt_reg_number' => $fiscal['registrationNumber'],
            'fiscal_total' => $fiscal['total'],
            'date_uploaded' => \diDateTime::sqlFormat(),
        ];

        if (static::useFiscalMarkFromCashDesk()) {
            $values['fiscal_mark'] = $fiscal['sign'];
        }

        $set = [];

        foreach ($values as $field => $value) {
            $set[] =
                "$field = " . (is_int($value) ? $value : $db->escapeValue($value));
        }

        return $db->execWrite(
            sprintf(
                'UPDATE %s SET %s WHERE id = %d AND %s AND date_uploaded IS NULL',
                $db->escapeTable(static::receiptsTable()),
                implode(', ', $set),
                $receiptId,
                static::noFiscalDocumentCondition()
            )
        ) > 0;
    }

    /**
     * Numbering restarts on a new fiscal drive, so a stored drive number has to
     * match too; a row without one (v1, OFD mail) is compared by the number alone.
     */
    protected static function isSameFiscalDocument(array $row, array $fiscal): bool
    {
        if (
            (string) ($row['fiscal_doc_id'] ?? '') !==
            (string) $fiscal['documentNumber']
        ) {
            return false;
        }

        $storedFn = trim((string) ($row['fiscal_fn_number'] ?? ''));

        return $storedFn === '' || $storedFn === $fiscal['fnNumber'];
    }

    /**
     * The same document recorded without a report (by hand, OFD mail): mark it
     * reported, or it stays overdue forever, and fill in what the record lacks.
     * A filled column is kept – the OFD mail parser may have written it. Guarded by
     * that document, so a lost race reports nothing.
     */
    protected function markSameDocumentReported(
        int $receiptId,
        array $row,
        array $fiscal
    ): void {
        $db = static::cashDeskDb();
        $string = "(%1\$s IS NULL OR %1\$s = '')";
        $number = '(%1$s IS NULL OR %1$s = 0)';
        $null = '%1$s IS NULL';

        $fill = [
            'fiscal_fn_number' => [$fiscal['fnNumber'], $string],
            'fiscal_kkt_reg_number' => [$fiscal['registrationNumber'], $string],
            'fiscal_mark_kkt' => [$fiscal['sign'], $string],
            'fiscal_total' => [$fiscal['total'], $null],
            'fiscal_date' => [$fiscal['dateTimeSql'], $null],
            'fiscal_session' => [$fiscal['shiftNumber'], $number],
            'fiscal_number' => [$fiscal['receiptNumber'], $number],
        ];

        if (static::useFiscalMarkFromCashDesk()) {
            $fill['fiscal_mark'] = [$fiscal['sign'], $string];
        }

        $set = ['date_uploaded = ' . $db->escapeValue(\diDateTime::sqlFormat())];

        foreach ($fill as $field => [$value, $empty]) {
            $set[] = sprintf(
                '%1$s = CASE WHEN %2$s THEN %3$s ELSE %1$s END',
                $field,
                sprintf($empty, $field),
                is_int($value) ? $value : $db->escapeValue($value)
            );
        }

        $changed = $db->execWrite(
            sprintf(
                'UPDATE %s SET %s WHERE id = %d AND fiscal_doc_id = %s AND date_uploaded IS NULL',
                $db->escapeTable(static::receiptsTable()),
                implode(', ', $set),
                $receiptId,
                $db->escapeValue((string) $row['fiscal_doc_id'])
            )
        );

        $expected = Model::toKopecks($row['amount'] ?? 0);

        if ($changed > 0 && $expected !== $fiscal['total']) {
            $this->onFiscalTotalMismatch(
                $receiptId,
                $expected,
                $fiscal['total'],
                $fiscal
            );
        }
    }

    /**
     * @param mixed $fiscal
     */
    protected function parseFiscal($fiscal): array
    {
        if (!is_array($fiscal)) {
            throw static::badCashDeskRequest('fiscal must be an object');
        }

        $parsed = [
            'documentNumber' => static::boundedInt(
                $fiscal['documentNumber'] ?? null,
                1,
                static::DOCUMENT_NUMBER_MAX
            ),
            'sign' => static::digitsUpTo(
                $fiscal['sign'] ?? null,
                10,
                static::FISCAL_SIGN_MAX
            ),
            'dateTimeSql' => static::isoToSql($fiscal['dateTime'] ?? null),
            'shiftNumber' => static::boundedInt(
                $fiscal['shiftNumber'] ?? null,
                1,
                static::INT_COLUMN_MAX
            ),
            'receiptNumber' => static::boundedInt(
                $fiscal['receiptNumber'] ?? null,
                1,
                static::INT_COLUMN_MAX
            ),
            'fnNumber' => static::digits($fiscal['fnNumber'] ?? null, 20),
            'registrationNumber' => static::digits(
                $fiscal['registrationNumber'] ?? null,
                20
            ),
            'total' => static::boundedInt(
                $fiscal['total'] ?? null,
                0,
                static::INT_COLUMN_MAX
            ),
        ];

        foreach ($parsed as $field => $value) {
            if ($value === null) {
                $name = $field === 'dateTimeSql' ? 'dateTime' : $field;

                throw static::badCashDeskRequest(
                    "fiscal.$name is missing or malformed"
                );
            }
        }

        return $parsed;
    }

    /**
     * @param mixed $journal
     * @return array[] valid entries ordered by seq, at most JOURNAL_MAX_ENTRIES
     */
    protected function parseJournal($journal, string $journalId): array
    {
        if (!is_array($journal) || !array_is_list($journal)) {
            throw static::badCashDeskRequest('journal must be an array');
        }

        foreach ($journal as $entry) {
            if (
                !is_array($entry) ||
                static::positiveInt($entry['seq'] ?? null) === null
            ) {
                throw static::badCashDeskRequest(
                    'every journal entry needs a positive seq'
                );
            }

            // The ack covers one journal: an entry of another would be resent forever
            if (static::journalId($entry['journalId'] ?? null) !== $journalId) {
                throw static::badCashDeskRequest(
                    'every journal entry needs the journalId of the heartbeat'
                );
            }
        }

        usort($journal, function (array $a, array $b) {
            return (int) $a['seq'] <=> (int) $b['seq'];
        });

        // The rest comes in the next heartbeat: the answer acks only what was stored
        return array_slice($journal, 0, static::JOURNAL_MAX_ENTRIES);
    }

    /**
     * @return int|null the highest stored seq of this request, null when it had none
     */
    protected function storeJournal(
        string $journalId,
        array $entries,
        string $now
    ): ?int {
        $db = static::cashDeskDb();
        $table = static::journalTable();
        $ack = null;

        foreach ($entries as $entry) {
            $seq = (int) static::positiveInt($entry['seq']);
            $receiptId = static::positiveInt($entry['receiptId'] ?? null);
            $documentNumber = static::positiveInt($entry['documentNumber'] ?? null);

            $id = $db->insertIgnore($table, [
                'journal_id' => $journalId,
                'seq' => $seq,
                'source' => $db->escape_string(
                    static::shortString($entry['source'] ?? '')
                ),
                'receipt_id' => $receiptId,
                'state' => $db->escape_string(
                    static::shortString($entry['state'] ?? '')
                ),
                'document_number' => $documentNumber,
                'event_at' => static::isoToSql($entry['at'] ?? null),
                'payload' => $entry,
                'received_at' => $now,
            ]);

            if ($id === false) {
                throw new \diDatabaseException(
                    "Unable to store cash desk journal seq $seq"
                );
            }

            if (!$id) {
                $this->checkJournalDuplicate($journalId, $seq, $entry);
            }

            $ack = $seq;
        }

        return $ack;
    }

    protected function checkJournalDuplicate(
        string $journalId,
        int $seq,
        array $entry
    ): void {
        $db = static::cashDeskDb();
        $row =
            static::fetchRows(
                sprintf(
                    'SELECT payload FROM %s WHERE journal_id = %s AND seq = %d',
                    $db->escapeTable(static::journalTable()),
                    $db->escapeValue($journalId),
                    $seq
                )
            )[0] ?? null;

        if (!$row) {
            throw new \diDatabaseException(
                "Cash desk journal $journalId seq $seq neither stored nor found"
            );
        }

        $stored = json_decode((string) $row['payload'], true);

        // == on purpose: the database may reorder JSON keys
        if (!is_array($stored) || $stored != $entry) {
            $this->onJournalConflict(
                $journalId,
                $seq,
                is_array($stored) ? $stored : [],
                $entry
            );
        }
    }

    protected function storeHeartbeat(array $status, string $now): void
    {
        $db = static::cashDeskDb();
        $table = static::heartbeatTable();

        if (
            $db->insert($table, [
                'payload' => $status,
                'received_at' => $now,
            ]) === false
        ) {
            throw new \diDatabaseException('Unable to store cash desk heartbeat');
        }

        $db->execWrite(
            sprintf(
                'DELETE FROM %s WHERE received_at < %s',
                $db->escapeTable($table),
                $db->escapeValue(
                    \diDateTime::sqlFormat(
                        '-' . static::HEARTBEAT_RETENTION_DAYS . ' days'
                    )
                )
            )
        );
    }

    /**
     * Every rule the cash desk relies on: a receipt failing any of them is not served.
     */
    protected function cashDeskReceiptProblem(array $r): ?string
    {
        $total = $r['total'] ?? null;

        if (!is_int($total) || $total <= 0) {
            return 'total is not a positive number of kopecks';
        }

        $type = $r['type'] ?? null;

        if (!in_array($type, static::RECEIPT_TYPES, true)) {
            return 'unknown type ' . json_encode($type);
        }

        if (
            $type === 'sellReturn' &&
            static::positiveInt($r['originalId'] ?? null) === null
        ) {
            return 'a return without originalId';
        }

        if (!is_string($r['paymentsPlace'] ?? null) || $r['paymentsPlace'] === '') {
            return 'empty paymentsPlace';
        }

        if (
            isset($r['contact']) &&
            (!is_string($r['contact']) || $r['contact'] === '')
        ) {
            return 'contact is neither a string nor null';
        }

        $items = $r['items'] ?? null;

        if (!is_array($items) || !$items) {
            return 'no items';
        }

        $itemsSum = 0;

        foreach ($items as $i => $item) {
            $name = $item['name'] ?? null;

            if (!is_string($name) || trim($name) === '') {
                return "item $i has no name";
            }

            if (mb_strlen($name) > static::ITEM_NAME_MAX_LENGTH) {
                return "item $i name is longer than " . static::ITEM_NAME_MAX_LENGTH;
            }

            $price = $item['price'] ?? null;
            $quantity = $item['quantity'] ?? null;
            $sum = $item['sum'] ?? null;

            if (
                !is_int($price) ||
                $price < 0 ||
                !is_int($quantity) ||
                $quantity <= 0 ||
                !is_int($sum) ||
                $price * $quantity !== $sum
            ) {
                return "item $i: price × quantity != sum";
            }

            if (
                !is_string($item['paymentObject'] ?? null) ||
                $item['paymentObject'] === '' ||
                !is_string($item['paymentMethod'] ?? null) ||
                $item['paymentMethod'] === ''
            ) {
                return "item $i has no paymentObject/paymentMethod";
            }

            $itemsSum += $sum;
        }

        if ($itemsSum !== $total) {
            return "items sum $itemsSum != total $total";
        }

        $payments = $r['payments'] ?? null;

        if (!is_array($payments) || !$payments) {
            return 'no payments';
        }

        $paymentsSum = 0;

        foreach ($payments as $i => $payment) {
            $sum = $payment['sum'] ?? null;

            if (!is_string($payment['type'] ?? null) || $payment['type'] === '') {
                return "payment $i has no type";
            }

            if (!is_int($sum) || $sum <= 0) {
                return "payment $i sum is not a positive number of kopecks";
            }

            $paymentsSum += $sum;
        }

        if ($paymentsSum !== $total) {
            return "payments sum $paymentsSum != total $total";
        }

        return null;
    }

    protected static function tooLargeCashDeskRequest(): HttpException
    {
        return HttpException::fastCreate(413, [
            'error' => 'too_large',
            'message' => 'body must not exceed ' . static::BODY_MAX_BYTES . ' bytes',
        ]);
    }

    protected static function badCashDeskRequest(string $message): HttpException
    {
        return HttpException::badRequest([
            'error' => 'bad_request',
            'message' => $message,
        ]);
    }

    /**
     * A JSON integer or a digit string; anything else (floats, "12abc", true) is null.
     *
     * @param mixed $value
     */
    protected static function positiveInt($value): ?int
    {
        $int = static::nonNegativeInt($value);

        return $int !== null && $int > 0 ? $int : null;
    }

    /**
     * @param mixed $value
     */
    protected static function nonNegativeInt($value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (is_string($value) && preg_match('/^\d{1,18}\z/', $value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    protected static function boundedInt($value, int $min, int $max): ?int
    {
        $int = static::nonNegativeInt($value);

        return $int !== null && $int >= $min && $int <= $max ? $int : null;
    }

    /**
     * A string of digits, never a JSON number: a number loses leading zeros, and a
     * 16-digit FN number is past 2^53 in JavaScript.
     *
     * @param mixed $value
     */
    protected static function digits($value, int $maxLength): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return preg_match('/^\d{1,' . $maxLength . '}\z/', $value) ? $value : null;
    }

    /**
     * @param mixed $value
     */
    protected static function digitsUpTo($value, int $maxLength, int $max): ?string
    {
        $digits = static::digits($value, $maxLength);

        return $digits !== null && (int) $digits <= $max ? $digits : null;
    }

    /**
     * A UUID in lower case (one journal must not split into two by letter case).
     *
     * @param mixed $value
     */
    protected static function journalId($value): ?string
    {
        return is_string($value) &&
            preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i',
                $value
            )
            ? strtolower($value)
            : null;
    }

    /**
     * ISO 8601 with a zone (`Z` or an offset) into PHP's local SQL time; null if not.
     *
     * @param mixed $value
     */
    protected static function isoToSql($value): ?string
    {
        if (
            !is_string($value) ||
            !preg_match(
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:?\d{2})\z/',
                $value
            )
        ) {
            return null;
        }

        try {
            $date = new \DateTime($value);
        } catch (\Exception $e) {
            return null;
        }

        // DateTime rolls 02-30 over to 03-02 and 24:00 to the next day
        if ($date->format('Y-m-d\TH:i:s') !== substr($value, 0, 19)) {
            return null;
        }

        return $date
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format('Y-m-d H:i:s');
    }

    /**
     * @param mixed $value
     */
    protected static function shortString($value): string
    {
        return is_scalar($value) ? mb_substr((string) $value, 0, 255) : '';
    }
}
