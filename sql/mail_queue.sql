CREATE TABLE IF NOT EXISTS mail_queue (
  id           BIGINT NOT NULL AUTO_INCREMENT,
  date         TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  sender       VARCHAR(255),
  recipient    VARCHAR(255),
  recipient_id BIGINT          DEFAULT '0',
  reply_to     VARCHAR(255)    DEFAULT '',
  subject      VARCHAR(255),
  body         MEDIUMTEXT,
  plain_body   TINYINT         DEFAULT '1',
  attachment   MEDIUMBLOB,
  incut_ids    VARCHAR(20)     DEFAULT '',
  visible      TINYINT         DEFAULT '1',
  sent         TINYINT         DEFAULT '0',
  news_id      BIGINT          DEFAULT '0',
  settings     TEXT,
  PRIMARY KEY (id),
  KEY idx(visible, sent)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_general_ci;
