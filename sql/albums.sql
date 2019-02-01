CREATE TABLE IF NOT EXISTS albums(
	id int unsigned not null auto_increment,
	slug varchar(255),
	slug_source varchar(255),
	title varchar(255),
	content text,
	cover_photo_id int default '0',
	pic varchar(50) default '',
	pic_w int default '0',
	pic_h int default '0',
	pic_t tinyint default '0',
	date timestamp NULL DEFAULT CURRENT_TIMESTAMP,
	order_num int,
	visible tinyint default '1',
	top tinyint default '0',
	comments_enabled tinyint default '1',
	comments_last_date datetime,
	comments_count int default '0',
	photos_count int default '0',
	videos_count int default '0',
	unique slug_idx(slug),
	key visible_idx(visible,order_num,date,photos_count,videos_count,comments_count),
	primary key(id)
)
DEFAULT CHARSET='utf8'
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
