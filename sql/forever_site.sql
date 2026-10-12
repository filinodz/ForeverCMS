-- Forever CMS: the website's own database. Setup-CMS.bat imports this once.
-- Based on SahtoutCMS's sahtout_site (2025-10-12), changed for Forever:
--   * no foreign keys, triggers or procedures that reach into the server databases (auth, characters, world):
--     the server's databases stay exactly as the server made them
--   * accounts are Battle.net accounts: pending_accounts keeps an e-mail instead of a user name and SRP data
--   * four realms: tables that name a character also name its realm
--   * no site_items copy of item_template: item data comes from world.armory_item (the repack's armory export)

CREATE DATABASE IF NOT EXISTS `forever_site` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `forever_site`;

CREATE TABLE IF NOT EXISTS `profile_avatars` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `profile_avatars` (`id`, `filename`, `display_name`, `active`) VALUES
	(1, '1-0.png', 'Avatar 1', 1), (2, '1-1.png', 'Avatar 2', 1), (3, '2-0.png', 'Avatar 3', 1), (4, '2-1.png', 'Avatar 4', 1),
	(5, '3-0.png', 'Avatar 5', 1), (6, '3-1.png', 'Avatar 6', 1), (7, '4-0.png', 'Avatar 7', 1), (8, '4-1.png', 'Avatar 8', 1),
	(9, '5-0.png', 'Avatar 9', 1), (10, '5-1.png', 'Avatar 10', 1), (11, '6-0.png', 'Avatar 11', 1), (12, '6-1.png', 'Avatar 12', 1),
	(13, '7-0.png', 'Avatar 13', 1), (14, '7-1.png', 'Avatar 14', 1), (15, '8-0.png', 'Avatar 15', 1), (16, '8-1.png', 'Avatar 16', 1),
	(17, '10-0.png', 'Avatar 17', 1), (18, '10-1.png', 'Avatar 18', 1), (19, '11-0.png', 'Avatar 19', 1), (20, '11-1.png', 'Avatar 20', 1),
	(21, '28-0.png', 'Avatar 21', 1), (22, '28-1.png', 'Avatar 22', 1), (23, '32-0.png', 'Avatar 23', 1), (24, '32-1.png', 'Avatar 24', 1),
	(25, '37-0.png', 'Avatar 25', 1), (26, '70-0.png', 'Avatar 26', 1), (27, '70-1.png', 'Avatar 27', 1);

-- One row per game account (auth.account.id). Made when the website first meets the account.
CREATE TABLE IF NOT EXISTS `user_currencies` (
  `account_id` int unsigned NOT NULL COMMENT 'auth.account.id',
  `username` varchar(64) NOT NULL COMMENT 'auth.account.username, like 5#1',
  `email` varchar(320) DEFAULT NULL COMMENT 'Battle.net e-mail, lower case',
  `avatar` varchar(255) DEFAULT NULL,
  `points` int unsigned NOT NULL DEFAULT '0',
  `tokens` int unsigned NOT NULL DEFAULT '0',
  `role` enum('player','moderator','admin') NOT NULL DEFAULT 'player',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`account_id`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `fk_user_currencies_avatar` (`avatar`),
  CONSTRAINT `fk_user_currencies_avatar` FOREIGN KEY (`avatar`) REFERENCES `profile_avatars` (`filename`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registrations waiting for their activation e-mail (only used when SMTP is on).
-- The password is kept encrypted with the key in includes/config.secret.php until the link is used; rows expire after 48 hours.
CREATE TABLE IF NOT EXISTS `pending_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(320) NOT NULL,
  `password_sealed` varbinary(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `activated` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email` (`email`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_logins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(255) NOT NULL COMMENT 'the e-mail that was tried, upper case',
  `attempts` int NOT NULL DEFAULT 0,
  `last_attempt` int NOT NULL,
  `block_until` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ip_username` (`ip_address`, `username`),
  KEY `idx_last_attempt` (`last_attempt`),
  KEY `idx_block_until` (`block_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(320) NOT NULL,
  `token` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reset_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `email` varchar(320) DEFAULT NULL,
  `attempts` int NOT NULL DEFAULT '0',
  `last_attempt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `blocked_until` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_email` (`email`),
  KEY `idx_ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `server_news` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `slug` varchar(120) DEFAULT NULL,
  `content` text NOT NULL,
  `posted_by` varchar(50) NOT NULL COMMENT 'GM/Admin name',
  `post_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `image_url` varchar(255) DEFAULT NULL COMMENT 'Optional image for news',
  `is_important` tinyint(1) DEFAULT '0' COMMENT '1 for important/sticky news',
  `category` enum('update','event','maintenance','other') DEFAULT 'update',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `server_news` (`id`, `title`, `slug`, `content`, `posted_by`, `image_url`, `is_important`, `category`) VALUES
	(1, 'Welcome to Forever', 'welcome-to-forever', 'The website is up. Create your account here, then log in to the game with the same e-mail and password.\n\nFour realms are waiting for you: Normal, PvP, RP and Hardcore. Each one has its own characters.', 'Forever', 'img/newsimg/news.png', 1, 'other');

-- "entry" is a game item id (world.armory_item.id), "itemset_id" a game item set id.
CREATE TABLE IF NOT EXISTS `shop_items` (
  `item_id` int unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `image` varchar(255) DEFAULT NULL,
  `point_cost` int unsigned NOT NULL DEFAULT '0',
  `token_cost` int unsigned NOT NULL DEFAULT '0',
  `stock` int unsigned DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `entry` int unsigned DEFAULT NULL,
  `gold_amount` int DEFAULT '0',
  `level_boost` smallint unsigned DEFAULT NULL,
  `at_login_flags` tinyint unsigned DEFAULT '0',
  `is_item` tinyint unsigned NOT NULL DEFAULT '0',
  `is_set` tinyint unsigned NOT NULL DEFAULT '0',
  `itemset_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`item_id`),
  KEY `idx_category` (`category`),
  KEY `idx_entry` (`entry`),
  KEY `idx_itemset_id` (`itemset_id`),
  CONSTRAINT `chk_shop_items_category` CHECK ((`category` in ('Mount','Pet','Gold','Service','Stuff','Set'))),
  CONSTRAINT `chk_is_item` CHECK ((`is_item` in (0,1))),
  CONSTRAINT `chk_is_set` CHECK ((`is_set` in (0,1))),
  CONSTRAINT `shop_items_chk_1` CHECK ((`level_boost` between 2 and 255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Curated Forever 1.60 cosmetics (verified against world.armory_item and Wowhead's Forever database).
-- Idempotent by entry: safe for fresh installs and repeated setup runs.
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Mount', 'Cerulean Prideclaw', 'Signature Skyborne mount from the Forever Heroic collection.', NULL, 0, 70, NULL, 280544, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 280544);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Mount', 'Veteran Adventurer''s Loyal Companion', 'Prestigious bear mount from the Forever Epic collection.', NULL, 0, 90, NULL, 280611, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 280611);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Mount', 'Reins of the Spectral Bear', 'Legacy prestige reward for the most dedicated collectors.', NULL, 0, 120, NULL, 277718, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 277718);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Mount', 'Swift Empyrean Galestrider', 'Very fast Skyborne crane in its luminous Empyrean livery.', NULL, 0, 85, NULL, 269671, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 269671);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Mount', 'Swift Stormy Galestrider', 'Very fast Skyborne crane wrapped in storm-blue feathers.', NULL, 0, 85, NULL, 269678, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 269678);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Mount', 'Swift Umber Galestrider', 'Very fast Skyborne crane with a rare umber finish.', NULL, 0, 85, NULL, 274933, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 274933);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Pet', 'Pachimari', 'Exclusive companion from the Forever Epic collection.', NULL, 0, 50, NULL, 280614, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 280614);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Pet', 'Zergling Leash', 'Collector companion included with the Forever Epic collection.', NULL, 0, 45, NULL, 13582, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 13582);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Pet', 'Diablo Stone', 'Collector companion included with the Forever Epic collection.', NULL, 0, 45, NULL, 13584, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 13584);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Pet', 'Panda Collar', 'Collector companion included with the Forever Epic collection.', NULL, 0, 45, NULL, 13583, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 13583);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Pet', 'Spectral Bear Cub', 'Rare Legacy Track companion with a spectral appearance.', NULL, 0, 55, NULL, 277714, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 277714);

CREATE TABLE IF NOT EXISTS `purchases` (
  `purchase_id` int unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int unsigned NOT NULL,
  `item_id` int unsigned NOT NULL,
  `realm_id` int unsigned NOT NULL DEFAULT '0',
  `character_guid` bigint unsigned NOT NULL DEFAULT '0',
  `character_name` varchar(12) DEFAULT NULL,
  `point_cost` int unsigned NOT NULL DEFAULT '0',
  `token_cost` int unsigned NOT NULL DEFAULT '0',
  `purchase_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`purchase_id`),
  KEY `fk_account_id` (`account_id`),
  KEY `fk_item_id` (`item_id`),
  CONSTRAINT `fk_account_id` FOREIGN KEY (`account_id`) REFERENCES `user_currencies` (`account_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_item_id` FOREIGN KEY (`item_id`) REFERENCES `shop_items` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vote_sites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `callback_file_name` varchar(50) NOT NULL,
  `site_name` varchar(255) NOT NULL,
  `siteid` varchar(255) NOT NULL,
  `url_format` varchar(255) DEFAULT NULL,
  `button_image_url` varchar(255) DEFAULT NULL,
  `cooldown_hours` int NOT NULL DEFAULT '12',
  `reward_points` int NOT NULL DEFAULT '1',
  `uses_callback` tinyint(1) NOT NULL DEFAULT '0',
  `callback_secret` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_site_name` (`site_name`),
  UNIQUE KEY `uk_external_site_id` (`callback_file_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vote_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL,
  `user_id` int unsigned NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `vote_timestamp` int NOT NULL,
  `reward_status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_site_id` (`site_id`),
  KEY `fk_vote_log_user_currencies` (`user_id`),
  CONSTRAINT `fk_vote_log_sites` FOREIGN KEY (`site_id`) REFERENCES `vote_sites` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_vote_log_user_currencies` FOREIGN KEY (`user_id`) REFERENCES `user_currencies` (`account_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vote_log_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL,
  `user_id` int NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `vote_timestamp` int NOT NULL,
  `reward_status` tinyint(1) NOT NULL DEFAULT '0',
  `reward_points` int NOT NULL DEFAULT '0',
  `claimed_timestamp` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_vote_timestamp` (`vote_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `character_teleport_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int unsigned NOT NULL,
  `realm_id` int unsigned NOT NULL DEFAULT '0',
  `character_guid` bigint unsigned NOT NULL,
  `character_name` varchar(12) NOT NULL,
  `teleport_timestamp` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  UNIQUE KEY `uk_character` (`realm_id`, `character_guid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `website_activity_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int unsigned NOT NULL,
  `character_name` varchar(12) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `timestamp` int unsigned NOT NULL,
  `details` text,
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
