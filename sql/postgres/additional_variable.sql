CREATE TABLE IF NOT EXISTS additional_variable
(
    id            SERIAL PRIMARY KEY,
    target_type   INT,
    name          VARCHAR(255),
    properties    JSON, # description, type, default_value
    order_num     INT,
    active        SMALLINT,
    created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NULL DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx__additional_variable
    ON additional_variable (order_num, active);

CREATE UNIQUE INDEX IF NOT EXISTS idx__additional_variable__name
    ON additional_variable (target_type, name);
