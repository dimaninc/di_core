CREATE TABLE IF NOT EXISTS redirects (
  id INT NOT NULL AUTO_INCREMENT,
  old_url VARCHAR(255),
  new_url VARCHAR(255),
  status INT DEFAULT 301,
  active TINYINT DEFAULT '1',
  date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx(old_url, status, active),
  PRIMARY KEY (id)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;
