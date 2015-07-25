CREATE TABLE IF NOT EXISTS {PREFIX}post_likes(
	`id` INT(10) NOT NULL AUTO_INCREMENT,
	`post_id` INT(10) unsigned NOT NULL,
	`user_id` INT(10) unsigned NOT NULL,
	`created_at` TIMESTAMP,
	PRIMARY KEY (id),
	INDEX `post_id_index` (`post_id`),
	CONSTRAINT `unique_post_user_id` UNIQUE (`post_id`,`user_id`)
) ENGINE=InnoDB{COLLATION};