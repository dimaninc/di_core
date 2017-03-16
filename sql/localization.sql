CREATE TABLE `localization` (
  `id` bigint NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `en_value` varchar(255) NOT NULL,
  UNIQUE INDEX `name_idx` (`name`),
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
