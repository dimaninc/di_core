CREATE TABLE IF NOT EXISTS mail_plans
(
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    target_type  INT         DEFAULT '0',
    target_id    BIGINT      NOT NULL DEFAULT '0',
    mode         smallint,
    conditions   TEXT,
    created_at   timestamptz DEFAULT CURRENT_TIMESTAMP,
    started_at   timestamptz NULL     DEFAULT NULL,
    processed_at timestamptz NULL     DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx__mail_plans
    ON mail_plans (target_type, target_id, mode, started_at, processed_at);
