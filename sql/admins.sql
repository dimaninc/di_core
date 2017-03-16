CREATE TABLE IF NOT EXISTS admins(
  id bigint not null auto_increment,
  login varchar(50),
  password varchar(32),
  first_name varchar(50),
  last_name varchar(50),
  email varchar(60),
  phone varchar(32),
  date timestamp default CURRENT_TIMESTAMP,
  ip bigint,
  host varchar(50),
  level varchar(255),
  active tinyint default '1',
  index idx(login,email,active),
  primary key(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
