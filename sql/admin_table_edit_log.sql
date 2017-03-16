CREATE TABLE IF NOT EXISTS admin_table_edit_log(
  id bigint auto_increment,
  target_table varchar(64),
  target_id bigint,
  admin_id int,
  old_data MEDIUMTEXT,
  new_data MEDIUMTEXT,
  created_at timestamp default CURRENT_TIMESTAMP,
  index idx(`target_table`,`target_id`,`admin_id`,`created_at`),
  primary key(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;