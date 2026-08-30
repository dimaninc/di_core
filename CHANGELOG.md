# Changelog

## 0.5.2

### Tinkoff: SBP payload (`GetQr`)

- **`Payment\Tinkoff\MerchantApi::getQr($args)`** – the missing `GetQr` call,
  next to the other API methods.
- **`Payment\Tinkoff\Helper::getSbpPayload($paymentId): ?string`** – the SBP
  string (`https://qr.nspk.ru/…`) of an already inited payment, from which both
  the QR picture and the bank-app deep link are built. Asks for
  `DataType=PAYLOAD` (`IMAGE` would return an SVG).

It parses the raw JSON body itself instead of reading MerchantApi's
success/error flags, exactly like `getPaymentState()`: those flags key off the
top-level `ErrorCode` and conflate "the request failed" with "this payment has
no QR". And it **never throws** – a transport failure, an empty body,
undecodable JSON, a missing or false `Success`, a `Data` that is not an SBP
payload all return `null` and are logged. SBP is an extra payment option, so the
caller has to be able to fall back to the web form; an exception here would take
the whole checkout down with it.

**What counts as a payload is checked, not assumed** (`Helper::isSbpPayload()`):
that string becomes the QR the payer scans and the link they tap, i.e. it *is*
the payment destination, so "some `https://` string the gateway sent back" is
not a good enough test. It must be printable ASCII (no control characters — they
would also forge log lines), parse as `https://` with no userinfo, and point at
`nspk.ru` or a subdomain of it — override `getSbpPayloadDomains()` to widen.
Anything else switches SBP off for that payment rather than pointing the payer
elsewhere. Bodies and exception messages are run through `sanitizeForLog()`
before being logged: `Token`/`Password` redacted, control characters flattened,
length capped.

#### Behaviour changes in `MerchantApi` (not additive)

- **TLS certificates are verified** (`VERIFY_TLS`, `CURLOPT_SSL_VERIFYPEER` /
  `VERIFYHOST`). The channel carries the request `Token` and brings back the
  URLs the payer is then sent to — `PaymentURL`, the SBP payload — so accepting
  any certificate meant anyone on the path could redirect a payment. If a host
  has no usable CA bundle, payments now fail loudly instead of silently
  accepting an unverified peer; subclass and set `const VERIFY_TLS = false` only
  as a stop-gap.
- **The response parsing knows which method it answers** (`buildQuery()` passes
  the path down; the parsing moved to the overridable `handleResponse()`).
  `PaymentURL` is only read for methods that return one (`PAYMENT_URL_METHODS`,
  i.e. `Init`). Before this, a *successful* `GetState`/`GetQr` on the cached api
  instance nulled the previous `Init`'s `paymentUrl` and left a bogus
  `Tinkoff response missing PaymentURL` in `getError()`. A missing `PaymentURL`
  on `Init` is still an error, unchanged.
- **`PaymentId` and `Status` now describe the response in hand, and are reset
  when it does not carry them.** They used to be left at their previous value —
  and one api instance serves a whole reconciler run, so after a
  `GetState`(payment A) a `GetQr`(payment B) left `$api->status` reporting A's
  status as if it were B's. A stale value read as the current one is worse than
  an obviously absent `null`.

Covered by `php/tests/Payment/TinkoffSbpPayloadTest.php`.

### `Controller\Feedback::afterModelSaved()` – hook between the save and the email

`sendAction()` used to go straight from `$this->getModel()->save()` to
`sendEmailNotification()`, so a project extending the controller had no point at
which to touch the row it had just stored. It now calls `afterModelSaved()` in
between; the return value is ignored.

The default implementation is empty (`return $this;`), so nothing changes for
existing projects. The order is the point: an override can create a neighbouring
record and link the feedback row to it, and the notification then goes out about
the already-linked message. If `save()` throws, the hook is not called at all.

The signature – no parameters, no declared return type – is part of the contract:
an override declares it the same way, and adding a return type in the parent
later would make every existing override incompatible (fatal error on class
load). The model is reached through `$this->getModel()`.

What the hook does **not** get is a transaction around the row. `save()` opens
and commits its own (`\diModel::save()`), and the hook runs after that commit
while still inside `sendAction()`'s `try`. An exception from an override
therefore only changes the answer — `ok=false`/400, or, for a `SpamException`,
`ok=true` with the email skipped — and leaves the feedback row stored. The
client normally resends, which stores it a second time, so an override that can
fail owns its own cleanup or idempotency.

## 0.5.1

### `LocalizationMigration::insertValues()` — keyed by language, not positional

**Breaking for anyone already calling it** (the helper is new in this cycle, so
most consumers are not): the per-token list is now `['ru' => …, 'en' => …]`. A
positional list is refused with an exception rather than silently mapped.

Why: a positional list has to agree with a column order defined in the consumer's
model, in another repository. The day a project inserted a language in the middle
of that list, every later migration wrote each translation into its neighbour's
column — silently, and nothing about the data looked wrong until a visitor read
Spanish where Italian should be.

- **`$strict` (second argument, on by default)** — every language the model has a
  column for must be present, and an unknown one is an error. `false` ships a
  deliberate subset: the remaining columns are written as `''` and the shortfall
  is logged. Refused in both modes: an empty translation list, a nameless token,
  a positional list and a non-scalar value. The whole batch is validated before
  the first row is written, so a bad token cannot leave the migration half-applied.
- **The table and the language set now come from the consumer's model**
  (`getLocalizationModel()` → `Model::create()`, project namespaces first) instead
  of literals. `down()` therefore deletes from that same table, and it escapes the
  token names it deletes by — `diDB::in()` quotes but does not escape, and a token
  name is prose too ("it's").
- **`Entity\Localization\Model::getValueFields()`** derives the value columns
  from `$fieldTypes`, matching `value` and `xx_value` with a two-letter code (an
  unrelated `default_value` is left alone). A consumer with extra languages
  declares them in its own model's `$fieldTypes` and needs no override here.
- The scaffolding template for `_cfg/migrations/localization/` now shows this API.

Covered by `php/tests/Database/LocalizationMigrationInsertValuesTest.php` and
`php/tests/Entity/LocalizationValueFieldsTest.php`.

## 0.5.0

Minor, not patch: `diImage::isHeic()` answers differently for the same file, so
a consumer's upload pipeline can take a branch it did not take before. Everything
else is additive.

### `diImage::isHeic()` no longer asks finfo

It reads the ISO-BMFF `ftyp` brands out of the first bytes instead — major and
compatible both — and rejects `avif`/`avis` before consulting the HEIF list.

Why the change: finfo's magic database differs between systems and versions (the
same file answers differently on macOS and on Ubuntu focal, and the bare `heif`
brand comes back as `application/octet-stream` in places), and the old two-string
comparison against `image/heic` / `image/heif` never covered the **sequence**
brands at all — `hevc`, `msf1` and friends, i.e. exactly the live photos and
bursts a phone produces. An unrecognised HEIF is simply not converted, after
which `getimagesize()` answers 0×0 and a zero-size picture travels on with no
exception and no log line, so the failure was silent.

**What changes for you:** more files are recognised (sequences, and files that
keep `heic`/`mif1` only among the compatible brands), and AVIF is now explicitly
NOT one of them — it is MIAF too and declares `mif1` compatible, so by that brand
alone the two are indistinguishable. If your pipeline relied on AVIF reaching the
HEIC branch, it no longer does.

### `diImage::convertHeicToJpeg()` — a converter chain with a deadline

Was: one `exec()` of one binary, an exception on a non-zero exit. Now:

- **A fallback chain** — the platform CLI (`heif-convert` on Linux, `magick
  convert` on mac), then **ext-imagick** (no `exec()`, so it does not tie up an
  FPM worker; needs a libheif delegate), then the other CLI. It throws only when
  every one has failed, carrying each attempt's reason.
- **A zero exit code is no longer taken as success.** With several top-level
  images in a HEIC (live photo, burst, depth map) both `heif-convert` and
  ImageMagick write `out-1.jpg` instead of `out.jpg` **and still exit 0** — which
  read as a successful conversion with no output. The result is re-read
  (`safeImageSize`, not `filesize`) and the first numbered frame adopted;
  leftovers are cleared between attempts.
- **A hard time budget**, `HEIC_TOTAL_TIMEOUT_SEC` (20 s), shared by the whole
  chain rather than granted per converter. `set_time_limit()` on Unix does not
  count time spent in system calls, so nothing bounded that `exec()` before. The
  in-process ext-imagick step is bounded by its share as
  `Imagick::RESOURCETYPE_TIME` (process-global, restored afterwards).

### New: `diCore\Helper\ProcessHelper`

`ProcessHelper::run($command, $seconds)` → `['code', 'output', 'timedOut']`. Runs
the command through `proc_open`, drains the pipe non-blockingly (a full pipe
buffer would deadlock the child), and sends SIGTERM then SIGKILL at the deadline.
Degrades to plain `exec()` where `proc_open` is disabled.

Three things worth knowing before using it:

- **Pass the command as an ARRAY when the kill has to reach the binary.** A
  string goes through `sh -c`, and only a single command can be `exec`-prefixed
  to replace the shell; a compound one leaves the shell's children behind (the
  call still returns on time).
- **The real ceiling is `$seconds + TERM_GRACE_SEC`** — the pause between SIGTERM
  and SIGKILL is on top of your timeout.
- **Output is one stream, capped at `MAX_OUTPUT_BYTES` (16 KB), tail kept.**
  stderr is merged into stdout by the kernel, so the order is the one the utility
  wrote; how much a foreign binary prints is nobody's decision, hence the cap.

### Speed log: a per-action threshold

`diBaseController::SLOW_SPEED_ACTIONS` (default `[]`) raises the slow-speed bar
for one action — `const SLOW_SPEED_ACTIONS = ['upload' => 25.0]` — and
`Logger::speedFinish()` takes it as a third, optional `$slowValue` argument
(`null` keeps the global `Environment::slowSpeedValue`). Existing call sites are
unaffected.

**Do not mute such an action instead.** In `slow` mode the per-request lines only
buffer and `speedFinish()` is what flushes them, so muting drops the request's
whole block — and a stuck `exec()` in exactly this kind of action is what
exhausts an FPM pool. Raise the bar, keep the stall visible.

Also fixed there: `speedFinish()` now clears its buffer, so a request that calls
it twice (`autoCreate()` then `createAttempt()`) no longer writes the same block
to the log twice.

---

## 0.4.0

Minor, not patch: the shipped dumps change charset, generated DDL and the
connection init change behaviour, and a new migration runs on upgrade. Nothing
here is breaking for a consumer that keeps its own `Data\Config`, but «install
the update and carry on» is not the whole story — read the note below.

### Removed (BREAKING)

- **`dbUpdater`** (`php/lib/dbUpdater.php`) is gone. It was global, so the
  autoloader picked it up and a consumer could call it directly — use migrations.
  Deprecated for years, but deleting an autoloaded class is still a break: grep
  your project for `DBUpdater` before upgrading.

### utf8mb4 support

utf8(mb3) cannot store 4-byte characters (emoji), and unless `sql_mode` is strict
MySQL truncates the value at the first one **silently** — text is lost with no
error anywhere.

- **The shipped `sql/*.sql` dumps are now `utf8mb4` / `utf8mb4_general_ci`.**
  A charset is never named without its collation, so a table can no longer pick
  up the server default (`utf8mb4_0900_ai_ci` on MySQL 8) and collide with the
  rest of the schema on a join.
- **`Config::dbEncoding` / `dbCollation` still default to mb3.** Raising them
  would put an mb4 connection over the mb3 schema of every existing consumer on
  a mere `composer update`. New projects should set `utf8mb4` in their own
  `Data\Config`; existing ones convert first, then switch. See README.
- **New `diCore\Database\Tool\CharsetConverter`** — the conversion engine
  (per-column `MODIFY`, MyISAM→InnoDB, database default, stored programs,
  relaxed/strict session handling), so a consumer's migration is a few lines.
  It selects by **collation**, not charset, so a table already on utf8mb4 but
  carrying MySQL 8's default `utf8mb4_0900_ai_ci` is converted too — otherwise
  it keeps colliding with the rest of the schema on a join. Procedures,
  functions, views and triggers are rebuilt as well, and the DEFINER of every
  one of them is checked against the current account **before** the first
  `DROP`, since DDL does not roll back.
- **New migration `charset/20260728100000`** converts the tables this package
  ships to whatever charset the project configured. Run it **by idx** — the
  list-based helpers only scan the consuming project's migration folder. On
  SQLite/PostgreSQL it is a no-op; on a project still configured as mb3 it
  **refuses to run**, rather than being recorded as done and never converting
  anything once the config is flipped. A genuine no-op for a project that stays
  on mb3 or runs on SQLite/PostgreSQL: it never narrows a charset, and it does
  not apply to non-MySQL connections. It converts the tables only — stored
  programs attached to them stay on their old charset context, see README.
- **`diModel::getCreateTableQuery()`, `diActionsLog::initTable()` and
  `diSearch::check_index_table_existence()`** follow the configured charset
  instead of a hardcoded `utf8`, via the new `Config::getDbCharsetClause()` /
  `getDbColumnCharsetClause()`. Both omit `COLLATE` rather than emit a blank one
  (`diSearch` used to produce `collate ;`, a syntax error that left the site
  without a search index), and a blank `dbCollation` is written to the db log
  once per process instead of passing unnoticed — MySQL 8 then hands the table
  its own `utf8mb4_0900_ai_ci`, which collides with everything else on a join.
- **Dump export (`Controller\Db`) writes the charset each table actually has**,
  read from `information_schema`, instead of the configured one — plus its
  `ROW_FORMAT` and any per-column `COLLATE`. With the shipped dumps on mb4 and
  `Config::dbEncoding` still mb3 the two legitimately differ, so the old code
  would have described an mb4 table as mb3: restoring that dump narrowed the
  table silently. Per-column collations were being dropped outright, which turns
  a `_bin` column case-insensitive on restore.
- **Dump export no longer loses `UNIQUE`.** The marker was stripped off the
  index name and then never written back, so every unique index came out of the
  dump as a plain `KEY` and the restored table happily accepted duplicates.
  Unrelated to the charset work, but one line away from it.
- **`diDB::initCharset()` fixed:** `set_charset()` now runs BEFORE
  `SET NAMES … COLLATE …`. It issues its own `SET NAMES` and resets the collation
  to the charset default, so running it last silently discarded the configured
  collation — invisible on mb3 (same default), not on mb4. It also tolerates an
  empty collation and no longer swallows a failed `set_charset()` — **a charset
  the server rejects now throws `\diDatabaseException`**, exactly as an
  unreachable database does, instead of leaving the client on the previous
  charset and mangling 4-byte characters on every write. Not `_fatal()`: that
  ends in `die()`, so the answer is **HTTP 200** with the error in the body (an
  nginx cache in front keeps serving it after the fix) and it is not a
  `\Throwable`, so it walks past the handler a consumer uses to serve its own
  503. Note this is reachable by configuration alone — mysqlnd rejects the name
  `utf8mb3`, the spelling MySQL 8.0.28+ calls canonical, so **do not put it in
  `dbEncoding`; keep `utf8`**.

  A **collation** the server rejects is treated differently, on purpose: raising
  `dbEncoding` to `utf8mb4` and forgetting `dbCollation` leaves a pair MySQL
  refuses (`ERROR 1253`), and failing there would take a whole site down over a
  typo. The charset is already applied by that point, so nothing can be
  truncated: the connection falls back to that charset's default collation and
  the reason goes to the file log. Only if the plain `SET NAMES` is refused too
  is it fatal.
- **PDO driver:** the DSN charset comes from the configuration instead of a
  hardcoded `utf8`, which used to leave `PDO::quote()` on mb3 while the server
  session had moved to mb4.
- **`ROW_FORMAT=DYNAMIC`** is declared by every shipped dump that indexes a
  `varchar(255)` — 1020 bytes in mb4, over the 767-byte limit of InnoDB's older
  `COMPACT` format — and the converter switches a `COMPACT`/`REDUNDANT` table to
  it as part of the widening `ALTER`, which would otherwise abort. So no minimum
  MySQL version beyond what the rest of the library needs. The MyISAM→InnoDB
  move sets it too: MyISAM allows a 1000-byte key, so an index already over 767
  bytes AT THE OLD CHARSET could not even reach InnoDB's `COMPACT` format.
- Stored programs created under **`NO_BACKSLASH_ESCAPES`** are refused by name
  in the pre-flight: the tokeniser that keeps rewrites out of string literals
  assumes a backslash escapes the next character, and under that mode it does
  not — so a literal ending in one reads as running on into the code after it.
- Stored programs are recreated **on a session already switched to the target
  charset** (MySQL stamps a program with the charset context it was created
  under, so rebuilding on the old connection would put them straight back), and
  charset tokens are rewritten **only outside string literals and comments** — a
  routine that builds SQL as a string is left as it is.
- Stored programs keep their **own `sql_mode`** and, for triggers, their **firing
  order**: `SHOW CREATE` carries neither, so recreating them naively stamped the
  (deliberately relaxed) session mode on every one and reordered any group
  sharing a table/timing/event.
- Column **`DEFAULT`s come from `SHOW CREATE TABLE`**, the only source that keeps
  them: `information_schema.COLUMN_DEFAULT` renders a 4-byte character as `?`
  (so does `SHOW COLUMNS`), and re-quoting that value wrote the mangled text
  back and reported success. Hit exactly the tables this converter is most often
  pointed at — already utf8mb4, but on MySQL 8's default collation. A default
  that cannot be read back is now refused by name instead of guessed at.
- **`ENUM`/`SET` members holding a 4-byte character are refused**, for the same
  reason one level worse: the member's text lives inside `COLUMN_TYPE`, which the
  rebuild copies verbatim, and here `SHOW CREATE TABLE` renders it as `?` TOO —
  the real values sit in a data dictionary table SQL may not read, so there is no
  lossless source at all. Converting anyway redefined the member as the literal
  `?` and every stored row holding the original collapsed to the enum error value
  `''`, silently. A member legitimately containing `?` is refused as well: the
  false positive costs one table converted by hand, the false negative a column.
- The **pre-flight names any index that will not fit once widened**, for both
  key caps: MySQL's 1000 bytes on the FULLTEXT tables left on MyISAM, and
  InnoDB's 3072 for everything else (a MyISAM table without FULLTEXT is measured
  against InnoDB's, since it becomes InnoDB first). An indexed
  `latin1 varchar(1000)` fits today and needs 4000 bytes in mb4; before, that
  surfaced only from a failed `ALTER` partway down the table list, leaving the
  schema half converted under a connection already switched to mb4.
