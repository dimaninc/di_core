dimaninc Core library
=========================

Upgrade notes
-------------

### utf8mb4

The shipped `sql/*.sql` dumps are `utf8mb4` / `utf8mb4_general_ci`. utf8(mb3)
cannot store 4-byte characters (emoji); unless `sql_mode` is strict, MySQL
truncates the value at the first one **silently**, so text is lost with no error
anywhere.

**The connection charset still defaults to mb3** — raising it would put an mb4
connection over the mb3 schema of every existing consumer on a mere
`composer update`. So:

- **New project:** set `utf8mb4` right away in your own `Data\Config`:
  ```php
  const dbEncoding = 'utf8mb4';
  const dbCollation = 'utf8mb4_general_ci';
  ```
- **Existing project:** leave it on mb3 until you convert. Order matters —
  convert the schema first, switch the connection second; an mb4 connection over
  mb3 columns keeps truncating.

Requires **MySQL 5.7.9+** (InnoDB `DYNAMIC` row format). The `slugs` and
`localization` dumps index a `varchar(255)`, which needs 1020 bytes in mb4 — over
the 767-byte limit of the older `COMPACT` format.

#### Converting an existing schema

`charset/20260728100000` converts the tables this package ships. **The rest of
your schema is yours to convert** — `diCore\Database\Tool\CharsetConverter` is
the same engine:

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
- `ALTER DATABASE`, or a later `CREATE TABLE` with no explicit charset inherits
  the old default.
- Stored programs. A procedure's parameters keep the charset written into their
  declaration, so one called from a trigger goes on mangling 4-byte characters
  long after every table was converted.
- Legacy `'0000-00-00'` defaults, which abort an unrelated table's rebuild
  because `ALTER` re-validates every column.
- `STRICT_ALL_TABLES` when narrowing back to mb3, so a rollback fails instead of
  silently sweeping every emoji out of the database.
