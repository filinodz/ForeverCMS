# Changelog

All notable changes to ForeverCMS will be documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.6.0] - 2026-10-16

### Added

- Independent Windows configuration assistant for existing ForeverCore databases, without requiring ForeverLauncher.
- Standalone PHP hosting documentation, Apache/nginx examples and clean staging with a SHA-256 manifest.
- Enlarged character inspection with zoom, rotation, pause, camera reset, keyboard close and responsive canvas sizing.
- Verified High Order and Windshaper client model fallback; exclusive appearances unavailable from the provider are disclosed.
- Responsive Players Online layout, combined name/class/race search, class/faction filters and separate human/bot counts.
- Regression checks for standalone settings, encryption-key isolation, generic join guides and roster filters.

### Fixed

- Classic helmet/shoulder models missing from the Retail appearance catalog.
- Incomplete customization choices, distorted canvas resizing, repeated retries and viewer recovery after browser navigation.
- Language selector and translated navigation overflowing laptop-width headers.
- Repeated realmlist addresses beneath public realm cards.
- Standalone database settings now use an ignored `config.database.php`, separate from the registration encryption key.

### Deployment

- The default How to Play page is generic and editable through Page Studio, without a personal server address, fixed client build or launcher download.
- Existing ForeverLauncher/Repack integration remains available; standalone setup does not replace launchers or game-server executables.
- Existing published page revisions and private configuration must be preserved during updates.

## [1.5.0] - 2026-10-14

### Added

- Free, bundled GrapesJS page studio: drag-and-drop layouts, text, images, responsive canvas, drafts, publication and revision restoration.
- Complete How to join us page editing, starting from the installed layout; application screens are not replaced.
- Character directory / multi-realm search separate from the PvP leaderboard, which now ranks by honorable kills.
- Actual Classic and Legacy talent trees, saved build ranks, spellbook and skills with client-data icons.
- Realmlist / Battle.net portal and launcher download link in General Settings, with dynamic page tokens.
- World-backed ForeverCreator studio for items, creatures, quests, teleporters, vendor inventories, loot, NPC placement and named destinations.
- Complete schema-based fields, real template search, appearance picker, live item / NPC 3D preview and Classic-safe teleporter menu preview.
- Stat assistant using the core's actual item budgets, armor and weapon damage tables.
- Transactional world creation history with edit, delete, restore, revision guards and validated SQL export.
- Optional ForeverCore world authoring bridge and compatible Windows worldserver, with corresponding source and build instructions.

### Changed

- New creations write to `world` only; the updated server loads custom item definitions and notifies the client without writing to SQL hotfixes tables.
- Item appearance resolution now uses the actual server's DB2 appearance library, rather than a single hard-coded shirt.
- Existing records cannot be overwritten by the creator; removal targets only recorded ownership keys.

### Fixed

- Load template now reads the server's exported world library and reports actionable errors.
- Windows certificate validation for 3D asset requests, missing CDN geometry through a fixed-origin archive fallback, and modern nested humanoid NPC metadata.
- Item icons restored in the live inspection and updated when the appearance changes.
- Armory 3D rendering includes the character's actual equipped display IDs.
- Dark form selectors retain readable text and backgrounds.

### Notes

- Restart the updated worldserver after saving or deleting creations. This is not an in-game live-edit feature.
- 3D previews depend on publicly available model data; custom client-only assets must be provided separately.

## [1.4.0] - 2026-10-13

### Added

- ForeverCreator item studio inside the administration panel with a live WoW-style inspection preview.
- Direct, transactional item creation in ForeverCore's `hotfixes` tables and `world.armory_item` index.
- Full item controls for classification, requirements, prices, ten stat slots, sockets, flags and five spell effects.
- Protected creation history with edit and delete actions for items created by the integrated studio.
- Dedicated realm switcher in Realm Configuration for Normal, PvP, RP and Hardcore.

### Changed

- Players Online now uses a responsive premium roster with live search, faction badges, class identity, location and level medallions.
- Realm Configuration now edits realm type, character database, game port, RA port and public visibility without leaving the page.

### Fixed

- Missing race artwork can no longer break the Players Online card layout.
- ForeverCreator creation and history updates are committed atomically across the server databases.

## [1.3.0] - 2026-10-13

### Added

- Premium Players Online page with public-realm tabs, live Alliance/Horde counts and direct character Armory links.
- One canonical shop catalog shared by ForeverCMS and ForeverCore through `world.shop_group`, `world.shop_product` and `world.shop_product_action`.
- One shared Points wallet through `auth.account_shop_points`, including vote rewards, administration and purchase history.
- Canonical shared-shop administration with automatic in-game cache reload.
- Installer migration for the shared shop catalog and existing CMS balances.

### Changed

- Points and Tokens now have separate visibility settings; Points remain available without exposing unused Tokens.
- The public shop clearly identifies services that must be completed through the in-game shop.

### Fixed

- Gold, character services, level boosts and professions now use bundled local artwork instead of a missing placeholder image.
- Shop item icons automatically fall back to a valid local category image if the external icon service cannot load.

## [1.2.0] - 2026-10-13

### Added

- Administration settings to enable or disable the home-page Bugtracker panel and configure its external URL.
- Administration setting to show or hide Points and Tokens throughout the public account interface.

### Changed

- A realm hidden in Realm Configuration is now removed from both the public status panel and the public realm selector, while remaining available to staff.
- The home-page media panel now displays only the configured content; when Bugtracker is disabled, the YouTube video no longer shows an unnecessary tab bar.

## [1.1.0] - 2026-10-12

### Added

- Per-realm visibility control for the public home-page status panel.
- Native High Order and Windshaper Skyborne emblems across account, armory and administration views.

### Fixed

- General settings can be saved without configuring a featured YouTube video.
- Discord configuration updates immediately and falls back to the invite button when the official server widget is disabled.
- Alliance and Horde counters now include Forever's custom race IDs, including High Order (95) and Windshaper (96).
- Logout now clears the named ForeverCMS session and its browser cookie instead of returning users to the account page.

## [1.0.0] - 2026-10-12

### Added

- Forever-themed CMS and launcher integration.
- Multi-realm portal, armory and local 3D character preview.
- Configurable Discord integration, premium shop and administration area.
- Clean community installer and database bootstrap.
