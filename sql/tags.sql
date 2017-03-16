CREATE TABLE IF NOT EXISTS tags(
  id bigint auto_increment,
  slug varchar(255),
  slug_source varchar(255) default '',
  title varchar(255),
  content text,
  pic varchar(40) default '',
  weight int default '0',
  html_title varchar(255) default '',
  html_keywords varchar(255) default '',
  html_description varchar(255) default '',
  visible tinyint default '1',
  date timestamp default CURRENT_TIMESTAMP,
  key idx(weight,visible,title,date),
  unique slug(slug),
  primary key(id)
)
DEFAULT CHARSET='utf8'
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
