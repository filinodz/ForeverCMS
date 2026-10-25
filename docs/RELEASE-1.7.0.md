# ForeverCMS 1.7.0 — Live world map and portable Armory

Developed and adapted by **[FilinoDZ](https://github.com/filinodz)**, based on open-source **SahtoutCMS**.

## What's new

- Interactive world map with 53 bundled map images, continent/zone navigation, zoom, drag and touch controls.
- Online character markers, realm selection, name/faction/bot filters and direct Armory links.
- Read-only position feed refreshed every 20 seconds. Coordinates reflect the server's last character save, not instantaneous movement; staff accounts stay hidden.
- Portable stock equipment appearance catalogue: Armory gear can render without the optional ForeverCreator export tables. Available server metadata takes priority.
- Enlarged 3D inspection, rotation, zoom, pause/reset and notices for unavailable external appearances.
- README screenshots of the map, equipped Armory profile and enlarged inspection, plus a dedicated upgrade guide.
- Shared online-presence helper included for the existing Players Online page.

English and French are supported. No VIP module or content tracker integration is included.

## Downloads

- **ForeverCMS-1.7.0-Standalone-Windows.zip** — PHP included. Run `Install-Standalone.bat` to configure your existing ForeverCore databases without a launcher.
- **ForeverCMS-1.7.0-for-ForeverRepack.zip** — PHP included. Run `Install-ForeverCMS.bat` to install/update only the website on an existing compatible ForeverRepack.
- **ForeverCMS-1.7.0-SHA256SUMS.txt** — SHA-256 checksums for both archives. Each package also includes its file manifest.

The GitHub source archives contain the source and screenshots; the two Windows packages additionally contain PHP and its extensions/licenses.

## Updating

Back up your CMS folder and databases. Retain private database, registration-key, realm, mail and site settings. For Repack updates, the supplied entry point preserves the existing core and launcher. Do not reset the website database for a normal update.

No game database migration or game-server restart is required for the new map/appearance features. Existing world-authoring and exported talent features still require the compatible ForeverCore bridge documented under `core/`.

Map settings are `live_map_enabled` and `live_map_show_bots`; both default to true when absent. The new navigation entry and feed are disabled when the map setting is false.

See [the map and Armory upgrade guide](MAP-AND-ARMORY.md), [standalone installation](STANDALONE.md) and [hosting examples](HOSTING.md).

## Validation

PHP syntax checks, isolated map API/page tests, coordinate projection and filter tests, Armory appearance fallback checks, existing CMS regression tests and Windows installer dry-run checks pass. The screenshots show the public CMS running locally against an existing server; the map capture shows an idle realm.

Models and textures remain subject to provider availability. Exclusive client-only assets must be supplied separately. Game content and artwork belong to their respective owners; ForeverCMS code retains its existing license.
