CREATE TABLE IF NOT EXISTS admins(
  id integer not null primary key autoincrement,
  login varchar(50),
  password varchar(32),
  first_name varchar(50),
  last_name varchar(50),
  email varchar(60),
  phone varchar(32),
  address varchar(255) default '',
  date timestamp default CURRENT_TIMESTAMP,
  ip bigint,
  host varchar(50),
  level varchar(255),
  active tinyint default '1'
);

CREATE INDEX idx_admins
ON admins (login,email,active);
