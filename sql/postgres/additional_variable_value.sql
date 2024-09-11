CREATE TABLE IF NOT EXISTS additional_variable_value
(
    id          SERIAL PRIMARY KEY,
    target_id   INT,
    variable_id INT,
    value       TEXT,
    created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NULL DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx__additional_variable_value__name
    ON additional_variable_value (variable_id, target_id);
