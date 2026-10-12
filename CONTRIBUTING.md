# Contributing to ForeverCMS

Thank you for helping improve ForeverCMS. Contributions should preserve its two
priorities: a polished Forever experience and safe interaction with player data.

## Before opening an issue

- Search existing issues and the troubleshooting section in the README.
- Reproduce the problem on the latest `main` branch.
- Remove passwords, account e-mails, IP addresses, session IDs and database dumps
  from screenshots and logs.
- For a security problem, follow `SECURITY.md` instead of opening a public issue.

## Development workflow

1. Fork the repository and create a short branch from `main`.
2. Keep one change per branch.
3. Follow the existing PHP, CSS and JavaScript style.
4. Never add generated secrets, runtime logs, backups or real player data.
5. Validate every changed PHP file with `php -l`.
6. Test in both English and French when user-facing text changes.
7. Test the desktop and responsive layouts.
8. Explain database migrations and include a safe rollback when applicable.

## Pull requests

Describe the user-visible result, how it was tested and any database or launcher
impact. Add before/after screenshots for visual work. A pull request may be held
until it avoids destructive schema changes, preserves upstream credits and passes
the automated checks.

## Visual direction

Use the established Forever palette, typography, spacing and real game-related
art direction. Avoid generic generated fantasy imagery, unrelated expansion logos,
visual clutter and inaccessible text contrast.

## Credits

Do not remove the SahtoutCMS attribution, the MIT notices, the FilinoDZ credit or
third-party notices. Contributions are submitted under the repository's license.
