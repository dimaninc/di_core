-- Task queue (diCore\Queue\PgQueue). The list of queues is the project's: it adds
-- CHECK (queue IN (...)) matching Setup::queues() and the notify trigger (job_notify.sql).
-- The name job is Setup::TABLE's default; a project whose job is taken (a job board) sets its
-- own TABLE and renames the table and the idx__job__* indexes in its copy of this DDL.
CREATE TABLE IF NOT EXISTS job
(
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    queue        text        NOT NULL,
    -- Tasks of one key run strictly by id; NULL – no order.
    order_key    text,
    payload      jsonb       NOT NULL,
    status       text        NOT NULL DEFAULT 'queued' CHECK (status IN ('queued', 'running', 'done', 'failed')),
    attempts     smallint    NOT NULL DEFAULT 0,
    run_at       timestamptz NOT NULL DEFAULT now(),
    locked_until timestamptz,
    error        text,
    created_at   timestamptz NOT NULL DEFAULT now(),
    finished_at  timestamptz
);

-- Reserving and purging finished tasks (PgQueue::purgeFinished(): age by run_at, with the queue list).
CREATE INDEX IF NOT EXISTS idx__job__pick ON job (queue, status, run_at);

-- "Is there an earlier unfinished one of the same key".
CREATE INDEX IF NOT EXISTS idx__job__order ON job (order_key, id)
    WHERE order_key IS NOT NULL AND status IN ('queued', 'running');

-- Admin lists filtered by status / by queue, newest first.
CREATE INDEX IF NOT EXISTS idx__job__status ON job (status, id);
CREATE INDEX IF NOT EXISTS idx__job__queue ON job (queue, id);
