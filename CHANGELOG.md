# Changelog

All notable changes to ForeverCMS will be documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
