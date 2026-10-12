<p align="center">
  <img src="www/img/forever/wallpapers/brand-logo.jpg" alt="WoW Forever" width="720">
</p>

<h1 align="center">ForeverCMS</h1>

<p align="center">
  A premium community portal built for Forever Launcher and ForeverRepack.
</p>

<p align="center">
  <a href="https://github.com/filinodz/ForeverCMS/actions"><img alt="PHP checks" src="https://img.shields.io/github/actions/workflow/status/filinodz/ForeverCMS/php-checks.yml?branch=main&label=PHP%20checks"></a>
  <img alt="PHP 8.2+" src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white">
  <img alt="ForeverRepack" src="https://img.shields.io/badge/ForeverRepack-ready-C9A227">
  <a href="LICENSE"><img alt="MIT license" src="https://img.shields.io/badge/license-MIT-3DA639"></a>
</p>

ForeverCMS brings the server website into the Forever experience: the launcher
starts it with the local stack, players use their Battle.net account, and staff
manage the portal from one themed administration area.

## Highlights

- Account registration, login, activation and recovery workflows.
- Multi-realm status with per-realm visibility controls, character lists and armory pages.
- Native High Order and Windshaper Skyborne faction counting and themed race icons.
- Local 3D character viewer with bundled model data.
- News, downloads, guides, vote system and premium shop.
- Administration for content, users, realms, Discord and shop settings.
- Configurable Discord invite and home-page widget—no hard-coded community link.
- Forever Launcher startup integration and a recoverable Windows installer.
- English and French interface.
- Clean distribution model: no player accounts, sessions or database dumps.

## Install a release

The recommended community installation is the packaged release:

1. Stop Forever Launcher.
2. Extract the complete ForeverCMS release.
3. Run `Install-ForeverCMS.bat`.
4. Pass a custom location if auto-detection does not find the repack:

```powershell
.\Install-ForeverCMS.ps1 -RepackPath "D:\Path\To\ForeverRepack"
```

5. Start **Database**, **Auth Server**, **World Server** and **Web Server** from
   Forever Launcher, then open `http://127.0.0.1:8080`.

The release payload includes the runtime required by the launcher. This source
repository intentionally excludes generated secrets, local logs, backups and the
bundled PHP runtime.

## Work from source

Requirements:

- Windows 10/11 or a PHP-compatible web server;
- PHP 8.2 or newer with PDO MySQL;
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

World of Warcraft and related names, artwork and assets are trademarks or
copyrighted works of Blizzard Entertainment. This fan-made community project is
not affiliated with or endorsed by Blizzard Entertainment.
