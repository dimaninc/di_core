CREATE TABLE IF NOT EXISTS content
(
    id                 SERIAL PRIMARY KEY,
    parent             integer      default '-1',
    clean_title        varchar(255),
    menu_title         varchar(255) default '',
    type               varchar(32),
    title              varchar(255),
    caption            varchar(255) default '',
    html_title         text,
    html_keywords      text,
    html_description   text,
    content            text,
    short_content      text,
    links_content      text,
    pic                varchar(32)  default '',
    pic_w              integer      default '0',
    pic_h              integer      default '0',
    pic_t              integer      default '0',
    pic2               varchar(32)  default '',
    pic2_w             integer      default '0',
    pic2_h             integer      default '0',
    pic2_t             integer      default '0',
    ico                varchar(50)  default '',
    ico_w              integer      default '0',
    ico_h              integer      default '0',
    ico_t              integer      default '0',
    color              varchar(32)  default '',
    background_color   varchar(32)  default '',
    class              varchar(32)  default '',
    menu_class         varchar(20)  default '',
    level_num          smallint     default '0',
    visible            smallint     default '0',
    visible_top        smallint     default '0',
    visible_bottom     smallint     default '0',
    visible_left       smallint     default '0',
    visible_right      smallint     default '0',
    visible_logged_in  smallint     default '0',
    to_show_content    smallint     default '1',
    order_num          integer,
    top                smallint     default '0',
    comments_count     integer      default '0',
    comments_last_date timestamp    default null,
    comments_enabled   smallint     default '1',
    ad_block_id        integer      default '0'
);

CREATE UNIQUE INDEX IF NOT EXISTS idx__content__clean_title
    ON content (clean_title);

CREATE INDEX IF NOT EXISTS idx__content__main
    ON content (visible, visible_top, visible_bottom, visible_left, visible_right, visible_logged_in, order_num, parent,
                level_num);
