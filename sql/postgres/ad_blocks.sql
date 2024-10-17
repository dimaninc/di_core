CREATE TABLE IF NOT EXISTS ad_blocks
(
    id                    SERIAL PRIMARY KEY,
    purpose               int,
    target_type           int,
    target_id             int,
    title                 varchar(255),
    default_slide_title   varchar(255) default '',
    default_slide_content text,
    properties            jsonb,
    transition            smallint     default '0',
    transition_style      smallint     default '0',
    duration_of_show      int          default '0',
    duration_of_change    int          default '0',
    slides_order          smallint     default '0',
    ignore_hover_hold     smallint     default '0',
    visible               smallint     default '1',
    order_num             int,
    date                  timestamp    default CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx__ad_blocks
    ON ad_blocks (purpose, target_type, target_id, visible, order_num);
