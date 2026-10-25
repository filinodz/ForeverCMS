<p align="center">
  <img src="www/img/forever/wallpapers/brand-logo.jpg" alt="WoW Forever" width="720">
</p>

<h1 align="center">ForeverCMS</h1>

<p align="center">
  A Warcraft community portal for ForeverCore — with or without a launcher.
</p>

<p align="center">
  <a href="https://github.com/filinodz/ForeverCMS/actions"><img alt="PHP checks" src="https://img.shields.io/github/actions/workflow/status/filinodz/ForeverCMS/php-checks.yml?branch=main&label=PHP%20checks"></a>
  <img alt="PHP 8.2+" src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white">
  <img alt="ForeverRepack" src="https://img.shields.io/badge/ForeverRepack-ready-C9A227">
  <a href="LICENSE"><img alt="MIT license" src="https://img.shields.io/badge/license-MIT-3DA639"></a>
</p>

ForeverCMS connects to your ForeverCore databases independently of ForeverLauncher.
Run it with your own PHP/MySQL stack or keep the optional repack integration.
Players use their server account, and staff manage the portal from one themed
administration area. Adaptation and design by **FilinoDZ**, based on open-source SahtoutCMS.

## Install without ForeverLauncher

See [the standalone installation guide](docs/STANDALONE.md). On Windows,
run **Install-Standalone.bat** for a graphical database setup assistant.
It tests the connection, prepares only the website schema, and saves recoverable
configuration backups. It does not install a game launcher or reset game data.

| Deployment | Entry point | What it changes |
| --- | --- | --- |
| ForeverLauncher / Repack | `Install-ForeverCMS.bat` in the Repack release archive | Backed-up CMS integration with the existing Website service; existing core and launcher retained. |
| Standalone Windows | `Install-Standalone.bat` | Website database and local CMS configuration only; no launcher/core replacement. |
| Separate PHP host | [Standalone guide](docs/STANDALONE.md) and [hosting examples](docs/HOSTING.md) | Your PHP host connects to existing ForeverCore databases through a private network. |

## Highlights

- Interactive world map with continent/zone navigation, zoom, online character markers, realm selection, faction/name/bot filters and direct Armory links.
- Account registration, login, activation and recovery workflows.
- Multi-realm status with per-realm visibility controls, a live Players Online roster and direct Armory links.
- Native High Order and Windshaper Skyborne faction counting and themed race icons.
- Character directory and multi-realm search, separate from the PvP leaderboard.
- Equipped 3D character viewer, actual Classic talent trees and saved ranks, Legacy builds, spellbook and skills with real client icons.
- Enlarged 3D inspection with rotation, zoom, pause and camera reset; Classic armor metadata restores removed Retail helmet/shoulder appearances.
- Portable equipped appearances: a bundled stock item catalogue renders gear without ForeverCreator export tables; installed server exports override stock metadata.
- High Order / Windshaper model fallback verified against Forever client data, with explicit notices for unavailable exclusive customizations.
- Responsive Players Online directory with combinable search, class/faction filters and separate human/bot counts.
- News, downloads, guides, vote system and a premium shop synchronized with ForeverCore's in-game catalog.
- One shared `world.shop_*` catalog, one `auth.account_shop_points` wallet and one purchase history for the website and the game.
- Administration for content, users, realms, Discord and the shared shop; changes are reloaded in game automatically.
- Integrated ForeverCreator for items, creatures, quests, Classic teleporters, vendor inventory, loot, NPC placement and named destinations.
- Free visual editor for the complete **How to join us** page, starting with the installed layout rather than a blank canvas.
- Configurable Discord invite and home-page widget—no hard-coded community link.
- Configurable public realm visibility, Bugtracker panel and independent Points/Token display.
- Forever Launcher startup integration and a recoverable Windows installer.
- English and French interface.
- Clean distribution model: no player accounts, sessions or database dumps.

## Repack installation / update

The live map and portable appearance updates are currently available on `main`.
The older 1.6.0 release archives do not include them. See the
[map, Armory and update guide](docs/MAP-AND-ARMORY.md) when updating from source.

Download **ForeverCMS-1.6.0-for-ForeverRepack.zip** from Releases. This is a
website-only update: it does not replace your existing launcher or core.

1. Back up your databases, stop the Website service and close ForeverLauncher.
2. Extract the complete ForeverCMS release.
3. Run `Install-ForeverCMS.bat` from the extracted archive and select your repack.
4. For unattended folder selection, use the equivalent PowerShell command:

```powershell
.\Install-ForeverCMS.ps1 -RepackPath "D:\Path\To\ForeverRepack" -ReplaceExisting -SkipCoreUpdate -SkipLauncherUpdate
```

5. Start **Database**, **Auth Server**, **World Server** and **Web Server** from
   Forever Launcher, then open `http://127.0.0.1:8080`.

Both 1.6.0 Windows archives include PHP but no game core or launcher. The source
repository excludes generated secrets, local logs, backups and PHP. For separate
hosting use **ForeverCMS-1.6.0-Standalone-Windows.zip** and the standalone guide.

To update an existing installation, add `-ReplaceExisting`. Your current CMS and
worldserver are backed up; private settings and game accounts are retained. Do
not use `-ResetSiteDatabase` for a normal update.

Version 1.5 supplied a compatible worldserver and its corresponding source. Version
1.6.0 keeps that bridge as a prerequisite but does not downgrade/replace a newer
core. If your ForeverCore build lacks it, see the optional patch in [`core`](./core)
and rebuild your own server. Exported item/talent features need a compatible bridge;
installing only the PHP files does not add missing core functionality.

## Page studio

Open **Admin → Page studio** to edit `/how_to_play` in English or French. The
editor imports the installed page with all its text, links and layout. Double-click
text to edit it, select a link or image to change its properties, and use blocks
and the style panel to add sections or change the design. Images can use your own
URLs. Desktop, tablet and mobile canvases are included.

Save drafts without changing the public page, **Publish** the layout, or restore
a saved revision. **Use original page** disables the custom layout without
destroying the saved draft. Login, shop, rankings and other application screens
are not replaced by this editor.

The free bundled editor is GrapesJS, under BSD-3-Clause. No account, API key or
paid plan is required. Scripts, forms and arbitrary iframe embeds are removed
from published layouts for safety. The global header/footer remain controlled
by General Settings. Use `{{realmlist}}`, `{{launcher_url}}`, `{{site_title}}` and
`{{discord_url}}` to follow the current server settings.

## ForeverCreator and world integration

Use **Admin → ForeverCreator** to load an existing server template by ID or
search by name, choose a free creation ID, and edit its fields. All schema fields
are available, including advanced requirements, flags, sockets, rewards, spell
effects and Classic-specific NPC/quest tables. Item stats use the same budgets,
armor and damage data as the running core; the stat assistant converts actual
attribute points into the correct stored values.

The appearance library uses display IDs exported from your server. Items and
NPCs have a 3D preview; teleporters also have a live destination-menu preview.
Teleports are restricted to maps 0/1 and checked against installed terrain.
Vendor and loot tools support multiple rows per tracked creation.

**Save to world** commits the records and their ownership history together.
History lets you edit, delete or restore your own creations. Existing server
records cannot be overwritten. SQL export validates the same fields and IDs
without modifying the database.

Restart the updated worldserver after a save or deletion before testing in game.
Items also update the website's Armory index. If your game client retains an old
custom item, close it and clear its cache. This is not a live-edit or client-asset
authoring tool. Generated NPCs are not spawned automatically: use NPC placement
or `.npc add <entry>` after the restart. New quests need a quest giver/ender.

New records are stored in `world.forever_creator_*`, with normal world tables for
NPCs, quests, gossip, loot and placements. The bridge reads these definitions at
startup and sends the standard client data notifications **without writing SQL
hotfixes tables**. It also exports read-only template, appearance, spell and trait
metadata from the server's loaded DB2 data to `world`.

3D uses the bundled renderer, local verified assets and a cached, fixed-origin
gateway to the public Astral/Wowhead model libraries. Windows HTTPS certificate
verification remains enabled. Custom Forever client-only geometry unavailable
from these sources must be provided separately; an unavailable model does not
mean its server display ID is invalid. Live inspection previews stat values and
known spell names, not a simulation of every possible proc or scripted effect.

## Work from source

Requirements:

- Windows 10/11 or a PHP-compatible web server;
- PHP 8.2 or newer with MySQLi, DOM, cURL and mbstring;
- a running ForeverRepack database;
- a ForeverRepack checkout for launcher integration.

The web root is [`www`](./www). The local launcher bridge is
[`forever_web.py`](./forever_web.py), and database/bootstrap logic lives under
[`setup`](./setup). The release installer source is kept in [`installer`](./installer);
its published package must add `payload/cms` and the compatible launcher binary.

For local development inside a repack, place this repository at
`ForeverRepack\cms`, provide the `cms\php` runtime from a trusted ForeverCMS
release, then run `Setup-CMS.bat` followed by `Start-CMS.bat`.

## Configuration

Most settings are editable in **Admin Panel → Settings**. Advanced configuration
is under `www/includes`:

| File | Purpose |
|---|---|
| `config.php` | auth, world, characters and site database connection |
| `realm_config.php` | realm names, game ports, RA ports and character schemas |
| `config.settings.php` | branding, portal and community links |
| `config.mail.php` | SMTP; disabled by default |
| `config.cap.php` | reCAPTCHA; disabled by default |
| `ra.conf.php` | remote administration settings |

`config.secret.php` is generated locally and ignored by Git. Never commit it.

## Security and privacy

Do not expose the bundled development server directly to the Internet. A public
deployment should use HTTPS, a maintained Apache/nginx + PHP setup, restricted
database accounts, SMTP and reCAPTCHA. Review [`SECURITY.md`](./SECURITY.md) before
publishing an instance.

The repository excludes user data, sessions, logs, launcher backups and private
credentials. The installer reset option targets only the `forever_site` database;
it must never erase game databases.

## Project structure

```text
ForeverCMS/
├─ installer/        Release installer source
├─ setup/            Database and launcher setup
├─ sql/              Clean ForeverCMS schema/data
├─ www/              PHP application and local assets
├─ forever_web.py    Launcher-facing web process
├─ router.php        Local PHP router
└─ Start-CMS.bat     Local entry point
```

## Contributing

Bug reports and focused pull requests are welcome. Read
[`CONTRIBUTING.md`](./CONTRIBUTING.md) before changing database behavior, authentication,
the shop or launcher integration. By participating, you agree to the
[`CODE_OF_CONDUCT.md`](./CODE_OF_CONDUCT.md).

## Credits and license

Forever adaptation, launcher integration, packaging and visual design by
**[FilinoDZ](https://github.com/filinodz)**.

ForeverCMS is based on the open-source
[SahtoutCMS](https://github.com/SahtoutDev/SahtoutCMS). The upstream MIT notice is
preserved in [`LICENSE-SahtoutCMS.txt`](./LICENSE-SahtoutCMS.txt). ForeverCMS
modifications are available under the [MIT License](./LICENSE).

The optional ForeverCore patch and corresponding worldserver are GPL-2.0-or-later,
separate from the CMS's MIT license. Full corresponding core source accompanies
the release; see [`core/README.md`](./core/README.md). GrapesJS retains its own
BSD-3-Clause license. Model assets and the third-party renderer retain their
respective rights; they are not relicensed under the CMS license.

World of Warcraft and related names, artwork and assets are trademarks or
copyrighted works of Blizzard Entertainment. This fan-made community project is
not affiliated with or endorsed by Blizzard Entertainment.
