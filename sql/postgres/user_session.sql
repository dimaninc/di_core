CREATE TABLE IF NOT EXISTS user_session
(
    id         SERIAL PRIMARY KEY,
    token      varchar(32),
    user_id    int,
    user_agent varchar(255) default '',
    ip         cidr,
    created_at timestamp    default CURRENT_TIMESTAMP,
    updated_at timestamp    default CURRENT_TIMESTAMP,
    seen_at    timestamp    default NULL
);

CREATE INDEX IF NOT EXISTS idx__user_session__main
    ON user_session (user_id, created_at, updated_at);

CREATE UNIQUE INDEX IF NOT EXISTS idx__user_session__token
    ON user_session (token);
