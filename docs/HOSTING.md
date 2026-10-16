# Hosting ForeverCMS separately

Keep the complete CMS source outside the public document root. Only `www/`
is public. Use PHP 8.2+ with the extensions in [STANDALONE.md](STANDALONE.md).
Your game core continues running separately; no ForeverLauncher process is
needed on the website host. Connect to its databases through a private network,
VPN or restricted tunnel. Do not expose MySQL or a world console to players.

## Apache

Example virtual host (adapt paths and domain):

```apache
<VirtualHost *:80>
    ServerName cms.example.com
    DocumentRoot /srv/ForeverCMS/www
    <Directory /srv/ForeverCMS/www>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <Directory /srv/ForeverCMS/www/includes>
        Require all denied
    </Directory>
</VirtualHost>
```

Enable `mod_rewrite` and your PHP handler. The supplied `www/.htaccess`
handles the application and model-asset routes. Do not serve `/srv/ForeverCMS`
itself: it contains SQL, setup tools and configuration backups.

## nginx + PHP-FPM

Example HTTP virtual host; adapt the PHP-FPM socket and add HTTPS using your
hosting provider's certificate procedure before opening registration:

```nginx
server {
    listen 80;
    server_name cms.example.com;
    root /srv/ForeverCMS/www;
    index index.php;

    location ~ /\. { deny all; }
    location ~ ^/languages/flags/[A-Za-z0-9_-]+\.png$ {
        try_files $uri =404;
    }
    location ~ ^/(includes|languages|vendor|logs|cache|sql)(/|$) {
        deny all;
    }
    location /model-assets/ {
        rewrite ^/model-assets/(.+)$ /pages/model_asset.php?asset=$1 last;
    }
    location / {
        try_files $uri $uri/ @forever_page;
    }
    location @forever_page {
        rewrite ^/(.+?)/?$ /pages/$1.php last;
    }
    location ~ \.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
```

Give the PHP worker write access only where features require it: `tmp/`, `logs/`,
`cache/models/`, private configuration files and the specific editable CMS settings.
Keep backups outside `www/` and protect them from other local users. Do not make
the entire website writable to everyone. Restrict runtime database privileges to
the enabled features; world editing and the shared shop need extra permissions.

Verify `/how_to_play`, `/players_online`, `/character?guid=<existing-id>`, admin
login and a model-asset request. Verify that direct `/includes/config.database.php`
access is refused and that no setup/backups folder is reachable. Configure SMTP,
HTTPS, secure sessions and a backup policy before accepting public accounts.

`Start-CMS.bat` and PHP's built-in server are local preview tools, not a production
Internet hosting stack. Remote CMS deployment does not change the game's portal,
certificate, build compatibility or router configuration.
