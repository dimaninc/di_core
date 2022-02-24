CREATE TABLE IF NOT EXISTS mail_incuts
(
    id          SERIAL PRIMARY KEY,
    target_type int             DEFAULT '0',
    target_id   BIGINT NOT NULL DEFAULT '0',
    type        smallint,
    content     BYTEA
);

CREATE INDEX IF NOT EXISTS idx__mail_incuts
    ON mail_incuts (target_type, target_id, type);

