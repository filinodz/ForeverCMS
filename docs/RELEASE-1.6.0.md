# ForeverCMS 1.6.0 — Armory inspection, live roster and standalone hosting

- Refined realm cards and reading surfaces while keeping the Warcraft artwork.
- Removed the repeated realmlist/address and copy button below each realm.
- Compact navigation at tablet/laptop widths keeps translated menus and the
  language selector inside the viewport.
- Generic English/French How to Play layout, editable through Page Studio.
- No personal server address, fixed client build or launcher download in the guide.
- Standalone Windows configuration assistant with connection checks, website-only
  schema preparation and configuration backups. ForeverLauncher is optional.
- Private local database configuration, excluded from Git and release staging.
- Updated installation and independent hosting documentation.
- Enlarged 3D character inspection, reliable resizing and Classic armor metadata.
- Explicit High Order / Windshaper model fallback and missing-appearance notices.
- Redesigned responsive roster with combined search/type/class/faction filters.
- Separate private database settings and registration encryption keys.

Both deployment modes are retained: ForeverLauncher/Repack integration and
independent PHP hosting against compatible ForeverCore databases. No personal
server configuration or client connection executable is included.

Adaptation and design by **FilinoDZ**. Based on open-source **SahtoutCMS**.

## Downloads

- **ForeverCMS-1.6.0-for-ForeverRepack.zip** — PHP included, folder picker and backed-up
  website installation/update. Run `Install-ForeverCMS.bat`. The existing core and
  launcher are preserved; a compatible ForeverCore installation is required.
- **ForeverCMS-1.6.0-Standalone-Windows.zip** — PHP included. Run
  `Install-Standalone.bat` to connect to existing compatible game databases without
  ForeverLauncher. For Apache/nginx hosting, use `www/` as the public document root
  and follow `docs/STANDALONE.md` and `docs/HOSTING.md`.
- **ForeverCMS-1.6.0-SHA256SUMS.txt** — checksums of both archives.

This is a CMS-only update. It does not bundle a game client, account database,
core executable, certificate/private key or launcher. The original 1.5 authoring
bridge (or a newer compatible implementation) remains required for world-authoring
and exported item/talent features. No core downgrade is needed for this release.

Existing published How to Play revisions remain unchanged on update. Use Page
Studio's original-page option to adopt the new generic layout, or keep/edit your
current guide. Back up your databases before installation. Do not reset the website
database for an update. Configure your own server identity, SMTP and HTTPS.

Verification includes PHP/JavaScript checks, Windows repack installer validation,
fresh/repeated standalone installation against isolated database fixtures, and
desktop/mobile UI checks. Missing exclusive 3D appearances remain a provider limit,
not a claim of an exact in-game Skyborne customization replica.
