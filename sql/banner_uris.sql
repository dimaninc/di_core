CREATE TABLE IF NOT EXISTS banner_uris(
	banner_id int,
	uri varchar(255) default '',
	positive tinyint default '1',
	key banner_inf(banner_id,uri,positive)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;