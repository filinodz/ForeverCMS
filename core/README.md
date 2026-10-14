# ForeverCMS world authoring bridge

This optional patch adds world-backed custom item definitions and read-only
template, appearance, spell and talent libraries for ForeverCMS 1.5.

Base source: [ForeverCore, forever branch](https://github.com/advocaite/TrinityCore/tree/691c2362c77f).
Tested commit: `691c2362c77f`.

Apply `forever_creator_world.patch` to that source with `git apply`, then use
the core's documented build procedure. The patch modifies `DB2Store.h`,
`DB2Store.cpp` and `World.cpp`. Import the CMS schemas
`sql/forever_creator_world.sql` and `sql/forever_armory_library.sql` into world,
then start the new worldserver once to export the libraries.

No SQL hotfix tables are written. Custom world records are loaded before store
indexing and announced through the normal client data protocol. The export uses
the actual loaded DB2 records, not a guessed WotLK item_template schema.

The release contains a matching Windows worldserver and full corresponding
source in `ForeverCore-source-for-ForeverCMS-1.5.0.zip`. Keep your existing server
configuration, data files and compatible runtime DLLs. Rebuild your own source
if you maintain additional core changes instead of replacing that custom binary.

The source archive preserves all server, dependency and build sources, plus
current SQL. Only superseded historical migrations under `sql/old` are omitted;
they are not required to compile the server.

These modifications and the core binary are GPL-2.0-or-later, inherited from
TrinityCore / ForeverCore; see `COPYING`. The PHP CMS remains separately MIT.
ForeverCMS integration by FilinoDZ. Upstream copyright and authors are retained.
