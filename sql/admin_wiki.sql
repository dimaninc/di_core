CREATE TABLE IF NOT EXISTS admin_wiki(
	id bigint auto_increment,
	title varchar(255),
	content text,
	visible tinyint default '1',
	date timestamp default CURRENT_TIMESTAMP,
	index idx(visible,date),
	primary key(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;