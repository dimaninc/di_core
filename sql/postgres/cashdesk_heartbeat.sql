CREATE TABLE IF NOT EXISTS cashdesk_heartbeat
(
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    payload     jsonb       NOT NULL,
    received_at timestamptz NOT NULL
);

CREATE INDEX IF NOT EXISTS idx__cashdesk_heartbeat__received
    ON cashdesk_heartbeat (received_at);
