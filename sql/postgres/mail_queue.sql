CREATE TABLE IF NOT EXISTS mail_queue
(
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    date         timestamptz DEFAULT CURRENT_TIMESTAMP,
    sender       text,
    recipient    text,
    recipient_id BIGINT      DEFAULT '0',
    reply_to     text        DEFAULT '',
    subject      text,
    body         TEXT,
    plain_body   smallint    DEFAULT '1',
    attachment   BYTEA,
    incut_ids    text        DEFAULT '',
    visible      smallint    DEFAULT '1',
    sent         smallint    DEFAULT '0',
    news_id      BIGINT      DEFAULT '0',
    settings     TEXT
);

CREATE INDEX IF NOT EXISTS idx__mail_queue
    ON mail_queue (visible, sent);
