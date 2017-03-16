CREATE TABLE IF NOT EXISTS admin_task_participants(
  admin_id int,
  task_id int,
  UNIQUE index idx(admin_id,task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;