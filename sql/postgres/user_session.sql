CREATE TABLE IF NOT EXISTS user_session
(
    id         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    token      text,
    user_id    int,
    user_agent text        default '',
    ip         cidr,
    created_at timestamptz default CURRENT_TIMESTAMP,
    updated_at timestamptz default CURRENT_TIMESTAMP,
    seen_at    timestamptz default NULL
);

CREATE INDEX IF NOT EXISTS idx__user_session__main
    ON user_session (user_id, created_at, updated_at);

CREATE UNIQUE INDEX IF NOT EXISTS idx__user_session__token
    ON user_session (token);
