CREATE TABLE IF NOT EXISTS geo_ip_cache(
  ip bigint,
  country_code varchar(2),
  country_name varchar(64),
  region_code varchar(2),
  region_name varchar(64),
  city varchar(64),
  zip_code varchar(10) DEFAULT '',
  latitude DOUBLE DEFAULT '0',
  longitude DOUBLE DEFAULT '0',
  created_at DATETIME DEFAULT NULL,
  updated_at timestamp default current_timestamp on update current_timestamp,
  primary key(ip)
)
  ENGINE=InnoDB
  DEFAULT CHARSET=utf8
  COLLATE=utf8_general_ci;
