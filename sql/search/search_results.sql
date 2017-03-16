CREATE TABLE IF NOT EXISTS search_results (
  search_id BIGINT,
  id        BIGINT,
  rel       TINYINT UNSIGNED,
  KEY idx(search_id)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_general_ci;