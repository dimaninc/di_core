CREATE TABLE IF NOT EXISTS mail_plans (
  id           BIGINT NOT NULL AUTO_INCREMENT,
  target_type  INT             DEFAULT '0',
  target_id    BIGINT NOT NULL DEFAULT '0',
  mode         TINYINT,
  conditions   TEXT,
  created_at   TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
  started_at   DATETIME NULL DEFAULT NULL,
  processed_at DATETIME NULL DEFAULT NULL,
  INDEX idx(target_type, target_id, mode, started_at, processed_at),
  PRIMARY KEY (id)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_general_ci;
