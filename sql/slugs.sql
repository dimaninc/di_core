CREATE TABLE IF NOT EXISTS slugs(
	id int not null auto_increment,
	target_type int,
	target_id int,
	slug varchar(100),
	full_slug varchar(255),
	level_num tinyint,
	unique index slug_idx(slug,level_num),
	unique index target_idx(target_type,target_id),
	UNIQUE INDEX full_slug_idx(full_slug),
	primary key(id)
)
ENGINE=InnoDB
DEFAULT CHARSET=utf8
COLLATE=utf8_general_ci;
