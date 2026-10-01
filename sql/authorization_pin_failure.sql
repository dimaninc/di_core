CREATE TABLE IF NOT EXISTS authorization_pin_failure
(
    id          bigint auto_increment,
    ip          varchar(45) not null,
    purpose     smallint    not null,
    target_hash char(64)    not null,
    user_id     bigint      default null,
    created_at  timestamp   default CURRENT_TIMESTAMP,
    key idx_pair (ip, target_hash, created_at),
    key idx_created (created_at),
    primary key (id)
)
    DEFAULT CHARSET = 'utf8mb4'
    COLLATE = 'utf8mb4_general_ci'
    ENGINE = InnoDB;
