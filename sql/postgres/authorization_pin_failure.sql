CREATE TABLE IF NOT EXISTS authorization_pin_failure
(
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ip          text        NOT NULL,
    purpose     smallint    NOT NULL,
    target_hash text        NOT NULL CHECK (target_hash ~ '^[0-9a-f]{64}$'),
    user_id     bigint,
    created_at  timestamptz DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx__authorization_pin_failure__pair
    ON authorization_pin_failure (ip, target_hash, created_at);

CREATE INDEX IF NOT EXISTS idx__authorization_pin_failure__created
    ON authorization_pin_failure (created_at);
