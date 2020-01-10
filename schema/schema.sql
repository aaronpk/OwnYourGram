CREATE TABLE `users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(255) DEFAULT NULL,
  `tier` TINYINT(4) NOT NULL DEFAULT '3',
  `instagram_user_id` varchar(255) DEFAULT NULL,
  `instagram_username` varchar(255) DEFAULT NULL,
  `instagram_access_token` varchar(255) DEFAULT NULL,
  `instagram_response` text,
  `micropub_endpoint` varchar(255) DEFAULT NULL,
  `media_endpoint` varchar(255) DEFAULT NULL,
  `micropub_syndication_targets` text,
  `micropub_access_token` text,
  `micropub_response` text,
  `micropub_success` tinyint(4) DEFAULT '0',
  `date_created` datetime DEFAULT NULL,
  `next_poll_date` datetime DEFAULT NULL,
  `last_poll_date` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_micropub_response` text,
  `last_instagram_photo` varchar(255) DEFAULT NULL,
  `last_photo_date` datetime DEFAULT NULL,
  `token_endpoint` varchar(255) DEFAULT NULL,
  `authorization_endpoint` varchar(255) DEFAULT NULL,
  `email_username` varchar(100) DEFAULT NULL,
  `photo_count` int(11) NOT NULL DEFAULT '0',
  `photo_count_this_week` int(11) NOT NULL DEFAULT '0',
  `last_micropub_url` varchar(255) DEFAULT NULL,
  `last_instagram_img_url` varchar(255) DEFAULT NULL,
  `ig_public` tinyint(4) NOT NULL DEFAULT '0',
  `allowlist` text CHARACTER SET utf8mb4,
  `blocklist` text CHARACTER SET utf8mb4,
  `add_tags` text CHARACTER SET utf8mb4,
  `polling_enabled` tinyint(4) NOT NULL DEFAULT '1',
  `send_media_as` varchar(20) NOT NULL DEFAULT 'upload',
  `multi_photo` tinyint(4) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `photos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `instagram_img` varchar(512) DEFAULT NULL,
  `instagram_img_list` text,
  `published` datetime DEFAULT NULL,
  `instagram_data` text,
  `canonical_url` varchar(255) DEFAULT NULL,
  `processed` tinyint(4) NOT NULL DEFAULT '0',
  `date_imported` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `syndication_rules` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `match` varchar(255) DEFAULT NULL,
  `syndicate_to` text,
  `syndicate_to_name` text,
  PRIMARY KEY (`id`)
);

