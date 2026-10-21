<?php

namespace diCore\Queue;

/**
 * A transaction left open by a task (a handler, a scheduler task): rolled back after the run,
 * or the next run would go inside it – never committing, invisible to others, aborted
 * (25P02) after any failed query.
 */
class OpenTransaction
{
    /** @return bool there was one */
    public static function rollBack(\PDO $pdo, ?\diDB $db = null): bool
    {
        $open = false;

        // Through the core first, down to zero: a raw ROLLBACK would leave its nesting counter
        // as it was, and the next startTransaction() in this process would make a SAVEPOINT
        // outside a transaction – every query committing by itself, a rollback rolling back
        // nothing. A level whose transaction was closed past the core (exec('COMMIT')) makes
        // PDO::rollBack() throw; the counter is already decremented by then, so go on.
        while ($db && $db->getTransactionNestingLevel() > 0) {
            try {
                $db->rollbackTransaction();
            } catch (\Throwable $e) {
            }

            $open = true;
        }

        // The rest is a BEGIN past the core: pdo_pgsql asks libpq for inTransaction and sees it.
        if ($pdo->inTransaction()) {
            $pdo->exec('ROLLBACK');
            $open = true;
        }

        return $open;
    }
}
