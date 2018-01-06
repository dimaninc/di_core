CREATE TABLE order_status (
  id        INT NOT NULL AUTO_INCREMENT,
  title     VARCHAR(255),
  color     VARCHAR(20),
  css_class VARCHAR(50),
  type      TINYINT      DEFAULT '0',
  visible   TINYINT      DEFAULT '1',
  order_num INT,
  PRIMARY KEY (id),
  KEY visible(visible, order_num)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_general_ci;

