CREATE TABLE IF NOT EXISTS fonts(
	id bigint auto_increment,
	title varchar(255),
	token varchar(255),
	weight varchar(50) default '',
	style varchar(50) default '',
	content text,
	file_eot varchar(40) default '',
	file_otf varchar(40) default '',
	file_ttf varchar(40) default '',
	file_woff varchar(40) default '',
	file_svg varchar(40) default '',
	token_svg varchar(255) default '',
	visible tinyint default '1',
	order_num bigint,
	date TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
	key idx(token,visible,order_num),
	primary key(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;