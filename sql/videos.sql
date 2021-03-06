CREATE TABLE videos(
	id int unsigned not null auto_increment,
	album_id int unsigned default '0',
	vendor tinyint default '0',
	vendor_video_uid varchar(50) default '',
	slug varchar(255),
	slug_source varchar(255),
	title varchar(255),
	content text,
	embed text,
	video_mp4 varchar(50),
	video_m4v varchar(50),
	video_ogv varchar(50),
	video_webm varchar(50),
	video_w int,
	video_h int,
	pic varchar(50),
	pic_w int,
	pic_h int,
	pic_t tinyint,
	pic_tn_w int,
	pic_tn_h int,
	views_count int unsigned default '0',
	date timestamp DEFAULT CURRENT_TIMESTAMP,
	order_num int,
	visible tinyint default '1',
	top tinyint default '0',
	comments_enabled tinyint default '1',
	comments_last_date datetime,
	comments_count int default '0',
	unique slug_idx(slug),
	key visible_idx(visible,order_num),
	key top_idx(top),
	primary key(id)
)
DEFAULT CHARSET='utf8'
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
