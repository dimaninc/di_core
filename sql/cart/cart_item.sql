CREATE TABLE IF NOT EXISTS cart_item (
  id          BIGINT NOT NULL AUTO_INCREMENT,
  cart_id     BIGINT,
  target_type INT,
  target_id   BIGINT,
  price       FLOAT,
  quantity    INT             DEFAULT '1',
  data        TEXT COMMENT 'JSON with item details if needed',
  created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  KEY idx(cart_id, target_type, target_id),
  PRIMARY KEY (id)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_general_ci;
