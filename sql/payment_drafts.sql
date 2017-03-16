CREATE TABLE IF NOT EXISTS payment_drafts(
	id bigint not null auto_increment,
	target_type int,
	target_id bigint,
	user_id bigint,
	pay_system tinyint unsigned,
	vendor tinyint unsigned default '0',
	currency tinyint unsigned,
	amount float default '0',
	date_reserved timestamp default CURRENT_TIMESTAMP,
	index idx(target_type,target_id,user_id,date_reserved),
	PRIMARY KEY(id)
)
DEFAULT CHARSET='utf8'
COLLATE='utf8_general_ci'
ENGINE=InnoDB;