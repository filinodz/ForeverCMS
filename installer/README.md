# ForeverCMS installer for Forever Launcher / Repack

ForeverCMS is the local community portal built for ForeverRepack: Battle.net account management, news, public realm status, live player roster, Armory with 3D previews, voting, the shared ForeverCore shop and administration.

## Credits

- Forever adaptation, launcher integration, packaging and original visual design: **FilinoDZ**.
- Based on the open-source [SahtoutCMS](https://github.com/SahtoutDev/SahtoutCMS), licensed under the MIT License. Its original license is included.
- This fan-made community project is not affiliated with or endorsed by Blizzard Entertainment.

## Installation

1. Close Forever Launcher.
2. Extract the complete release archive to a normal folder.
3. Double-click `Install-ForeverCMS.bat`.
4. Start Forever Launcher, open **LOCAL SERVER**, then start Database, Auth Server, World Server and Web Server.
5. Open the website button or visit `http://127.0.0.1:8080`.

The installer normally finds ForeverRepack automatically. To specify it manually:

```powershell
.\Install-ForeverCMS.ps1 -RepackPath "D:\Path\To\ForeverRepack"
```

To validate the package and destination without changing anything:

```powershell
.\Install-ForeverCMS.ps1 -RepackPath "D:\Path\To\ForeverRepack" -DryRun
```

## Existing ForeverCMS installations

The installer refuses to overwrite an existing `cms` folder unless `-ReplaceExisting` is supplied. A replacement first backs up the complete previous CMS and launcher configuration under `ForeverCMS-Backups\<timestamp>`.

```powershell
.\Install-ForeverCMS.ps1 -RepackPath "D:\Path\To\ForeverRepack" -ReplaceExisting
```

The existing `forever_site` database is preserved by default. Add `-ResetSiteDatabase` only when you intentionally want to remove CMS-specific accounts, settings and history. This option never deletes the repack's `auth`, `characters*`, `world` or `hotfixes` databases.

## Shared in-game shop

Setup installs the Forever premium products into the canonical `world.shop_*` tables and migrates existing balances to `auth.account_shop_points`. ForeverCMS and ForeverCore therefore use the same products, prices and Points wallet. Shop changes made in the CMS administration are reloaded in game automatically.

## Included

- Forever premium design and community-supplied WoW Forever backgrounds.
- Configurable Discord widget, public realms, Bugtracker, Points and Tokens.
- Live Players Online page linked to the Armory.
- Shared CMS/in-game shop and premium catalog.
- Local 3D character viewer assets.
- Portable PHP runtime and one-click setup.
- Clean CMS schema without bundled player accounts, sessions, logs, secrets or database dumps.
