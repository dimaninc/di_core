CREATE TABLE IF NOT EXISTS photos(
	id int unsigned not null auto_increment,
	album_id int unsigned default '0',
	slug varchar(255),
	slug_source varchar(255),
	title varchar(255),
	content text,
	pic varchar(50),
	pic_w int,
	pic_h int,
	pic_t tinyint,
	pic_tn_w int,
	pic_tn_h int,
	visible tinyint unsigned default '1',
	top tinyint unsigned default '0',
	comments_enabled tinyint default '1',
	comments_last_date datetime,
	comments_count int default '0',
	date timestamp DEFAULT CURRENT_TIMESTAMP,
	order_num int,
	unique slug_idx(slug),
	key idx(album_id,visible,top,order_num,date),
	primary key(id)
)
DEFAULT CHARSET='utf8'
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
