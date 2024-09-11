CREATE TABLE IF NOT EXISTS additional_variable
(
    id            INT AUTO_INCREMENT,
    target_type   INT,
    name          VARCHAR(255),
    properties    JSON, # type, default_value
    order_num     INT,
    active        TINYINT,
    created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NULL DEFAULT NULL,
    INDEX idx (order_num, active),
    UNIQUE INDEX name_idx (target_type, name),
    PRIMARY KEY (id)
)
    DEFAULT CHARSET = 'utf8'
    COLLATE = 'utf8_general_ci'
    ENGINE = InnoDB;
