CREATE TABLE IF NOT EXISTS localization
(
    id       bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name     text NOT NULL,
    value    TEXT,
    en_value TEXT
);

CREATE UNIQUE INDEX IF NOT EXISTS idx__localization__name
    ON localization (name);
