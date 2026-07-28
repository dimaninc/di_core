CREATE TABLE IF NOT EXISTS admin_tasks(
	id bigint auto_increment,
	admin_id int default '0',
	title varchar(255),
	content text,
	visible tinyint default '1',
	status tinyint default '0',
	priority tinyint default '0',
	due_date datetime,
	date timestamp default CURRENT_TIMESTAMP,
	index idx(admin_id,due_date,visible,status,priority),
	primary key(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
