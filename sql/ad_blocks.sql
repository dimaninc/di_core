CREATE TABLE IF NOT EXISTS ad_blocks(
  id bigint auto_increment,
  title varchar(255),
  default_slide_title varchar(255) default '',
  default_slide_content text,
  transition tinyint default '0',
  transition_style tinyint default '0',
  duration_of_show int default '0',
  duration_of_change int default '0',
  slides_order tinyint default '0',
  ignore_hover_hold tinyint(1) default '0',
  visible tinyint default '1',
  order_num bigint,
  date timestamp default CURRENT_TIMESTAMP,
  key idx(visible,order_num),
  primary key(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
