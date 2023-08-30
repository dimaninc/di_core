CREATE TABLE IF NOT EXISTS `comments`(
	`id` BIGINT NOT NULL AUTO_INCREMENT,
	`user_type` tinyint default '0',
	`user_id` INT NOT NULL DEFAULT '0',
	`owner_id` INT NOT NULL DEFAULT '0',
	`parent` BIGINT NOT NULL DEFAULT '0',
	`target_type` int,
	`target_id` BIGINT NOT NULL DEFAULT '0',
	`content` TEXT NOT NULL,
	`date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	`ip` BIGINT NOT NULL,
	`order_num` INT NOT NULL,
	`level_num` TINYINT NOT NULL DEFAULT '0',
	`visible` TINYINT NOT NULL DEFAULT '1',
	`moderated` TINYINT NOT NULL DEFAULT '1',
    `karma` int default '0',
    `evil_score` int default '0',
	INDEX `idx`(target_type, target_id, visible, moderated, order_num),
	INDEX `user`(user_type, user_id),
	PRIMARY KEY (`id`)
)
DEFAULT CHARSET='utf8'
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
