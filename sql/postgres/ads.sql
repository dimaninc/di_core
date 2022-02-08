CREATE TABLE IF NOT EXISTS ads
(
    id                 SERIAL PRIMARY KEY,
    block_id           INT,
    category_id        INT          DEFAULT '0',
    title              VARCHAR(255),
    content            TEXT,
    href               VARCHAR(255),
    href_target        smallint     DEFAULT '0',
    onclick            VARCHAR(255) DEFAULT '',
    button_color       VARCHAR(10)  DEFAULT '',
    transition         smallint     DEFAULT '0',
    transition_style   smallint     DEFAULT '0',
    duration_of_show   INT          DEFAULT '-1',
    duration_of_change INT          DEFAULT '-1',
    pic                VARCHAR(40)  DEFAULT '',
    pic_w              INT          DEFAULT '0',
    pic_h              INT          DEFAULT '0',
    visible            smallint     DEFAULT '1',
    order_num          INT,
    date               TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    show_date1         DATE NULL    DEFAULT NULL,
    show_date2         DATE NULL    DEFAULT NULL,
    show_time1         TIME NULL    DEFAULT NULL,
    show_time2         TIME NULL    DEFAULT NULL,
    show_on_weekdays   VARCHAR(50)  DEFAULT '',
    show_on_holidays   smallint     DEFAULT 0
);

CREATE INDEX idx__ads
    ON ads (block_id, category_id, show_date1, show_date2, show_time1, show_time2, show_on_weekdays, show_on_holidays,
            visible, order_num);
