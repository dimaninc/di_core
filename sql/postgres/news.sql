CREATE TABLE IF NOT EXISTS news
(
    id                 SERIAL PRIMARY KEY,
    clean_title        varchar(255),
    menu_title         varchar(255) default '',
    title              varchar(255),
    short_content      text,
    content            text,
    html_title         varchar(255) default '',
    html_keywords      varchar(255) default '',
    html_description   varchar(255) default '',
    pic                varchar(50)  default '',
    pic_w              int          default '0',
    pic_h              int          default '0',
    pic_t              int          default '0',
    pic_tn_w           int          default '0',
    pic_tn_h           int          default '0',
    date               TIMESTAMP,
    visible            smallint     default '1',
    order_num          bigint,
    karma              int          default '0',
    comments_count     int          default '0',
    comments_last_date TIMESTAMP    default NULL,
    comments_enabled   smallint     default '1'
);

CREATE INDEX IF NOT EXISTS idx__news
    ON news (visible, order_num, date);

CREATE UNIQUE INDEX IF NOT EXISTS idx__news__clean_title
    ON news (clean_title);
