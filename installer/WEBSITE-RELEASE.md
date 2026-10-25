# ForeverCMS 1.7.0 — ForeverRepack website update

Made with love by **FilinoDZ**. Based on open-source **SahtoutCMS**; original
license and credits are included in `payload/cms/`.

This package updates the website and its integration with an **existing compatible
ForeverRepack**. It does not replace ForeverLauncher or worldserver, change the
client build, install a game client, or reset game accounts/characters.

## Install or update

1. Back up your databases. Stop the Website/CMS service and close ForeverLauncher.
   Keep the repack's existing MySQL available. Do not run another repack on port 3317.
2. Extract the complete ZIP outside your existing `cms/` folder.
3. Double-click **Install-ForeverCMS.bat** and select your ForeverRepack root.
4. Existing CMS files and launcher settings are backed up under
   `ForeverCMS-Backups/<timestamp>`. Private database, SMTP, realm and site settings
   are retained. The website schema/integration setup is rerun; game databases are
   not reset. Existing Page Studio publications are kept.
5. Start Website from ForeverLauncher and open `http://127.0.0.1:8080`.

Command-line equivalent:

```powershell
.\Install-ForeverCMS.ps1 -RepackPath "D:\Path\To\ForeverRepack" -ReplaceExisting -SkipCoreUpdate -SkipLauncherUpdate
```

Add `-DryRun` to validate the destination without installing anything. Do not use
`-ResetSiteDatabase` for a normal update. The legacy repack setup expects its
existing local MySQL schema/account conventions; for other layouts or a separate
web host, use the **Standalone Windows** archive and `Install-Standalone.bat`.

## Compatibility and limits

Use the ForeverCore armory/creator bridge required by ForeverCMS 1.5 or a newer
compatible implementation. This website-only update does not add that bridge to
an unmodified core. See `payload/cms/core/README.md` for its source patch; do not
downgrade an independently updated core to install this CMS release.

High Order/Windshaper previews use a verified client model fallback. Exclusive
client-only appearances unavailable from the 3D provider are identified on screen.

The complete documentation is in `payload/cms/README.md`, `docs/STANDALONE.md`
and `docs/HOSTING.md`. For public hosting use a production PHP server and HTTPS,
not the local preview server. Configure your own server identity, SMTP, realms and
How to Play content in the administration panel before inviting players.
