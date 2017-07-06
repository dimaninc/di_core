CREATE TABLE comment_cache (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,

  target_type          INT,
  target_id            INT,
  update_every_minutes BIGINT,

  html                 MEDIUMTEXT,

  created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME,

  UNIQUE KEY target(target_type, target_id),
  KEY idx(updated_at, update_every_minutes),
  PRIMARY KEY (id)
)
  DEFAULT CHARSET = 'utf8'
  COLLATE = 'utf8_general_ci'
  ENGINE = InnoDB;
