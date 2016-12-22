ALTER TABLE users
ADD COLUMN micropub_syndication_targets text AFTER micropub_endpoint;

CREATE TABLE `syndication_rules` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `match` varchar(255) DEFAULT NULL,
  `syndicate_to` text,
  `syndicate_to_name` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
