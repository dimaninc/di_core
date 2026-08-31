# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

`dimaninc/di_core` is a PHP CMS core library (Composer package) used as a dependency in web projects. It provides a full MVC stack: ORM, admin panel, routing, templating, authentication, and database migrations.

**PHP >= 7.4**, **Twig >= 2.11**, **PHPUnit ^11**

## Key Commands

```bash
# Install dependencies
composer install

# Run migrations (from consuming project root)
php vendor/dimaninc/di_core/php/admin/workers/cli.php controller=migration action=up_last_not_executed

# Run tests (core library)
./vendor/bin/phpunit -c phpunit.xml.dist

# Run tests (consuming project uses phpunit-project.xml.dist as template)
./vendor/bin/phpunit -c phpunit.xml

# Post-install setup scripts (from consuming project root)
sh vendor/dimaninc/di_core/scripts/copy_core_static.sh
sh vendor/dimaninc/di_core/scripts/create_work_folders.sh
```

## Architecture

### Two-layer class system

- **`php/lib/`** — Legacy global classes (no namespace): `diModel`, `diCollection`, `diBaseController`, `diLib`, `diTwig`, `diTypes`
- **`php/src/diCore/`** — Namespaced code (PSR-4: `diCore\`): entities, admin pages, controllers, traits, helpers

New code goes in `php/src/diCore/`. Legacy classes in `php/lib/` are base classes still actively extended.

### Entity pattern (Model + Collection)

Each database entity lives in `php/src/diCore/Entity/{EntityName}/` with two files:

- **`Model.php`** — extends `\diModel`. Represents a single row. Uses magic methods: `getFieldName()`, `setFieldName($v)`, `hasFieldName()`. Field names are CamelCase transforms of snake_case DB columns (e.g., `created_at` → `getCreatedAt()`).
- **`Collection.php`** — extends `\diCollection`. Query builder with fluent interface: `filterByField($value, $operator)`, `orderByField($direction)`, `selectField()`.

Both files include `@method` PHPDoc blocks for IDE autocompletion. Traits add shared behavior:

| Model Trait | Collection Trait | Purpose |
|---|---|---|
| `Traits\Model\Hierarchy` | `Traits\Collection\Hierarchy` | Parent-child tree |
| `Traits\Model\AutoTimestamps` | `Traits\Collection\AutoTimestamps` | created_at/updated_at |
| `Traits\Model\JsonProperties` | `Traits\Collection\JsonProperties` | JSON field handling |
| `Traits\Model\ActiveInside` | `Traits\Collection\ActiveInside` | Active/inactive status |
| `Traits\Model\UserInside` | — | User ownership |
| `Traits\Model\Tagged` | `Traits\Collection\Tagged` | Tag associations |
| `Traits\Model\OrderItem` | `Traits\Collection\OrderItem` | Sort ordering |

Entity types are registered in `diTypes` (integer constants). Model declares `const type`, `const table`.

### Controllers

Extend `diBaseController`. Located in `php/src/diCore/Controller/`. Action methods named `action{Name}()`. REST controllers use `_postAction()`, `_putAction()`, `_deleteAction()`. Bilingual `$language` arrays (en/ru) for error messages. Routes resolve via PSR-4: `/api/auth/login` → `Controller\Auth::actionLogin()`.

**Speed log: `LOG_SPEED` gates the controller, `SLOW_SPEED_ACTIONS` raises the bar for one action.** In `slow` mode the per-request lines only buffer — it is `speedFinish()` that flushes them, so an action-level MUTE would drop the request's whole block. Don't do that: for an action whose duration is inherently large (upload, image processing) the global threshold means a slow-speed entry on every request, but muting it also hides a genuine wedge, and a stuck `exec()` in exactly such an action is what exhausts an FPM pool. Give it its own threshold instead — `const SLOW_SPEED_ACTIONS = ['upload' => 25.0]`, passed through as `speedFinish($message, $module, $slowValue)`. `createAttempt()` closes the request without knowing the action, so `autoCreate()` stores it in `static::$routedAction` first. Covered by `php/tests/Controller/SpeedLogActionsTest.php`.

### Admin panel

- **`Admin\Base`** — Admin shell, menu, routing. URL pattern: `/_admin/{module}/{method}/{id}`
- **`Admin\BasePage`** — CRUD base for list/add/edit. Subclassed per entity in `Admin\Page\{Name}`
- **`Admin\Form`** — Form field definitions and rendering
- **`Admin\Submit`** — Form processing, file/image uploads
- **`Admin\Grid`** — List/table display with sorting and columns

**Edit-log writes made outside the admin form are gated by ONE helper: `Admin\Base::isEditLogEnabledForModule($module)`** (`isEditLogEnabledForTable()` is the thin wrapper for a writer that only knows the table). The flag itself stays where it always was (the page's `useEditLog()`), but the writers (list toggle/delete in `diListController`, `Controller\Configuration`) run where the admin `Base` (`$X`) is not built, so the page is instantiated with `newInstanceWithoutConstructor()` and anything that throws counts as "no logging". Don't inline a second copy of that resolution: the two writers would then disagree about whether the same table is logged. **Ask by the name the page is ADDRESSED by, not by the one it happens to store into:** for an ordinary entity they coincide, but `Data\Configuration::setTableName()` renames the settings table under a page still reached as `/_admin/configuration/` — gating on the table name there resolves to no page at all, so the journal stays empty forever while the page keeps showing its tab, and nothing errors. **A library test must not assert what a CONSUMER decides:** the gate test used to compare the answer for `configuration` against a literal `false`, which held only while no project had turned the log on — the first one that did turned this suite red (consumers run `php/tests` under their own bootstrap) for doing exactly what the library invites. It now asserts the answer equals the module's own, with the table pointing at a probe page that answers the opposite.

**The settings page logs too, with a synthetic target.** `Admin\Page\Configuration::useEditLog()` (default `false`, a project turns it on; the gate asks by `Admin\Page\Configuration::ADMIN_MODULE`, never by the table) makes `Controller\Configuration` wrap `storeAction()`/`delPicAction()` in `runWithEditLog()` – snapshot before, action, snapshot after, ONE record with just the difference. The setting keys stand in for field names, which is what lets the existing diff template render it unchanged. Two things that are not obvious: the snapshot always re-reads the DB (`loadAllFromDB()`), because `store()` refreshes `self::$data` only for the checkboxes it unchecked, so `getData()` alone answers with pre-store values; and the record needs a `target_id` it doesn't have (settings are not a row, and `Model::validate()` rejects an empty one, which `0` is) – hence `Admin\Page\Configuration::EDIT_LOG_TARGET_ID`, the whole table as one logical record. The write is wrapped in `catch (\Throwable)` end to end: a journal has no right to break saving the settings — but **best-effort is not silent**: both catches report through `Controller\Configuration::onEditLogFailure()` (file channel + `E_USER_WARNING`, mirroring `BasePage::onEditLogUnavailable()` on the reading side), or an unreachable store reads as «nobody changed anything», which is the one answer this log exists to disprove. **A record may point at a TABLE rather than a row, and every target resolver must ask first** (`Model::hasTargetModel()`): `getTarget()` went through `createForTable()`, which throws for a table with no model class, and `Admin\Page\AdminTableEditLog::renderForm()` calls it unguarded — a settings record killed that page. It now yields an empty `\diModel` bound to the table (`---`), and `getTargetAdminHref()` points at the module, not at a `/form/<id>/` that does not exist. Not `createForTableNoStrict()`: that loads by id, and a synthetic id resolves to whatever real row carries it — a wrong target reads worse than none. Covered by `php/tests/Controller/ConfigurationEditLogTest.php` and `php/tests/Entity/AdminTableEditLog/SyntheticTargetTest.php`.

**The log template cannot be rendered without `BasePage::registerEditLogEscaper()`** — it pipes every diff through `escape('insdel')`, and a strategy Twig does not know is a `RuntimeError`, not a fallback. So `renderEditLog()` registers it itself, at the render, instead of relying on `beforeRenderForm()`: a page without a form never runs that. General shape, worth remembering past this template: **a template with a hard dependency on runtime registration must acquire it where it is rendered, not where one of its callers happens to prepare it** — the second caller is written years later and the failure is a 500, not a missing feature.

**The log tab is `shouldPrintEditLog()` + `renderEditLog()` + `printEditLog()`, split on purpose.** The settings page fits none of the base defaults: it has no id (so its `shouldPrintEditLog()` drops that condition), and no form at all (so it places `renderEditLog()`'s string into its own tab template instead of `getForm()->setInput()`). Its tab goes LAST, so `FIRST_TAB` stays a settings group, and into its own block template – `tab_page.htm` wraps content in `<table class="grid">` while the log is a `<ul>` – but still inside `{TAB_PAGES}`, because `js/admin/diConfiguration.js` hands `diTabs` the `form [data-purpose="tab-pages"]` container. And it renders through `renderEditLogSafely()`, not `renderEditLog()`: the base deliberately leaves everything but the store read unguarded so a code bug surfaces, which on a record form costs the form — here it costs the ONLY door to the settings, since `BasePage::create()` turns the exception into `die($message)` on prod and the settings are then changeable only in the database. **Weigh "fail loudly" against what the failing page IS:** on a page that is itself the recovery tool, a loud failure removes the recovery.

### Templating

Primary: Twig (`.html.twig` in `templates/`). Legacy: FastTemplate (`.html` in `tpl/`). Core templates use `@core` namespace. Twig cache: `_cfg/cache/twig/`.

### Images

`diImage` (global, `php/lib/`) — GD-based thumbnails, watermarks, format detection.

**`exec()` has no timeout — use `Helper\ProcessHelper::run($command, $seconds)` for anything external.** `set_time_limit()` on Unix does not count time spent in system calls, so a wedged child process holds an FPM worker until the pool starves. The helper runs the command through `proc_open`, drains both pipes non-blockingly (a full pipe buffer would deadlock the child) and sends SIGTERM then SIGKILL at the deadline, returning `['code', 'output', 'timedOut']`. **Pass the command as an ARRAY when the kill has to reach the binary** — a string goes through `sh -c`, and only a single command can be `exec`-prefixed to replace the shell; a compound one leaves the shell's children behind (the call still returns on time). Degrades to plain `exec()` where `proc_open` is disabled. Covered by `php/tests/Helper/ProcessHelperTest.php`.

**HEIF is detected by its `ftyp` brand, not by finfo — and AVIF is excluded FIRST.** `diImage::isHeic()` reads the ISO-BMFF brands (major + compatible) out of the first bytes. AVIF is MIAF too and declares `mif1` compatible, so the two are indistinguishable by that brand alone; `avif`/`avis` are checked before the HEIF list, or every AVIF would be handed to a HEIC converter that cannot read it. Beyond that: the magic database differs between systems and versions, and a two-string comparison against `image/heic`/`image/heif` never covered the *sequence* brands (`hevc`, `msf1` — live photos and bursts) at all. The asymmetry is what forces the care: an unrecognised HEIF is simply not converted, `getimagesize()` then answers 0×0, and a zero-size picture travels on with no exception and no log line. Covered by `php/tests/Image/HeicDetectionTest.php` — **with a real AVIF layout** (`avif` + `mif1 miaf MA1B`): the first version of that fixture was a 16-byte `ftyp` with no compatible brands at all, which no encoder produces, so it passed while real AVIF files did not. A test built to pass is worse than no test.

**HEIC → JPEG converts through a fallback chain, not one binary.** `diImage::convertHeicToJpeg()` walks `getHeicConverterOrder()` — the platform's CLI (`heif-convert` on Linux, `magick convert` on mac), then **ext-imagick** (no `exec()`, so it does not fork an FPM worker; needs a libheif delegate), then the other CLI — and throws only when every one failed, with each attempt's reason in the message and in the log. Two invariants: a zero exit code is not success (`heicOutputIsUsable()` re-reads the file, since `safeImageSize()` would otherwise hand a 0×0 image downstream), and a failed attempt's leftover file is deleted before the next converter runs. iPhone photos are the most common upload there is — a single failing converter must not mean nobody can upload anything. The whole chain shares ONE time budget (`HEIC_TOTAL_TIMEOUT_SEC`), including the in-process ext-imagick step — that one can't be interrupted from outside, so its share is applied as `Imagick::RESOURCETYPE_TIME` (process-global, hence restored afterwards). Not one budget per converter — three timeouts in a row would be the tripled worst case the timeout exists to prevent; each attempt gets what is left, and an exhausted budget is refused rather than run unbounded. Covered by `php/tests/Image/HeicConversionTest.php`.

### Payment gateways

Each gateway is a `Payment\{Name}\Helper` (extends `Payment\BaseHelper`) that a project subclasses as `Settings`, plus a thin API client. Two invariants from `Payment\Tinkoff` generalise to any of them.

**A field parsed out of a gateway response describes THAT response — clear it before reading a new one.** The api client is cached per helper (`getApi()`) and one instance serves a whole reconciler run, so anything left from the previous call silently belongs to another payment; `handleResponse()` therefore resets `PaymentId`/`Status`/`PaymentURL` up front, ahead of every early return, and the transport-failure branch resets them too. Only whether a MISSING `PaymentURL` counts as an error is method-dependent (`PAYMENT_URL_METHODS`) — and that comparison is **case-insensitive**, because `buildQuery()` is public and the method name comes from the consumer. Covered by `php/tests/Payment/TinkoffSbpPayloadTest.php`.

**A string the gateway sent back is not a place to send a payer until it is checked.** The SBP payload becomes the QR and the deep link, i.e. it IS the payment destination, so `Helper::isSbpPayload()` demands https, no userinfo, a whitelisted host, printable ASCII anchored with `\z` (`$` also matches before a trailing newline — that one leaked a log-forging `\n`) and a length cap. `PaymentURL` has the same shape of hole and is **not** covered yet — see the `TODO(BOTCARD-10)` in `MerchantApi::handleResponse()`.

**TLS is verified** (`MerchantApi::VERIFY_TLS`), and a root missing from the host's store is answered with a bundle — `Helper::getCaBundlePath()` → `CURLOPT_CAINFO` — never by turning verification off: the bundle adds one anchor, the constant removes the protection for every host. `Helper::createApi()` is the seam for substituting an api subclass at all. Covered by `php/tests/Payment/TinkoffCaBundleTest.php`.

### Database

Supports MySQL (primary), PostgreSQL, SQLite, MongoDB. Schema files in `sql/` with engine-specific variants in `sql/postgres/`, `sql/sqlite/`. Connection managed by `diCore\Database\Connection`.

**Mongo connection options.** Timeouts keep the driver's own defaults — the library must not retune them for every consumer, since the safe value depends on topology (a replica set needs server selection to ride out a primary election). Set the three timeouts per connection in the settings array (other URI options are ignored); `Connection::open()`/`openByDsn()` also take them as a trailing `$extraOptions` argument, because a DSN's query string is not parsed. See [`doc/mongo-timeouts.md`](doc/mongo-timeouts.md).

**Admin edit-log degradation.** `Admin\BasePage::renderEditLog()` guards only its store read (against `\Exception`, so code bugs still surface) and fills the tab with a "temporarily unavailable" notice — the tab is registered unconditionally, so an empty one would read as "never edited". Failures go through the overridable `onEditLogUnavailable()` hook, which by default only writes to the file log. **Override it to report to your monitoring:** the same guard also turns a real breakage (e.g. a table missing after a bad migration) from a loud 500 into a quiet notice. An override of `createEditLogCollection()` must not make the render load chunks lazily (that happens inside the template, outside the guard); `setPageSize()` is not that and is the answer for a log nothing else bounds – `count()` clamps itself to the page size, so the single `loadChunk()` already sets `loaded`.

**Charset: the shipped `sql/*.sql` dumps are `utf8mb4`, `Config::dbEncoding` still is not.** The default connection charset stays mb3 on purpose — raising it would put an mb4 connection over the mb3 schema of every existing consumer on a `composer update`. New projects set `utf8mb4`/`utf8mb4_general_ci` in their own `Data\Config`; existing ones convert the schema and flip the config in ONE deploy (order depends on whose migration runs — see README). **Never spell `dbEncoding` as `utf8mb3`:** mysqlnd rejects that name, and `initCharset()` treats a refused charset as a broken connection (throws `\diDatabaseException`, same as an unreachable host). **Never name a charset without its collation** in generated DDL — use `Config::getDbCharsetClause()` / `getDbColumnCharsetClause()` (they omit `COLLATE` rather than emitting a blank one, and log the misconfiguration), never a literal `utf8`. **Anything reading a column DEFAULT for DDL must use `SHOW CREATE TABLE`** — `information_schema.COLUMN_DEFAULT` and `SHOW COLUMNS` render a 4-byte character as `?`. `diCore\Database\Tool\CharsetConverter` is the conversion engine (per-column `MODIFY`, MyISAM→InnoDB, `ROW_FORMAT=DYNAMIC`, database default, stored programs, DEFINER/sql_mode/key-cap pre-flights) and `migrations/charset/20260728100000` converts this package's own tables — run it **by idx**, the list-based helpers only scan the consuming project's folder. Tables built in code (`di_migrations_log`, `configuration`, `di_actions_log`, `search_index_*`) have no dump and are not in its list. Full upgrade notes, invariants and the consumer-migration recipe: [`README.md`](README.md) and [`CHANGELOG.md`](CHANGELOG.md).

**Removed: `dbUpdater`** (`php/lib/dbUpdater.php`). It was deprecated but global and autoloaded, so a consumer could still be calling it — use migrations.

### Migrations

Files in `_cfg/migrations/` (in consuming project), format `{idx}_{name}.php`. Extend `diCore\Database\Tool\Migration`. Must implement `up()` and `down()`. Tracked in `di_migrations_log` table. Managed via `MigrationsManager`.

**Scaffold the migration file with the generator, then fill in `up()`/`down()`** — don't hand-write the file. Two ways:

- Admin UI: **Admin → Migrations** (`/_admin/migrations/`) has a create form; `idx` defaults to the current `YmdHis`.
- CLI (`MigrationsManager::createMigration($idx, $name, $folder = '')`):
  ```bash
  php -r "
  require 'vendor/dimaninc/di_core/php/cliHelper.php';
  (new \diCore\Database\Tool\MigrationsManager())->createMigration(date('YmdHis'), 'Added product table');
  echo 'Done';
  "
  ```

The generator writes the file with the correct class name (`diMigration_{idx}`), matching `$idx`/`$name`, a transliterated/slugified filename, and empty `up()`/`down()` stubs. The optional third arg is a subfolder (e.g. `'payments'` → `_cfg/migrations/payments/`). After scaffolding, fill `up()` (e.g. `$this->executeSqlFile([...], \diCore\Controller\Db::getDumpsFolder() . 'tables/')` for a new table, or `$this->getDb()->q("ALTER TABLE ...")` for alterations).

**Localization migrations add tokens with `LocalizationMigration::insertValues($values, $strict = true)`** — `'token' => ['ru' => …, 'en' => …]`, **keyed by language, never positional**. A positional list forced the caller to agree with a column order defined in another repository, and the day a project inserted a language in the middle, every later migration wrote Spanish into the Italian column — silently, and nothing about the data looked wrong until a visitor read it. A language key cannot drift, which is also why the ORDER of the value columns now carries no meaning, only their set. Each token becomes one `INSERT IGNORE` (key and values escaped through the DB API), so a translation an editor already changed survives a re-run.

`$strict` is completeness: every language the model has a column for must be present, an unknown one is an error. Pass `false` for a deliberate subset — the remaining columns are written as `''` (not left to the column default, so a re-run cannot depend on the schema) and the shortfall is logged, never silent; an unknown language is then skipped and logged too, which is what lets a six-language migration install on a two-language database. An empty translation list is an error in **both** modes. The whole batch is validated before the first row is written, so a bad token cannot leave the migration half-applied.

**The table and the language set both come from the project's localization model** (`getLocalizationModel()` → `Model::create()`, resolved project-namespace-first). `Entity\Localization\Model::getValueFields()` derives them from `$fieldTypes` — matching `value` and `xx_value` with a two-letter code, so an unrelated `default_value` column is not written to by every migration — which means adding a language to a project is one line in its model, and cannot be done half-way. This package's own `sql/localization.sql` has only `value` and `en_value`.

`insertValues()` does **not** fill `$names`, and it can't: `down()` runs in its own process on an instance whose `up()` never ran, so list the tokens there by hand or the rollback is a silent no-op (`diDB::in([])` compares against `''`). Covered by `php/tests/Database/LocalizationMigrationInsertValuesTest.php` and `php/tests/Entity/LocalizationValueFieldsTest.php`.

## Conventions

- **ALWAYS scaffold entities and admin pages with the generators, then edit the result.** Never hand-write a `Model.php` / `Collection.php` / `Admin/Page/*.php` from scratch. The mandated flow for any new entity is: create the SQL schema + migration → run the migration so the table exists → run `ModelsManager` to generate Model + Collection → run `AdminPagesManager` to generate the admin page → only then manually adjust the generated files (extra fields, columns, custom logic). The generators introspect the live table, so the table must exist first. See "Adding a New Entity to a Project" below for the exact commands.
- **Use `Model::createById($id)` and `Model::createBySlug($slug)` instead of `Model::create($id)`**. The generic `create()` is ambiguous; always prefer the explicit factory methods.
- DB columns are `snake_case`; magic accessors are `CamelCase` (e.g., `order_num` → `getOrderNum()`)
- In old code image fields come in groups: `{name}`, `{name}_w`, `{name}_h`, `{name}_t` (filename, width, height, type), lately this changed to only `{name}` storing.
- Entity `const type` must match `diTypes` integer constant
- Model `$publicFields` controls which fields appear in API/public output
- Model `$fieldTypes` maps columns to `FieldType` enum (json, date, etc.)
- Model `$picStoreSettings` configures image upload/resize behavior
- Collections are lazy-loaded — query executes on first iteration/count
- Admin pages are registered as modules in the admin menu system
- Nothing guessable (tokens, keys, file names) comes from `rand()`/`mt_rand()` — use `get_unique_id()` or `StringHelper::random()`; never seed the global generator except through `diCollection::seedRandomizer()`

### Date/Time Formatting

Use `\diDateTime::sqlFormat()` instead of `date('Y-m-d H:i:s')` for SQL datetime strings:

```php
\diDateTime::sqlFormat();              // current datetime → 'Y-m-d H:i:s'
\diDateTime::sqlFormat('-1 hour');     // relative (strtotime-compatible)
\diDateTime::sqlFormat('+10 minutes');
```

Also available: `\diDateTime::sqlDateFormat()` for date-only (`Y-m-d`).

### Model/Collection Destroy Methods

- `$model->destroy()` — **in-memory only**, clears model data, does NOT delete the DB record
- `$model->hardDestroy()` — deletes DB record + related files and data
- `$collection->softDestroy()` — **batch** deletes all DB records by IDs (single `DELETE ... WHERE id IN (...)` query), no related file cleanup
- `$collection->hardDestroy()` — iterates models to kill related files, then batch deletes DB records

**Rule:** Use `softDestroy()` on collections when entities have no related files — it's a single query instead of N individual deletes. Use `hardDestroy()` only when models have images or related data that need cleanup.

### Database & Collection gotchas

These bite when using `diDB` / `diCollection` directly (e.g., in tests or one-off scripts) outside of the entity layer.

**`diCollection` pagination is 1-indexed.** `setPageNumber(0)` returns zero rows even though `count()` reports the correct total — there is no page 0. Either omit `setPageNumber()` (default is page 1) or pass `setPageNumber(1)`. Symptom: `count() === N` but the iterator yields nothing.

**`diDB` query API.** Methods on `\diDB` / `\diMYSQLi` you actually have:

| Need | Use |
|---|---|
| Run any SQL | `$db->q($sql)` — returns the mysqli_result for SELECTs, bool for writes |
| Fetch one row from a result set | `$db->fetch($rs)` (object) or `$db->fetch_array($rs)` (array) |
| Read one record by WHERE | `$db->r($table, 'WHERE id=1', 'id, email')` — returns **stdClass**, accessed via `->field` |
| Read all records by WHERE | `$db->ar($table, 'WHERE …', 'id, email')` — array of stdClass |
| Count rows in a result set object | `$db->count($rs)` (operates on `$rs`, NOT a query) |
| Count rows by WHERE clause | `(int) $db->r($table, 'WHERE …', 'COUNT(*) AS n')->n` — or use `Collection::create()->filterBy…()->count()` |
| Insert / update / delete | `$db->insert($t, $vals)`, `$db->update($t, $vals, 'WHERE…')`, `$db->delete($t, 'WHERE…')`, `$db->insert_or_update(…)`, `$db->insertIgnore(…)` |
| Last inserted id | `$db->getLastInsertId()` |
| Escape values | `$db->escape_string($s)`, `$db->quoteValue($s)`, `$db->quoteField($f)` |

There is **no** `getCount()`, `getRecordsCount()`, or `getRecordsCountByQuery()`. If you find yourself reaching for those, either run `SELECT COUNT(*)` via `q()`/`r()`, or use the Collection layer — that's what it's for.

**`Traits\Model\JsonProperties` hardcodes the column name `properties`.** Reading the trait source: `setProp()`/`prop()` always reference `'properties'`. If your entity uses a different JSON column (e.g. `payload`, `settings`, `data`), do NOT `use JsonProperties`. Either:
- Call `\diModel`'s underlying helpers directly: `$model->getJsonData($field, $path)`, `$model->updateJsonData($field, $path, $value)`, `$model->hasJsonData($field, $path)`, `$model->killJsonData($field, $path)`. These take the column name as the first arg.
- Or write thin wrappers on the entity's `Model` class (e.g. `getProp(string $path)` that delegates to `getJsonData('payload', $path)`).

**`Model::create()` factory shape.** `Model::create()` with no args creates an empty unsaved model — fine for inserts. For lookups, prefer the explicit factories:
- `Model::createById($id)`
- `Model::createBySlug($slug)`
- `Collection::create()->filterBy…()->getFirstItem()` returns an empty model when nothing matches; check `$m->exists()` before using it.

**Filter magic methods.** `filterByEmail($value)`, `orderByCreatedAt('asc')`, `selectId()` etc. are all generated by `\diCollection::__call()` based on field names — they're listed in the Collection's `@method` PHPDoc but not declared as real methods. Operator support: `filterByX($value, $operator)` where `$operator` is `'='`, `'!='`, `'>'`, `'<='`, `'>='`, `'<'`, etc.

**Upsert id-recovery on `Model::save()`.** When `allowInsertOrUpdate()` hits the UPDATE path on MySQL, the model's id is auto-populated via the `LAST_INSERT_ID(<idField>)` trick — wired through `diDB::insert_or_update($..., $autoIncrementField)` from `saveToDb()`. When `allowSkipConflictOnInsert([...lookupFields])` is called with the unique-key columns and the row already exists, `saveToDb()` runs a follow-up `SELECT` on those columns to populate the id. Without lookup fields, the model is left without an id on conflict (the INSERT IGNORE silently skips). Covered by `php/tests/Database/SaveToDbTest.php`.

## Testing

di_core ships its own framework tests under `php/tests/`. They are self-contained (create their own throwaway tables in `setUp`) and are intended to be picked up by the consumer project's PHPUnit by adding a second `<directory>` entry next to `tests/unit`:

```xml
<directory suffix="Test.php">vendor/dimaninc/di_core/php/tests</directory>
```

`phpunit-project.xml.dist` already includes this entry. Tests use the consumer project's bootstrap (DB connection, autoload) — keep them framework-only, no project-specific entities or types.

## Adding a New Entity to a Project

Step-by-step guide for creating a new database entity in a project that uses `di_core`.

**Prerequisites:** The project namespace is registered in `_cfg/common.php` via `\diLib::registerNamespace()`. The local dev domain is defined in `src/{Namespace}/Data/Environment.php`.

### Step 1: Create the SQL schema file

Create `db/dump/tables/{table_name}.sql` with `CREATE TABLE IF NOT EXISTS`.

**Conventions:**
- InnoDB engine, `DEFAULT CHARSET=utf8mb4`, `COLLATE=utf8mb4_general_ci` — **always name the collation too**: a charset without one gets the server default (`utf8mb4_0900_ai_ci` on MySQL 8), which then collides with every other table on a join
- `id` is `BIGINT AUTO_INCREMENT` primary key (or `INT` for small tables)
- Columns use `snake_case`
- `created_at` → `TIMESTAMP DEFAULT CURRENT_TIMESTAMP`
- `updated_at` → `TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`
- Named indexes (`idx`, `slug_idx`, `target_idx`, etc.)

### Step 2: Create a migration

**Scaffold the file with the generator, then fill in `up()`** (see "Migrations" above) — don't hand-write it:

```bash
php -r "
require 'vendor/dimaninc/di_core/php/cliHelper.php';
(new \diCore\Database\Tool\MigrationsManager())->createMigration(date('YmdHis'), 'My entity');
echo 'Done';
"
```

This writes `_cfg/migrations/{idx}_My-entity.php` with the right class name and empty `up()`/`down()`. Then fill `up()` to create the table:

```php
public function up()
{
    $folder = \diCore\Controller\Db::getDumpsFolder() . 'tables/';
    $this->executeSqlFile(['my_entity.sql'], $folder);
}
```

**Conventions:**
- Class name: `diMigration_{idx}` (set by the generator)
- `$idx` matches the timestamp in the filename
- `$name` is a human-readable description
- For new tables: use `$this->executeSqlFile()` pointing to the SQL file
- For alterations: use `$this->getDb()->q("ALTER TABLE ...")`
- Migrations can live in subdirectories — pass the subfolder as the 3rd arg of `createMigration()` (e.g., `'payments'` → `_cfg/migrations/payments/`)

Run the migration by its idx:
```bash
php vendor/dimaninc/di_core/php/admin/workers/cli.php controller=migration action=up idx=20260214120000
```

### Step 3: Register the entity type

Edit the project's `src/{Namespace}/Data/Types.php` (extends `\diCore\Data\Types`). Add:

1. **A new integer constant** (pick the next available ID):
   ```php
   const my_entity = 88; // next unused ID
   ```

2. **An entry in `$tables`** (type ID → table name):
   ```php
   self::my_entity => 'my_entity',
   ```

3. **An entry in `$names`** (type ID → name string, usually matches the constant name):
   ```php
   self::my_entity => 'my_entity',
   ```

4. **An entry in `$titles`** (type ID → human-readable title):
   ```php
   self::my_entity => 'My Entity Title',
   ```

### Step 4: Generate Model and Collection

Generate from CLI using `ModelsManager` (`diCore\Tool\Code\ModelsManager`):

```bash
php -r "
require 'vendor/dimaninc/di_core/php/cliHelper.php';
(new \diCore\Tool\Code\ModelsManager())->createEntity(
    ['default', 'my_entity'],  // [connection, table]
    true,                       // create model
    '',                         // model class name (auto-detected)
    true,                       // create collection
    '',                         // collection class name (auto-detected)
    \diLib::getFirstNamespace() // project namespace
);
echo 'Done';
"
```

This generates:
- `src/{Namespace}/Entity/{PascalCaseName}/Model.php` — with `@method` PHPDoc annotations, `$fieldTypes`, and auto-detected traits (`AutoTimestamps` if `created_at`+`updated_at` exist, `TargetInside` if `target_type`+`target_id` exist)
- `src/{Namespace}/Entity/{PascalCaseName}/Collection.php` — with `filterBy`/`orderBy`/`select` annotations and matching traits

**Note:** The entity class name is derived from the table name by singularizing and camelizing it (e.g., `my_entities` → `MyEntity`, `discount_first_visit` → `DiscountFirstVisit`). The `const type` references `\diTypes::{name}` which must already exist from Step 3.

### Step 5: Generate an admin page

Whenever the entity needs an admin UI, **always generate the page with `AdminPagesManager` first, then edit it** — do not write `Admin/Page/*.php` by hand.

Generate from CLI using `AdminPagesManager` (`diCore\Tool\Code\AdminPagesManager`):

```bash
php -r "
require 'vendor/dimaninc/di_core/php/cliHelper.php';
(new \diCore\Tool\Code\AdminPagesManager())->createPage(
    ['default', 'my_entity'],  // [connection, table]
    '',                         // caption (auto-detected from Types titles)
    '',                         // class name (auto-detected)
    \diLib::getFirstNamespace() // project namespace
);
echo 'Done';
"
```

This generates `src/{Namespace}/Admin/Page/{PascalCaseName}.php` with:
- List view with auto-detected columns
- Form with fields based on column types (auto-maps to `string`, `int`, `checkbox`, `datetime_str`, `pic`, etc.)
- Default sorting by `order_num ASC` (if exists) or `id DESC`
