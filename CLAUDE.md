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

- DB columns are `snake_case`; magic accessors are `CamelCase` (e.g., `order_num` → `getOrderNum()`)
- In old code image fields come in groups: `{name}`, `{name}_w`, `{name}_h`, `{name}_t` (filename, width, height, type), lately this changed to only `{name}` storing.
- Entity `const type` must match `diTypes` integer constant
- Model `$publicFields` controls which fields appear in API/public output
- Model `$fieldTypes` maps columns to `FieldType` enum (json, date, etc.)
- Model `$picStoreSettings` configures image upload/resize behavior
- Collections are lazy-loaded — query executes on first iteration/count
- Admin pages are registered as modules in the admin menu system
