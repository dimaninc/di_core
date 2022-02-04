CREATE TABLE IF NOT EXISTS mail_queue
(
    id           SERIAL PRIMARY KEY,
    date         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    sender       VARCHAR(255),
    recipient    VARCHAR(255),
    recipient_id BIGINT       DEFAULT '0',
    reply_to     VARCHAR(255) DEFAULT '',
    subject      VARCHAR(255),
    body         TEXT,
    plain_body   smallint      DEFAULT '1',
    attachment   BYTEA,
    incut_ids    VARCHAR(20)  DEFAULT '',
    visible      smallint      DEFAULT '1',
    sent         smallint      DEFAULT '0',
    news_id      BIGINT       DEFAULT '0',
    settings     TEXT
);

CREATE INDEX idx__mail_queue
    ON mail_queue (visible, sent);
