CREATE TABLE IF NOT EXISTS localization
(
    id       SERIAL PRIMARY KEY,
    name     varchar(255) NOT NULL,
    value    TEXT,
    en_value TEXT
);

CREATE INDEX IF NOT EXISTS idx__localization__name
    ON localization (name);
