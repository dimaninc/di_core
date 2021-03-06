CREATE TABLE order_item (
  id          BIGINT(20) NOT NULL AUTO_INCREMENT,
  order_id    BIGINT(20),
  target_type INT,
  target_id   BIGINT(20),
  price       FLOAT,
  orig_price  FLOAT,
  quantity    INT                 DEFAULT '1',
  status      TINYINT(4)          DEFAULT '0',
  created_at  TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
  data        TEXT COMMENT 'JSON with item details if needed',
  PRIMARY KEY (id),
  KEY order_id(order_id, target_type, target_id, price, status)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_general_ci;
