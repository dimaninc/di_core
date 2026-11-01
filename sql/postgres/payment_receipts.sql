CREATE TABLE IF NOT EXISTS payment_receipts
(
    id                    bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    target_type           int,
    target_id             bigint,
    user_id               bigint,
    pay_system            smallint,
    vendor                smallint         DEFAULT 0,
    currency              smallint,
    amount                numeric          DEFAULT 0,
    rnd                   text             DEFAULT '',
    outer_number          text             DEFAULT '',
    date_reserved         timestamptz      DEFAULT NULL,
    date_payed            timestamptz      DEFAULT CURRENT_TIMESTAMP,
    date_uploaded         timestamptz      DEFAULT NULL,
    fiscal_mark           text             DEFAULT '',
    fiscal_doc_id         text             DEFAULT '',
    fiscal_date           timestamptz      DEFAULT NULL,
    fiscal_session        int              DEFAULT 0,
    fiscal_number         int              DEFAULT 0,
    fiscal_mark_kkt       text             DEFAULT '',
    fiscal_fn_number      text             DEFAULT '',
    fiscal_kkt_reg_number text             DEFAULT '',
    fiscal_total          bigint           DEFAULT NULL,
    draft_id              bigint,
    ip                    bigint           DEFAULT 0,
    partner_code_id       int              DEFAULT 0
);

CREATE UNIQUE INDEX IF NOT EXISTS idx__payment_receipts__draft
    ON payment_receipts (draft_id);

CREATE INDEX IF NOT EXISTS idx__payment_receipts__target
    ON payment_receipts (target_type, target_id);

CREATE INDEX IF NOT EXISTS idx__payment_receipts__user
    ON payment_receipts (user_id);

CREATE INDEX IF NOT EXISTS idx__payment_receipts__date_payed
    ON payment_receipts (date_payed);

CREATE INDEX IF NOT EXISTS idx__payment_receipts__pending
    ON payment_receipts (date_payed) WHERE date_uploaded IS NULL;

CREATE INDEX IF NOT EXISTS idx__payment_receipts__fiscal_doc
    ON payment_receipts (fiscal_doc_id);
