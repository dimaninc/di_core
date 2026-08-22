CREATE TABLE IF NOT EXISTS tag_links(
  target_type int,
  target_id bigint,
  tag_id bigint,
  key idx(target_type,target_id,tag_id)
)
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
