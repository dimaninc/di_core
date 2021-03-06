CREATE TABLE IF NOT EXISTS payment_receipts(
	id bigint not null auto_increment,
	target_type int,
	target_id bigint,
	user_id bigint,
	pay_system tinyint unsigned,
	vendor tinyint unsigned default '0',
	currency tinyint unsigned,
	amount float DEFAULT '0',
	rnd varchar(8) DEFAULT '',
	outer_number VARCHAR(32) DEFAULT '',
	date_reserved datetime DEFAULT NULL,
	date_payed timestamp DEFAULT CURRENT_TIMESTAMP,
	date_uploaded timestamp DEFAULT NULL,
	draft_id bigint,
	index idx(target_type,target_id,user_id,date_reserved,date_payed,date_uploaded),
	unique draft_idx(draft_id),
	PRIMARY KEY(id)
)
DEFAULT CHARSET='utf8'
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
