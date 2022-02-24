CREATE TABLE IF NOT EXISTS admin_table_edit_log(
    id           SERIAL PRIMARY KEY,
    target_table varchar(64),
    target_id    bigint,
    admin_id     int,
    old_data     text,
    new_data     text,
    created_at   timestamp default CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx__admin_table_edit_log
    ON admin_table_edit_log (target_table, target_id, admin_id, created_at);
