-- ForeverCMS / ForeverCore shared shop catalog.
-- Safe to run repeatedly. The in-game shop and ForeverCMS both read these rows.

INSERT INTO `world`.`shop_group` (`ID`, `Name`, `IconFileDataID`, `Ordering`) VALUES
  (100, 'Mounts', 132261, 100),
  (101, 'Companions', 132599, 101)
ON DUPLICATE KEY UPDATE `Name` = VALUES(`Name`), `IconFileDataID` = VALUES(`IconFileDataID`), `Ordering` = VALUES(`Ordering`);

INSERT INTO `world`.`shop_product`
  (`ID`, `GroupID`, `Name`, `Description`, `IconFileDataID`, `Price`, `NormalPrice`, `Ordering`, `Enabled`) VALUES
  (1001, 100, 'Cerulean Prideclaw', 'Signature Skyborne mount from the Forever Heroic collection.', 7749904, 70, 0, 1, 1),
  (1002, 100, 'Veteran Adventurer''s Loyal Companion', 'Prestigious bear mount from the Forever Epic collection.', 7931167, 90, 0, 2, 1),
  (1003, 100, 'Reins of the Spectral Bear', 'Legacy prestige reward for the most dedicated Forever collectors.', 3753812, 120, 0, 3, 1),
  (1004, 100, 'Swift Empyrean Galestrider', 'Very fast Skyborne crane in its luminous Empyrean livery.', 7754707, 85, 0, 4, 1),
  (1005, 100, 'Swift Stormy Galestrider', 'Very fast Skyborne crane wrapped in storm-blue feathers.', 7754704, 85, 0, 5, 1),
  (1006, 100, 'Swift Umber Galestrider', 'Very fast Skyborne crane with a rare umber finish.', 7754704, 85, 0, 6, 1),
  (1011, 101, 'Pachimari', 'Exclusive companion from the Forever Epic collection.', 7741132, 50, 0, 1, 1),
  (1012, 101, 'Zergling Leash', 'Collector companion included with the Forever Epic collection.', 136217, 45, 0, 2, 1),
  (1013, 101, 'Diablo Stone', 'Collector companion included with the Forever Epic collection.', 132787, 45, 0, 3, 1),
  (1014, 101, 'Panda Collar', 'Collector companion included with the Forever Epic collection.', 132494, 45, 0, 4, 1),
  (1015, 101, 'Spectral Bear Cub', 'Rare Legacy Track companion with a spectral appearance.', 294471, 55, 0, 5, 1)
ON DUPLICATE KEY UPDATE
  `GroupID` = VALUES(`GroupID`), `Name` = VALUES(`Name`), `Description` = VALUES(`Description`),
  `IconFileDataID` = VALUES(`IconFileDataID`), `Price` = VALUES(`Price`),
  `NormalPrice` = VALUES(`NormalPrice`), `Ordering` = VALUES(`Ordering`), `Enabled` = VALUES(`Enabled`);

INSERT INTO `world`.`shop_product_action` (`ProductID`, `Index`, `Action`, `Param1`, `Param2`) VALUES
  (1001, 0, 1, 280544, 1),
  (1002, 0, 1, 280611, 1),
  (1003, 0, 1, 277718, 1),
  (1004, 0, 1, 269671, 1),
  (1005, 0, 1, 269678, 1),
  (1006, 0, 1, 274933, 1),
  (1011, 0, 1, 280614, 1),
  (1012, 0, 1, 13582, 1),
  (1013, 0, 1, 13584, 1),
  (1014, 0, 1, 13583, 1),
  (1015, 0, 1, 277714, 1)
ON DUPLICATE KEY UPDATE `Action` = VALUES(`Action`), `Param1` = VALUES(`Param1`), `Param2` = VALUES(`Param2`);

-- Preserve an existing ForeverCore wallet. Only accounts that do not have one
-- yet inherit their legacy CMS balance during the upgrade.
INSERT IGNORE INTO `auth`.`account_shop_points` (`AccountID`, `Points`)
SELECT `account_id`, GREATEST(0, `points` + `tokens`) FROM `forever_site`.`user_currencies`;
