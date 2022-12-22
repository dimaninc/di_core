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
    fiscal_mark varchar(16) default '',
    fiscal_doc_id varchar(16) default '',
    fiscal_date datetime default null,
    fiscal_session int default 0,
    fiscal_number int default 0,
	draft_id bigint,
    ip bigINT(11) DEFAULT '0',
    partner_code_id INT DEFAULT '0',
    index idx (target_type, target_id, user_id, date_reserved, date_payed, date_uploaded, ip),
    index idx_fiscal(fiscal_doc_id, fiscal_mark, fiscal_date, fiscal_session, fiscal_number),
	unique draft_idx(draft_id),
	PRIMARY KEY(id)
)
DEFAULT CHARSET='utf8'
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
