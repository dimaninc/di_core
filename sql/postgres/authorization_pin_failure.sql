CREATE TABLE IF NOT EXISTS authorization_pin_failure
(
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    ip          varchar(45) NOT NULL,
    purpose     smallint    NOT NULL,
    target_hash char(64)    NOT NULL,
    user_id     bigint,
    created_at  timestamp   DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx__authorization_pin_failure__pair
    ON authorization_pin_failure (ip, target_hash, created_at);

CREATE INDEX IF NOT EXISTS idx__authorization_pin_failure__created
    ON authorization_pin_failure (created_at);
