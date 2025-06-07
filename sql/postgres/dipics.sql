CREATE TABLE IF NOT EXISTS dipics
(
    id         SERIAL PRIMARY KEY,
    _table     varchar(100) default '',
    _field     varchar(100) default '',
    _id        bigint,
    target_id  bigint       default '0',
    title      varchar(255),
    content    text,
    orig_fn    varchar(100) default '',
    pic        varchar(50),
    pic_t      int          default '0',
    pic_w      int,
    pic_h      int,
    pic_tn     varchar(50)  default '',
    pic_tn_t   int          default '0',
    pic_tn_w   int,
    pic_tn_h   int,
    pic_tn2_t  int          default '0',
    pic_tn2_w  int,
    pic_tn2_h  int,
    date       timestamp    default CURRENT_TIMESTAMP,
    by_default smallint     default '0',
    visible    smallint     default '1',
    order_num  bigint
);

CREATE INDEX IF NOT EXISTS idx__dipics__main
    ON dipics (_table, _id, _field, by_default);

CREATE INDEX IF NOT EXISTS idx__dipics__visible
    ON dipics (visible, order_num, date, _table, _field, _id);
