#!/usr/bin/env bash
/usr/bin/php vendor/dimaninc/di_core/php/admin/workers/cli.php controller=migration action=up_last_not_executed
