CREATE TABLE IF NOT EXISTS search_results (
  search_id BIGINT,
  id        BIGINT,
  rel       TINYINT UNSIGNED,
  KEY idx(search_id)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;
