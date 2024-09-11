CREATE TABLE IF NOT EXISTS additional_variable_value
(
    id                   INT AUTO_INCREMENT,
    target_id            INT,
    additional_variable_id INT,
    value                TEXT,
    created_at           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY idx (additional_variable_id, target_id),
    PRIMARY KEY (id)
)
    DEFAULT CHARSET = 'utf8'
    COLLATE = 'utf8_general_ci'
    ENGINE = InnoDB;
