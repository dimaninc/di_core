CREATE TABLE IF NOT EXISTS authorization_pin
(
    id         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id    bigint,
    purpose    smallint    NOT NULL,
    channel    smallint    NOT NULL,
    target     text        NOT NULL,
    code_hash  text        NOT NULL CHECK (code_hash ~ '^[0-9a-f]{64}$'),
    status     smallint    NOT NULL DEFAULT 0,
    attempts   smallint    NOT NULL DEFAULT 0,
    payload    jsonb,
    ip         text,
    expired_at timestamptz NOT NULL,
    created_at timestamptz DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamptz NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx__authorization_pin__target
    ON authorization_pin (purpose, target, created_at);

CREATE INDEX IF NOT EXISTS idx__authorization_pin__hash
    ON authorization_pin (code_hash);

CREATE INDEX IF NOT EXISTS idx__authorization_pin__ip
    ON authorization_pin (ip, purpose, created_at);

CREATE INDEX IF NOT EXISTS idx__authorization_pin__created
    ON authorization_pin (created_at);
