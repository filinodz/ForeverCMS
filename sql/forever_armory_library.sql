-- Read-only Armory library, exported by the updated ForeverCore at startup.
USE `world`;

CREATE TABLE IF NOT EXISTS `forever_armory_source_spell_name` (
  `ID` int unsigned NOT NULL DEFAULT '0',
  `Name` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `VerifiedBuild` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`,`VerifiedBuild`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forever_armory_source_spell_misc` (
  `ID` int unsigned NOT NULL DEFAULT '0',
  `Attributes1` int NOT NULL DEFAULT '0',
  `Attributes2` int NOT NULL DEFAULT '0',
  `Attributes3` int NOT NULL DEFAULT '0',
  `Attributes4` int NOT NULL DEFAULT '0',
  `Attributes5` int NOT NULL DEFAULT '0',
  `Attributes6` int NOT NULL DEFAULT '0',
  `Attributes7` int NOT NULL DEFAULT '0',
  `Attributes8` int NOT NULL DEFAULT '0',
  `Attributes9` int NOT NULL DEFAULT '0',
  `Attributes10` int NOT NULL DEFAULT '0',
  `Attributes11` int NOT NULL DEFAULT '0',
  `Attributes12` int NOT NULL DEFAULT '0',
  `Attributes13` int NOT NULL DEFAULT '0',
  `Attributes14` int NOT NULL DEFAULT '0',
  `Attributes15` int NOT NULL DEFAULT '0',
  `Attributes16` int NOT NULL DEFAULT '0',
  `Attributes17` int NOT NULL DEFAULT '0',
  `DifficultyID` smallint NOT NULL DEFAULT '0',
  `CastingTimeIndex` smallint unsigned NOT NULL DEFAULT '0',
  `DurationIndex` smallint unsigned NOT NULL DEFAULT '0',
  `PvPDurationIndex` smallint unsigned NOT NULL DEFAULT '0',
  `RangeIndex` smallint unsigned NOT NULL DEFAULT '0',
  `SchoolMask` tinyint unsigned NOT NULL DEFAULT '0',
  `Speed` float NOT NULL DEFAULT '0',
  `LaunchDelay` float NOT NULL DEFAULT '0',
  `MinDuration` float NOT NULL DEFAULT '0',
  `SpellIconFileDataID` int NOT NULL DEFAULT '0',
  `ActiveIconFileDataID` int NOT NULL DEFAULT '0',
  `ContentTuningID` int NOT NULL DEFAULT '0',
  `ShowFutureSpellPlayerConditionID` int NOT NULL DEFAULT '0',
  `SpellVisualScript` int NOT NULL DEFAULT '0',
  `ActiveSpellVisualScript` int NOT NULL DEFAULT '0',
  `SpellID` int unsigned NOT NULL DEFAULT '0',
  `VerifiedBuild` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`,`VerifiedBuild`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forever_armory_source_trait_tree` (
  `TitleText` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ID` int unsigned NOT NULL DEFAULT '0',
  `TraitSystemID` int unsigned NOT NULL DEFAULT '0',
  `BaseNodeGroup` int NOT NULL DEFAULT '0',
  `FirstTraitNodeID` int NOT NULL DEFAULT '0',
  `PlayerConditionID` int NOT NULL DEFAULT '0',
  `Flags` int NOT NULL DEFAULT '0',
  `MinZoom` float NOT NULL DEFAULT '0',
  `MaxZoom` float NOT NULL DEFAULT '0',
  `UiTextureKitID` int NOT NULL DEFAULT '0',
  `VerifiedBuild` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`,`VerifiedBuild`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `forever_armory_source_trait_node` (
  `ID` int unsigned NOT NULL DEFAULT '0',
  `TraitTreeID` int unsigned NOT NULL DEFAULT '0',
  `PosX` int NOT NULL DEFAULT '0',
  `PosY` int NOT NULL DEFAULT '0',
  `Type` tinyint unsigned NOT NULL DEFAULT '0',
  `Flags` int NOT NULL DEFAULT '0',
  `TraitSubTreeID` int NOT NULL DEFAULT '0',
  `VerifiedBuild` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`,`VerifiedBuild`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forever_armory_source_trait_node_entry` (
  `ID` int unsigned NOT NULL DEFAULT '0',
  `TraitDefinitionID` int NOT NULL DEFAULT '0',
  `MaxRanks` int NOT NULL DEFAULT '0',
  `NodeEntryType` tinyint unsigned NOT NULL DEFAULT '0',
  `TraitSubTreeID` int NOT NULL DEFAULT '0',
  `VerifiedBuild` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`,`VerifiedBuild`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forever_armory_source_trait_node_x_trait_node_entry` (
  `ID` int unsigned NOT NULL DEFAULT '0',
  `TraitNodeID` int unsigned NOT NULL DEFAULT '0',
  `TraitNodeEntryID` int NOT NULL DEFAULT '0',
  `Index` int NOT NULL DEFAULT '0',
  `VerifiedBuild` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`,`VerifiedBuild`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forever_armory_source_trait_definition` (
  `OverrideName` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `OverrideSubtext` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `OverrideDescription` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ID` int unsigned NOT NULL DEFAULT '0',
  `SpellID` int NOT NULL DEFAULT '0',
  `OverrideIcon` int NOT NULL DEFAULT '0',
  `OverridesSpellID` int NOT NULL DEFAULT '0',
  `VisibleSpellID` int NOT NULL DEFAULT '0',
  `VerifiedBuild` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`,`VerifiedBuild`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forever_armory_source_trait_edge` (
  `ID` int unsigned NOT NULL DEFAULT '0',
  `VisualStyle` int NOT NULL DEFAULT '0',
  `LeftTraitNodeID` int unsigned NOT NULL DEFAULT '0',
  `RightTraitNodeID` int NOT NULL DEFAULT '0',
  `Type` tinyint unsigned NOT NULL DEFAULT '0',
  `VerifiedBuild` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`,`VerifiedBuild`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forever_armory_source_skill_line` (
  `DisplayName` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `AlternateVerb` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `Description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `HordeDisplayName` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `OverrideSourceInfoDisplayName` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ID` int unsigned NOT NULL DEFAULT '0',
  `CategoryID` tinyint NOT NULL DEFAULT '0',
  `SpellIconFileID` int NOT NULL DEFAULT '0',
  `CanLink` tinyint NOT NULL DEFAULT '0',
  `ParentSkillLineID` int unsigned NOT NULL DEFAULT '0',
  `ParentTierIndex` int NOT NULL DEFAULT '0',
  `Flags` int NOT NULL DEFAULT '0',
  `SpellBookSpellID` int NOT NULL DEFAULT '0',
  `ExpansionNameSharedStringID` int NOT NULL DEFAULT '0',
  `HordeExpansionNameSharedStringID` int NOT NULL DEFAULT '0',
  `VerifiedBuild` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`,`VerifiedBuild`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forever_armory_source_skill_race_class_info` (
  `ID` int unsigned NOT NULL DEFAULT '0',
  `SkillID` smallint unsigned NOT NULL DEFAULT '0',
  `ClassMask` int NOT NULL DEFAULT '0',
  `Flags` int NOT NULL DEFAULT '0',
  `Availability` int NOT NULL DEFAULT '0',
  `MinLevel` tinyint NOT NULL DEFAULT '0',
  `SkillTierID` smallint NOT NULL DEFAULT '0',
  `RaceMask1` int NOT NULL DEFAULT '0',
  `RaceMask2` int NOT NULL DEFAULT '0',
  `VerifiedBuild` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`,`VerifiedBuild`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forever_armory_source_skill_line_x_trait_tree` (
  `ID` int unsigned NOT NULL DEFAULT '0',
  `SkillLineID` int unsigned NOT NULL DEFAULT '0',
  `TraitTreeID` int NOT NULL DEFAULT '0',
  `OrderIndex` int NOT NULL DEFAULT '0',
  `VerifiedBuild` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`,`VerifiedBuild`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forever_armory_source_talent` (
  `ID` int unsigned NOT NULL DEFAULT '0',
  `Description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `TierID` tinyint unsigned NOT NULL DEFAULT '0',
  `Flags` int NOT NULL DEFAULT '0',
  `ColumnIndex` tinyint unsigned NOT NULL DEFAULT '0',
  `TabID` smallint unsigned NOT NULL DEFAULT '0',
  `ClassID` tinyint NOT NULL DEFAULT '0',
  `SpecID` smallint unsigned NOT NULL DEFAULT '0',
  `SpellID` int unsigned NOT NULL DEFAULT '0',
  `OverridesSpellID` int unsigned NOT NULL DEFAULT '0',
  `RequiredSpellID` int unsigned NOT NULL DEFAULT '0',
  `CategoryMask1` int NOT NULL DEFAULT '0',
  `CategoryMask2` int NOT NULL DEFAULT '0',
  `SpellRank1` int unsigned NOT NULL DEFAULT '0',
  `SpellRank2` int unsigned NOT NULL DEFAULT '0',
  `SpellRank3` int unsigned NOT NULL DEFAULT '0',
  `SpellRank4` int unsigned NOT NULL DEFAULT '0',
  `SpellRank5` int unsigned NOT NULL DEFAULT '0',
  `SpellRank6` int unsigned NOT NULL DEFAULT '0',
  `SpellRank7` int unsigned NOT NULL DEFAULT '0',
  `SpellRank8` int unsigned NOT NULL DEFAULT '0',
  `SpellRank9` int unsigned NOT NULL DEFAULT '0',
  `PrereqTalent1` int unsigned NOT NULL DEFAULT '0',
  `PrereqTalent2` int unsigned NOT NULL DEFAULT '0',
  `PrereqTalent3` int unsigned NOT NULL DEFAULT '0',
  `PrereqRank1` tinyint unsigned NOT NULL DEFAULT '0',
  `PrereqRank2` tinyint unsigned NOT NULL DEFAULT '0',
  `PrereqRank3` tinyint unsigned NOT NULL DEFAULT '0',
  `VerifiedBuild` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`,`VerifiedBuild`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `forever_armory_source_pvp_talent` (
  `Description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ID` int unsigned NOT NULL DEFAULT '0',
  `SpecID` int unsigned NOT NULL DEFAULT '0',
  `SpellID` int NOT NULL DEFAULT '0',
  `OverridesSpellID` int NOT NULL DEFAULT '0',
  `Flags` int NOT NULL DEFAULT '0',
  `ActionBarSpellID` int NOT NULL DEFAULT '0',
  `PvpTalentCategoryID` int NOT NULL DEFAULT '0',
  `LevelRequired` int NOT NULL DEFAULT '0',
  `PlayerConditionID` int NOT NULL DEFAULT '0',
  `VerifiedBuild` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`,`VerifiedBuild`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
