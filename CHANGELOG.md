# Changelog

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
- The **pre-flight names any index that will not fit once widened**, for both
  key caps: MySQL's 1000 bytes on the FULLTEXT tables left on MyISAM, and
  InnoDB's 3072 for everything else (a MyISAM table without FULLTEXT is measured
  against InnoDB's, since it becomes InnoDB first). An indexed
  `latin1 varchar(1000)` fits today and needs 4000 bytes in mb4; before, that
  surfaced only from a failed `ALTER` partway down the table list, leaving the
  schema half converted under a connection already switched to mb4.
