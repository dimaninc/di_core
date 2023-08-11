CREATE TABLE IF NOT EXISTS user_session
(
    id         bigint auto_increment,
    token      varchar(32),
    user_id    int,
    user_agent varchar(255) default '',
    ip         bigint,
    created_at timestamp    default CURRENT_TIMESTAMP,
    updated_at timestamp    default CURRENT_TIMESTAMP,
    seen_at    timestamp    default NULL,
    key idx (user_id, created_at, updated_at),
    unique token_idx (token),
    primary key (id)
)
    DEFAULT CHARSET = 'utf8'
    COLLATE = 'utf8_general_ci'
    ENGINE = InnoDB;
