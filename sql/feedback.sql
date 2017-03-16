CREATE TABLE IF NOT EXISTS feedback(
  id bigint unsigned not null auto_increment,
  user_id bigint,
  name varchar(100) default '',
  email varchar(60) default '',
  phone varchar(20) default '',
  content text,
  ip bigint,
  date timestamp default CURRENT_TIMESTAMP,
  primary key(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;