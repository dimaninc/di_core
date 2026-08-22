dimaninc Core library
=========================

Upgrade notes
-------------

### Removed: `dbUpdater`

`php/lib/dbUpdater.php` is gone. It was deprecated, but it was also global and
autoloaded, so a consumer could be calling it directly — use migrations.

### utf8mb4

The shipped `sql/*.sql` dumps are `utf8mb4` / `utf8mb4_general_ci`. utf8(mb3)
cannot store 4-byte characters (emoji); unless `sql_mode` is strict, MySQL
truncates the value at the first one **silently**, so text is lost with no error
anywhere.

**The connection charset still defaults to mb3** — raising it would put an mb4
connection over the mb3 schema of every existing consumer on a mere
`composer update`. So:

> **The dumps and the default connection charset disagree on purpose, and that
> is a temporary state.** A project that takes the mb4 dumps but leaves the
> connection on mb3 gets tables able to hold emoji behind a connection that cuts
> them first — silent loss, with nothing wrong-looking in the schema. Set the
> connection charset, or convert, but do not sit in between. The migration below
> refuses to narrow an mb4 table back to mb3 for the same reason.

- **New project:** set `utf8mb4` right away in your own `Data\Config`:
  ```php
  const dbEncoding = 'utf8mb4';
  const dbCollation = 'utf8mb4_general_ci';
  ```
- **Existing project:** leave it on mb3 until you are ready to convert, then do
  the conversion and the config flip as ONE deploy, with writes stopped. Either
  half on its own truncates: an mb3 connection cuts 4-byte characters before
  they reach an mb4 column, an mb4 connection cannot write them into an mb3 one.

  Which half goes first depends on whose migration you run, and the two are not
  interchangeable:

  | | order | why |
  |---|---|---|
  | your own migration | schema, then config | it names `'utf8mb4'` itself (see the example below), so it does not care what is configured |
  | the bundled `charset/20260728100000` | config, then schema | it converts to the **configured** charset and refuses to run while that is still mb3 |

  Two migrations, one deploy: run the bundled one for the tables this package
  ships and your own for the rest.

**Row format.** An indexed `varchar(255)` needs 1020 bytes in mb4, over the
767-byte limit of InnoDB's older `COMPACT` format. Every shipped dump that has
one declares `ROW_FORMAT=DYNAMIC` explicitly, so they install even where
`innodb_default_row_format` is still `COMPACT`; the converter likewise switches a
`COMPACT`/`REDUNDANT` table to `DYNAMIC` as part of the widening `ALTER`, since
that ALTER would otherwise abort. `COMPRESSED` tables are left as they are.
Footnote for the truly ancient: on MySQL 5.5/5.6 with the Antelope file format,
`ROW_FORMAT=DYNAMIC` silently falls back to `COMPACT` and the wide index fails
anyway — both are long EOL, so **5.7.9+** remains the practical floor.

#### Converting an existing schema

`charset/20260728100000` converts the tables this package ships. **Run it
explicitly** — the list-based helpers (`upNew`, `upLastNotExecuted`) only ever
scan the consuming project's own `_cfg/migrations/`, so a migration shipped with
the package is not picked up by them:

```bash
php vendor/dimaninc/di_core/php/admin/workers/cli.php \
    controller=migration action=up idx=20260728100000
```

It converts to the charset the project has **configured**, and refuses to run
while that is still mb3 — otherwise a run made before the config flip would be
recorded as done and the core tables would never be converted.

**The rest of your schema is yours to convert** —
`diCore\Database\Tool\CharsetConverter` is the same engine:

```php
public function up()
{
    (new \diCore\Database\Tool\CharsetConverter(
        $this->getDb(),
        'utf8mb4',
        'utf8mb4_general_ci'
    ))->inPreparedSession(
        false,
        fn($c) => $c
            // First: everything that can make the run impossible. Without it a
            // stored program owned by another account is only discovered after
            // every table has been converted, and that cannot be rolled back.
            ->preflight()
            ->moveMyisamTablesToInnoDb()
            ->convertTables()
            ->setDatabaseCharset()
            ->rebuildStoredPrograms()
    );
}
```

What it handles, each of which is a way this goes wrong quietly:

- Per-column `MODIFY` instead of `CONVERT TO CHARACTER SET`, which widens types
  (`text` → `mediumtext` → `longtext`) and forces one collation on every column,
  flattening `_bin` columns to case-insensitive — enough to collide slugs
  differing only in case under a UNIQUE index.
- Selection by **collation**, not charset. A table already on utf8mb4 but with
  MySQL 8's default `utf8mb4_0900_ai_ci` — which is every table created once the
  database default became mb4 — would otherwise be skipped and go on raising
  "Illegal mix of collations" against the converted ones.
- Procedures, **functions**, views and triggers, whose DEFINER is checked against
  the current account before the first `DROP`: recreating one owned by another
  account needs `SET_USER_ID`/`SUPER`, and there is no rolling back a dropped
  trigger.
- Both spellings of the old charset: `information_schema` reports it as `utf8`
  before MySQL 8.0.28 and `utf8mb3` after. Matching one converts nothing and
  reports success.
- MyISAM → InnoDB first (MyISAM caps a key at 1000 bytes against InnoDB's 3072,
  and an indexed `varchar(255)` needs 1020), leaving FULLTEXT tables alone.
- Index keys that will not fit once widened, named **before** anything is
  altered rather than found from a failed `ALTER` halfway down the list: an
  indexed `latin1 varchar(1000)` fits in 1000 bytes today and needs 4000 in mb4.
  Both caps are checked, MyISAM's 1000 for the FULLTEXT tables that stay there
  and InnoDB's 3072 for everything else.
- Column `DEFAULT`s are read from `SHOW CREATE TABLE`, not from
  `information_schema`, which renders a 4-byte character in a default as `?` —
  re-quoting that value destroys the default while reporting success.
- `ALTER DATABASE`, or a later `CREATE TABLE` with no explicit charset inherits
  the old default.
- Stored programs. A procedure's parameters keep the charset written into their
  declaration, so one called from a trigger goes on mangling 4-byte characters
  long after every table was converted.
- Legacy `'0000-00-00'` defaults, which abort an unrelated table's rebuild
  because `ALTER` re-validates every column.
- `STRICT_ALL_TABLES` when narrowing back to mb3, so a rollback fails instead of
  silently sweeping every emoji out of the database.

Four things it does NOT do for you:

- **Both sides of a foreign key must be converted in the same run.** The session
  runs with `foreign_key_checks = 0` — without it a table could not be converted
  at all — so a partial run can leave an FK whose two columns disagree on
  collation, and that only surfaces later, on a DML or the next `ALTER`.
- **The tables this package creates in code have no `sql/` dump**, so the bundled
  migration's table list misses them: `di_migrations_log`, `configuration`,
  `di_actions_log` and every `search_index_*`. A fresh install builds them on the
  configured charset, but an existing one keeps them where they were — convert
  them with the rest of your schema.
- **Collations other than `_bin` and `_cs` are normalised onto the target.** A
  column deliberately on `utf8mb4_unicode_ci` becomes `utf8mb4_general_ci`, which
  changes which values compare equal (accents, `ß`/`ss`). Case sensitivity is
  preserved — `_bin` and a `_cs` collation already on the target charset are left
  alone, since flattening those would collide values differing only in case under
  a UNIQUE index.
- **Plan for downtime, and stop writes.** Changing a charset is
  `ALGORITHM=COPY`: a full table rebuild under an exclusive metadata lock, so
  writes to each table block for its duration. Worse for triggers — a rebuild is
  `DROP` then `CREATE` with no lock in between, so a write landing in that window
  runs WITHOUT the trigger. For a denormalising or cache-filling trigger that is
  silent data divergence, not just downtime. Migrations usually run unattended
  via `up_last_not_executed`; make sure that is not happening under traffic.
  If the `CREATE` half fails, the original definition is put back before the
  error is reported, so a failed run does not also cost you the trigger; when
  even that fails — a trigger the server accepted once and refuses now, e.g. one
  still naming a column a later migration dropped — the error says so outright
  and carries the full DDL to recreate by hand.

The bundled migration picks its tables by dump filename, so a consumer table that
merely shares a name with one of this package's (`order`, `content`, `news`,
`tags`, `photos`, `comments`, `videos`, `feedback`, `searches`) is converted too.
Usually what you want — converging on one charset — but worth knowing.

**And it converts those tables ONLY — not the stored programs attached to them.**
`rebuildStoredPrograms()` is schema-wide by nature, so the bundled migration
deliberately leaves it to you. The consequence is easy to miss: a trigger of
yours on, say, `content` keeps the charset context it was created under, so it
goes on truncating 4-byte characters written *through it* long after the column
underneath became mb4. If you have triggers, views or routines touching any of
the tables above, convert them in your own migration — the example earlier in
this section does exactly that.
