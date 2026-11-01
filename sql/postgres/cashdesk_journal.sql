CREATE TABLE IF NOT EXISTS cashdesk_journal
(
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    journal_id      text        NOT NULL
        CHECK (journal_id ~ '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'),
    seq             bigint      NOT NULL,
    source          text        DEFAULT '',
    receipt_id      bigint      DEFAULT NULL,
    state           text        DEFAULT '',
    document_number bigint      DEFAULT NULL,
    event_at        timestamptz DEFAULT NULL,
    payload         jsonb       NOT NULL,
    received_at     timestamptz NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx__cashdesk_journal__journal_seq
    ON cashdesk_journal (journal_id, seq);

CREATE INDEX IF NOT EXISTS idx__cashdesk_journal__receipt
    ON cashdesk_journal (source, receipt_id);
