CREATE TABLE IF NOT EXISTS tags
(
    id               SERIAL PRIMARY KEY,
    slug             varchar(255),
    slug_source      varchar(255) default '',
    title            varchar(255),
    content          text,
    pic              varchar(40)  default '',
    weight           int          default '0',
    html_title       varchar(255) default '',
    html_keywords    varchar(255) default '',
    html_description varchar(255) default '',
    visible          smallint     default '1',
    date             timestamp    default CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx__tags
    ON tags (weight, visible, title, date);

CREATE UNIQUE INDEX IF NOT EXISTS idx__tags__slug
    ON tags (slug);
