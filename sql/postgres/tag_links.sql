CREATE TABLE IF NOT EXISTS tag_links
(
    target_type int,
    target_id   bigint,
    tag_id      bigint
);

CREATE INDEX IF NOT EXISTS idx__tag_links
    ON tag_links (target_type, target_id, tag_id);
