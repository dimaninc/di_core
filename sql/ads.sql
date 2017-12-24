CREATE TABLE IF NOT EXISTS ads (
  id                 BIGINT       AUTO_INCREMENT,
  block_id           BIGINT,
  category_id        BIGINT       DEFAULT '0',
  title              VARCHAR(255),
  content            TEXT,
  href               VARCHAR(255),
  href_target        TINYINT      DEFAULT '0',
  onclick            VARCHAR(255) DEFAULT '',
  button_color       VARCHAR(10)  DEFAULT '',
  transition         TINYINT      DEFAULT '0',
  transition_style   TINYINT      DEFAULT '0',
  duration_of_show   INT          DEFAULT '-1',
  duration_of_change INT          DEFAULT '-1',
  pic                VARCHAR(40)  DEFAULT '',
  pic_w              INT(4)       DEFAULT '0',
  pic_h              INT(4)       DEFAULT '0',
  visible            TINYINT      DEFAULT '1',
  order_num          BIGINT,
  date               TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  show_date1         DATE NULL    DEFAULT NULL,
  show_date2         DATE NULL    DEFAULT NULL,
  show_time1         TIME NULL    DEFAULT NULL,
  show_time2         TIME NULL    DEFAULT NULL,
  show_on_weekdays   VARCHAR(50)  DEFAULT '',
  show_on_holidays   TINYINT      DEFAULT 0,
  INDEX idx(block_id, category_id, show_date1, show_date2, show_time1, show_time2, show_on_weekdays, show_on_holidays, visible, order_num),
  PRIMARY KEY (id)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8
  COLLATE = utf8_general_ci;
