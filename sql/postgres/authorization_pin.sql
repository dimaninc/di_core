CREATE TABLE IF NOT EXISTS authorization_pin
(
    id         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id    bigint,
    purpose    smallint     NOT NULL,
    channel    smallint     NOT NULL,
    target     varchar(255) NOT NULL,
    code_hash  char(64)     NOT NULL,
    status     smallint     NOT NULL DEFAULT 0,
    attempts   smallint     NOT NULL DEFAULT 0,
    payload    jsonb,
    ip         varchar(45),
    expired_at timestamp    NOT NULL,
    created_at timestamp    DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp    DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx__authorization_pin__target
    ON authorization_pin (purpose, target, created_at);

CREATE INDEX IF NOT EXISTS idx__authorization_pin__hash
    ON authorization_pin (code_hash);

CREATE INDEX IF NOT EXISTS idx__authorization_pin__ip
    ON authorization_pin (ip, purpose, created_at);

CREATE INDEX IF NOT EXISTS idx__authorization_pin__created
    ON authorization_pin (created_at);
