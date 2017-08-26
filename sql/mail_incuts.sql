CREATE TABLE IF NOT EXISTS mail_incuts(
    id bigint not null auto_increment,
    target_type int DEFAULT '0',
    target_id BIGINT NOT NULL DEFAULT '0',
    type TINYINT,
    content longtext,
    index idx(target_type,target_id,type),
    primary key (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
