CREATE TABLE `order` (
  id         INT     NOT NULL AUTO_INCREMENT,
  user_id    INT,
  type       TINYINT NULL     DEFAULT NULL,
  invoice    BIGINT  NULL     DEFAULT NULL,
  status     INT              DEFAULT '0',
  delivery   INT              DEFAULT '0',
  payment_id INT              DEFAULT '0',
  comments   TEXT,
  created_at TIMESTAMP        DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY invoice(invoice),
  KEY idx(user_id, invoice, status, payment_id, created_at)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_general_ci;