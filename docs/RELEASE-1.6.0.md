# ForeverCMS 1.6.0 — release candidate

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

Before publishing: review screenshots, test installation on a fresh compatible
ForeverCore database, and verify mail/HTTPS configuration on the intended host.
Never package your production configuration, accounts, sessions, backups or dumps.
