CREATE TABLE IF NOT EXISTS tags
(
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    slug             text,
    slug_source      text        default '',
    title            text,
    content          text,
    pic              text        default '',
    weight           int         default '0',
    html_title       text        default '',
    html_keywords    text        default '',
    html_description text        default '',
    visible          smallint    default '1',
    date             timestamptz default CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx__tags
    ON tags (weight, visible, title, date);

CREATE UNIQUE INDEX IF NOT EXISTS idx__tags__slug
    ON tags (slug);
