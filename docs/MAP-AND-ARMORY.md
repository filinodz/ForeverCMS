# Live map and portable Armory appearances

These updates are available in the source repository's `main` branch. The older
1.6.0 Windows release archives do not contain them.

## Live world map

Open `/map` to navigate Azeroth, continents and zones. Use the zoom buttons,
Ctrl/Command + wheel, drag or touch gestures. Select an adventurer in the roster
to locate them; select their map marker to open their Armory profile.

The map supports public realm selection, character-name search, faction filters
and a bot toggle. Bot detection uses character-level Playerbot V2 records when
available, with legacy account-based detection as a fallback. Without either
schema, characters are treated as players.

The browser requests `/map_api?realm=<id>` every 20 seconds while the tab is
visible. Coordinates are **the last positions saved in the characters database**:
freshness depends on your core's `PlayerSaveInterval` (commonly about 90 seconds).
This does not stream instantaneous movement. Characters inside instances may be
listed without a marker where no calibrated map artwork exists.

Only configured public realms are exposed. Deleted characters and accounts with
positive staff security levels are excluded. If staff access cannot be checked,
the feed returns an error rather than exposing their locations. The feed reads
the existing databases and never writes player data. At most 2,000 online
characters are returned; the side roster displays up to 300 matching entries.

Add these settings to `www/includes/config.settings.php`, preserving your other
site settings:

```php
$live_map_enabled = true;
$live_map_show_bots = true;
```

Set `live_map_enabled` to `false` to hide the menu and disable the page/feed.
Set `live_map_show_bots` to `false` to exclude detected bots from the API entirely.
Both settings default to `true` when absent. There is no SQL migration.

## Armory and 3D equipment

The bundled `www/assets/data/item-appearances-forever.json` contains static stock
item metadata exported from ForeverCore's client-data library, with no accounts,
characters or equipment histories. Known stock gear can therefore render even
when the optional Creator export tables have not been installed.

When available, the server's `forever_creator_*` / `forever_creator_source_*`
appearance tables take precedence. Missing individual items fall back to the
stock catalogue. Unknown custom items need their actual server-side appearance
metadata; their display IDs are never guessed. Model geometry and textures still
depend on the bundled assets and the configured fixed-origin model gateway.
Unavailable external appearances are indicated in the viewer. Enlarged
inspection, zoom, rotation, pause, reset and Skyborne model fallback are retained.

## Update an existing installation

1. Back up your CMS folder and private configuration. For a source checkout,
   pull `main`; for a separate deployment, copy the changed application files
   from the source repository to your CMS installation.
2. Include the complete map assets: `www/pages/map.php`, `map_api.php`,
   `www/assets/js/pages/live-map.js`, `www/assets/css/live-map.css`,
   `www/assets/data/world-map.json` and `www/assets/img/worldmap/`.
3. Include the updated `www/includes/header.php`, `forever_models.php`,
   `online_presence.php`, `www/assets/js/pages/character-viewer.js` and the
   `item-appearances-forever.json` catalogue.
4. Keep your existing `config.database.php`, `config.secret.php`, `config.php`,
   `realm_config.php` and site settings. Merge only the two optional map settings
   above into `config.settings.php`. Do not copy configuration from another server.
5. Open `/map` and a character's Armory profile in English and French. Refresh
   the browser cache or restart your PHP worker if cached code is still served.

The update does not require a game server restart, database reset, game core or
launcher replacement. To roll back, restore the backed-up CMS files together.

## Rebuild the map assets (optional)

The map is ready to use with the bundled calibrated JSON and 53 WebP images.
To rebuild for matching client data, install Python 3 with Pillow and provide
the relevant `UiMap*.db2` files and MapFixForever `Art/` and sibling `Data/`
folders:

```bash
python -m pip install Pillow
python tools/build-world-map.py --dbc "/path/to/client/dbc/frFR" --art "/path/to/MapFixForever/Art"
```

The tool reads local client metadata/artwork and writes static map assets only.
It never reads player positions. It supports the WDC5 layouts used by this
catalogue; rebuilding against other client layouts may require adaptation.

Map artwork and game content belong to Blizzard Entertainment and their
respective authors. ForeverCMS code remains under its existing license.
