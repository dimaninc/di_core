CREATE TABLE module_cache (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,

  title                VARCHAR(255) DEFAULT '',
  module_id            VARCHAR(50),
  query_string         VARCHAR(255),
  bootstrap_settings   VARCHAR(255) DEFAULT '',
  update_every_minutes BIGINT,

  content              MEDIUMTEXT,

  created_at           TIMESTAMP             DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME,
  active               TINYINT               DEFAULT '1',

  KEY idx(module_id, active, updated_at, update_every_minutes),
  KEY search_idx(module_id,query_string,bootstrap_settings),
  PRIMARY KEY (id)
)
  DEFAULT CHARSET = 'utf8'
  COLLATE = 'utf8_general_ci'
  ENGINE = InnoDB;
