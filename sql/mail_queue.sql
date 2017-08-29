CREATE TABLE IF NOT EXISTS mail_queue(
  id bigint not null auto_increment,
  date timestamp default CURRENT_TIMESTAMP,
  sender varchar(255),
  recipient varchar(255),
  recipient_id bigint default '0',
  subject varchar(255),
  body mediumtext,
  plain_body tinyint default '1',
  attachment MEDIUMBLOB,
  incut_ids varchar(20) default '',
  visible tinyint default '1',
  sent tinyint default '0',
  news_id bigint default '0',
  primary key(id),
  key idx(visible,sent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
