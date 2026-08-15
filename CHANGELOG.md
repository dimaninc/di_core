# Changelog

## 0.4.0

Minor, not patch: the shipped dumps change charset, generated DDL and the
connection init change behaviour, and a new migration runs on upgrade. Nothing
here is breaking for a consumer that keeps its own `Data\Config`, but «install
the update and carry on» is not the whole story — read the note below.

### Removed

- **`dbUpdater`** (`php/lib/dbUpdater.php`) is gone. It was global, so the
  autoloader picked it up and a consumer could call it directly — use migrations.

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
- **`diModel::getCreateTableQuery()` and `diActionsLog::initTable()`** follow the
  configured charset instead of a hardcoded `utf8`, via the new
  `Config::getDbCharsetClause()`.
- **`diDB::initCharset()` fixed:** `set_charset()` now runs BEFORE
  `SET NAMES … COLLATE …`. It issues its own `SET NAMES` and resets the collation
  to the charset default, so running it last silently discarded the configured
  collation — invisible on mb3 (same default), not on mb4. It also tolerates an
  empty collation and no longer swallows a failed `set_charset()` — **a charset
  the server rejects now fails the request outright** (same path as an
  unreachable database), instead of leaving the client on the previous charset
  and mangling 4-byte characters on every write.

  A **collation** the server rejects is treated differently, on purpose: raising
  `dbEncoding` to `utf8mb4` and forgetting `dbCollation` leaves a pair MySQL
  refuses (`ERROR 1253`), and failing there would take a whole site down over a
  typo — via `_fatal()`, which answers **HTTP 200** with the error in the body,
  so an nginx cache in front would keep serving it after the fix. The charset is
  already applied by that point, so nothing can be truncated: the connection
  falls back to that charset's default collation and the reason goes to the file
  log. Only if the plain `SET NAMES` is refused too is it fatal.
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
- Stored programs are recreated **on a session already switched to the target
  charset** (MySQL stamps a program with the charset context it was created
  under, so rebuilding on the old connection would put them straight back), and
  charset tokens are rewritten **only outside string literals and comments** — a
  routine that builds SQL as a string is left as it is.
- Stored programs keep their **own `sql_mode`** and, for triggers, their **firing
  order**: `SHOW CREATE` carries neither, so recreating them naively stamped the
  (deliberately relaxed) session mode on every one and reordered any group
  sharing a table/timing/event.
