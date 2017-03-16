CREATE TABLE IF NOT EXISTS searches (
  id   BIGINT NOT NULL AUTO_INCREMENT,
  t    VARCHAR(20),
  date INT UNSIGNED,
  KEY idx(date),
  PRIMARY KEY (id)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_general_ci;