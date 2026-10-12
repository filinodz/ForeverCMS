# Security policy

## Supported version

Security fixes target the latest release and the current `main` branch.

## Report a vulnerability privately

Please use GitHub's private vulnerability reporting for
[ForeverCMS](https://github.com/filinodz/ForeverCMS/security/advisories/new).
Do not open a public issue for authentication bypasses, SQL injection, exposed
credentials, remote administration abuse or player-data disclosure.

Include the affected version, reproduction steps, impact and a suggested fix if
available. Do not include real player credentials or data.

## Deployment notes

- Keep the CMS and PHP runtime patched.
- Use HTTPS and a reverse proxy for public hosting.
- Give the CMS only the database permissions it needs.
- Keep `config.secret.php`, SMTP credentials and RA credentials private.
- Disable verbose errors in production and protect local logs/backups.
- Back up the site database before migrations.
