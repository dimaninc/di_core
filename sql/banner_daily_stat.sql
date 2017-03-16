CREATE TABLE banner_daily_stat(
  id bigint not null auto_increment,
  banner_id int,
  type TINYINT,
  uri varchar(255),
  date DATE,
  count int default '0',
  key banner_id(`banner_id`,`type`,`date`),
  key `count`(`count`),
  unique `uri_idx` (banner_id,date,type,uri),
  primary key(id)
)
  ENGINE=InnoDB
  DEFAULT CHARSET=utf8
  COLLATE=utf8_general_ci;