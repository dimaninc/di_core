CREATE TABLE IF NOT EXISTS cart (
  id         BIGINT NOT NULL AUTO_INCREMENT,
  session_id VARCHAR(32)     DEFAULT '',
  user_id    BIGINT,
  created_at TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  KEY idx(session_id, user_id),
  PRIMARY KEY (id)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_general_ci;
