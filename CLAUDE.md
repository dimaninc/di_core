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

### Admin panel

- **`Admin\Base`** — Admin shell, menu, routing. URL pattern: `/_admin/{module}/{method}/{id}`
- **`Admin\BasePage`** — CRUD base for list/add/edit. Subclassed per entity in `Admin\Page\{Name}`
- **`Admin\Form`** — Form field definitions and rendering
- **`Admin\Submit`** — Form processing, file/image uploads
- **`Admin\Grid`** — List/table display with sorting and columns

### Templating

Primary: Twig (`.html.twig` in `templates/`). Legacy: FastTemplate (`.html` in `tpl/`). Core templates use `@core` namespace. Twig cache: `_cfg/cache/twig/`.

### Database

Supports MySQL (primary), PostgreSQL, SQLite, MongoDB. Schema files in `sql/` with engine-specific variants in `sql/postgres/`, `sql/sqlite/`. Connection managed by `diCore\Database\Connection`.

### Migrations

Files in `_cfg/migrations/` (in consuming project), format `{idx}_{name}.php`. Extend `diCore\Database\Tool\Migration`. Must implement `up()` and `down()`. Tracked in `di_migrations_log` table. Managed via `MigrationsManager`.

## Conventions

- **Use `Model::createById($id)` and `Model::createBySlug($slug)` instead of `Model::create($id)`**. The generic `create()` is ambiguous; always prefer the explicit factory methods.
- DB columns are `snake_case`; magic accessors are `CamelCase` (e.g., `order_num` → `getOrderNum()`)
- In old code image fields come in groups: `{name}`, `{name}_w`, `{name}_h`, `{name}_t` (filename, width, height, type), lately this changed to only `{name}` storing.
- Entity `const type` must match `diTypes` integer constant
- Model `$publicFields` controls which fields appear in API/public output
- Model `$fieldTypes` maps columns to `FieldType` enum (json, date, etc.)
- Model `$picStoreSettings` configures image upload/resize behavior
- Collections are lazy-loaded — query executes on first iteration/count
- Admin pages are registered as modules in the admin menu system

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
- InnoDB engine, `DEFAULT CHARSET=utf8`, `COLLATE=utf8_general_ci`
- `id` is `BIGINT AUTO_INCREMENT` primary key (or `INT` for small tables)
- Columns use `snake_case`
- `created_at` → `TIMESTAMP DEFAULT CURRENT_TIMESTAMP`
- `updated_at` → `TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`
- Named indexes (`idx`, `slug_idx`, `target_idx`, etc.)

### Step 2: Create a migration

Create `_cfg/migrations/{optional_subfolder}/{timestamp}_{name}.php`.

Generate a timestamp: `date +%Y%m%d%H%M%S` (e.g., `20260214120000`).

```php
<?php
class diMigration_20260214120000 extends \diCore\Database\Tool\Migration
{
    public static $idx = '20260214120000';
    public static $name = 'My entity';

    public function up()
    {
        $folder = \diCore\Controller\Db::getDumpsFolder() . 'tables/';
        $this->executeSqlFile(['my_entity.sql'], $folder);
    }

    public function down()
    {
    }
}
```

**Conventions:**
- Class name: `diMigration_{timestamp}`
- `$idx` matches the timestamp in the filename
- `$name` is a human-readable description
- For new tables: use `$this->executeSqlFile()` pointing to the SQL file
- For alterations: use `$this->getDb()->q("ALTER TABLE ...")`
- Migrations can live in subdirectories for grouping (e.g., `_cfg/migrations/payments/`)

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

### Step 5 (Optional): Generate an admin page

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
