# Standalone ForeverCMS

ForeverCMS does not require ForeverLauncher or ForeverHub. It needs compatible
ForeverCore game databases and a PHP web server. It is not a generic WotLK CMS:
the Battle.net account schema and ForeverCore world/armory tables must exist.

## Requirements

- PHP 8.2+ with `mysqli`, `gmp`, `mbstring`, `openssl`, `dom`, `sodium`; enable `curl`,
  `fileinfo` and `gd` for the corresponding website features.
- MySQL 8 and existing ForeverCore `auth`, `world` and characters databases.
- A database account with the website schema permissions and the game-table
  permissions required by the features you enable. Use a dedicated account,
  not MySQL root, for the running website. Never publish MySQL to players.
- Apache, nginx or another production PHP host for a public deployment.

## Windows: guided configuration

1. Extract ForeverCMS into its own directory, independent of the repack.
2. Use PHP already installed on your PC, or put a portable PHP runtime in `php/`.
   The source repository does not contain the PHP runtime.
3. Start MySQL yourself using your existing server management method.
4. Double-click **Install-Standalone.bat**.
5. Enter your website name/URL, database host/port/user/password, the names of
   the accounts/world/characters databases, and a **separate website database**.
6. Enter the realm ID, realm name, game port and hostname. These must match
   your existing server. The assistant does not create or reconfigure realms.
7. Click **Test connection**, then **Install / configure**. Re-enter the password
   after a test if the password box has been cleared.
8. Use **Start-CMS.bat** for a local preview, or configure your production host
   with `www/` as its document root. PHP's built-in server is for local testing,
   not public hosting.
9. Log in with an existing server account. Existing GM-level-3 accounts receive
   website administrator access. Configure further realms in Admin → Realms.

The wizard creates/imports only the website schema and links website roles to
existing game accounts. It does **not** reset accounts or characters, change
the core, install certificates, open ports, or modify a launcher. Shared-shop,
world-authoring and armory exports depend on your installed ForeverCore schema;
their optional migration scripts are not silently applied by this installer.

## Other operating systems / manual configuration

Configure the standard PHP extensions and set `www/` as the document root.
Create the website database from `sql/forever_site.sql`. Supply your database
settings through `www/includes/config.database.php` (see the example below), and
configure your realm entries in `www/includes/realm_config.php`.

```php
<?php
if (!defined('ALLOWED_ACCESS')) exit('Forbidden');
foreach (['auth', 'world', 'char', 'site'] as $type) {
    ${'db_'.$type.'_host'} = '127.0.0.1';
    ${'db_'.$type.'_port'} = 3306;
    ${'db_'.$type.'_user'} = 'your_cms_user';
    ${'db_'.$type.'_pass'} = 'your_private_password';
}
$db_auth_name = 'auth';
$db_world_name = 'world';
$db_site_name = 'forever_site';
```

Keep this file outside version control and restrict its local filesystem access.
It is separate from `config.secret.php`, which holds the registration encryption
key. Preserve both during updates; never place database credentials in the key file.
The router and web-server configuration must deny direct access to `includes/`,
SQL, logs, backups and hidden files. Keep the setup directory outside the public
document root. Use HTTPS and configure sessions/mail before a public launch.

For working Apache and nginx routing examples, see [Hosting](HOSTING.md).

## Content and updates

- The default **How to Play** page is intentionally generic: no personal server
  address, client build, launcher download or server-specific connection tool.
  Use **Admin → Page Studio** to write your own instructions in English/French.
- Realm cards show status and player information, not repeated realmlist addresses.
- Keep private configuration, sessions, uploads, account data and backups out of
  GitHub/release archives. Back up the website database and configuration before
  updating. The installer stores configuration backups in `setup/backups/`.

Made with love by **FilinoDZ**. Based on the open-source **SahtoutCMS** project;
original license and attribution files remain included.

## Preparing a clean preview folder

From a Git checkout, run `setup/Stage-Standalone.ps1 -Destination <new-folder>`.
Optionally add `-PhpRuntime <portable-php-folder>` to include your approved PHP
runtime, extensions and their original licenses. The script excludes private
configuration, logs, sessions, backups and dumps, and produces SHA256SUMS.txt.
Review the contents before sharing. It creates no ZIP and publishes nothing.
