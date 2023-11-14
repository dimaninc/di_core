CREATE TABLE IF NOT EXISTS comments
(
    id          SERIAL PRIMARY KEY,
    user_type   SMALLINT           default '0',
    user_id     INT       NOT NULL DEFAULT '0',
    owner_id    INT       NOT NULL DEFAULT '0',
    parent      INT       NOT NULL DEFAULT '0',
    target_type int,
    target_id   INT       NOT NULL DEFAULT '0',
    content     TEXT      NOT NULL,
    order_num   INT       NOT NULL,
    level_num   SMALLINT  NOT NULL DEFAULT '0',
    visible     SMALLINT  NOT NULL DEFAULT '1',
    moderated   SMALLINT  NOT NULL DEFAULT '1',
    karma       int                default '0',
    evil_score  int                default '0',
    ip          cidr,
    created_at  TIMESTAMP NULL     DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NULL     DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx__comment__user
    ON comments (user_type, user_id);

CREATE INDEX IF NOT EXISTS idx__comment__main
    ON comments (target_type, target_id, visible, moderated, order_num);
