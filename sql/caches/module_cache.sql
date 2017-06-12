CREATE TABLE module_cache (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,

  module_id            VARCHAR(50),
  query_string         VARCHAR(255),
  update_every_minutes BIGINT,

  content              MEDIUMTEXT,

  created_at           TIMESTAMP             DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME,
  active               TINYINT               DEFAULT '1',

  KEY idx(module_id, active, updated_at, update_every_minutes),
  PRIMARY KEY (id)
)
  DEFAULT CHARSET = 'utf8'
  COLLATE = 'utf8_general_ci'
  ENGINE = InnoDB;
