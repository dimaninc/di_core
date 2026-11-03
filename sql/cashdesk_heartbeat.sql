CREATE TABLE IF NOT EXISTS cashdesk_heartbeat
(
    id          bigint   auto_increment,
    payload     json     not null,
    received_at datetime not null,
    key idx_received (received_at),
    primary key (id)
)
    DEFAULT CHARSET = 'utf8mb4'
    COLLATE = 'utf8mb4_general_ci'
    ENGINE = InnoDB;
