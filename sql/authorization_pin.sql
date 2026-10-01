CREATE TABLE IF NOT EXISTS authorization_pin
(
    id         bigint auto_increment,
    user_id    bigint       default null,
    purpose    smallint     not null,
    channel    smallint     not null,
    target     varchar(255) not null,
    code_hash  char(64)     not null,
    status     tinyint      not null default 0,
    attempts   tinyint      not null default 0,
    payload    json         default null,
    ip         varchar(45)  default null,
    expired_at datetime     not null,
    created_at timestamp    default CURRENT_TIMESTAMP,
    updated_at timestamp    not null default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
    key idx_target (purpose, target, created_at),
    key idx_hash (code_hash),
    key idx_ip (ip, purpose, created_at),
    key idx_created (created_at),
    primary key (id)
)
    DEFAULT CHARSET = 'utf8mb4'
    COLLATE = 'utf8mb4_general_ci'
    ENGINE = InnoDB;
