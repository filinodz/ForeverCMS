-- Forever CMS premium shop seed.
-- Safe to run repeatedly: each game item is inserted only when its entry is absent.
USE `forever_site`;

INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Mount', 'Cerulean Prideclaw', 'Signature Skyborne mount from the Forever Heroic collection.', NULL, 0, 70, NULL, 280544, 1, 0 WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 280544);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Mount', 'Veteran Adventurer''s Loyal Companion', 'Prestigious bear mount from the Forever Epic collection.', NULL, 0, 90, NULL, 280611, 1, 0 WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 280611);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Mount', 'Reins of the Spectral Bear', 'Legacy prestige reward for the most dedicated collectors.', NULL, 0, 120, NULL, 277718, 1, 0 WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 277718);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Mount', 'Swift Empyrean Galestrider', 'Very fast Skyborne crane in its luminous Empyrean livery.', NULL, 0, 85, NULL, 269671, 1, 0 WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 269671);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Mount', 'Swift Stormy Galestrider', 'Very fast Skyborne crane wrapped in storm-blue feathers.', NULL, 0, 85, NULL, 269678, 1, 0 WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 269678);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Mount', 'Swift Umber Galestrider', 'Very fast Skyborne crane with a rare umber finish.', NULL, 0, 85, NULL, 274933, 1, 0 WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 274933);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Pet', 'Pachimari', 'Exclusive companion from the Forever Epic collection.', NULL, 0, 50, NULL, 280614, 1, 0 WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 280614);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Pet', 'Zergling Leash', 'Collector companion included with the Forever Epic collection.', NULL, 0, 45, NULL, 13582, 1, 0 WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 13582);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Pet', 'Diablo Stone', 'Collector companion included with the Forever Epic collection.', NULL, 0, 45, NULL, 13584, 1, 0 WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 13584);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Pet', 'Panda Collar', 'Collector companion included with the Forever Epic collection.', NULL, 0, 45, NULL, 13583, 1, 0 WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 13583);
INSERT INTO `shop_items` (`category`, `name`, `description`, `image`, `point_cost`, `token_cost`, `stock`, `entry`, `is_item`, `is_set`)
SELECT 'Pet', 'Spectral Bear Cub', 'Rare Legacy Track companion with a spectral appearance.', NULL, 0, 55, NULL, 277714, 1, 0 WHERE NOT EXISTS (SELECT 1 FROM `shop_items` WHERE `entry` = 277714);
