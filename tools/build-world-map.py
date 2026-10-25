"""Builds the website's live-map assets from the repack's DB2 files.

    python tools/build-world-map.py --art <MapFixForever>/Art --dbc <server>/data/dbc/frFR

Reads UiMap, UiMapAssignment, UiMapXMapArt and UiMapArtTile, stitches each
map's BLP tiles (MapFixForever's copy of the WoW Forever English map art) into
one WebP per map, and writes www/assets/data/world-map.json: the map tree and
the world-coordinate calibration used to place characters. No player data is
read or written.
"""
import argparse
import json
import os
import re
import sys

from PIL import Image

from wdc5 import DB2, as_float

HERE = os.path.dirname(os.path.abspath(__file__))
WWW = os.path.join(HERE, '..', 'www')

# The repack only ships frFR DB2 strings; English names for the website.
NAMES_EN = {
    947: 'Azeroth', 1414: 'Kalimdor', 1415: 'Eastern Kingdoms', 1411: 'Durotar', 1412: 'Mulgore',
    1413: 'The Barrens', 1416: 'Alterac Mountains', 1417: 'Arathi Highlands', 1418: 'Badlands',
    1419: 'Blasted Lands', 1420: 'Tirisfal Glades', 1421: 'Silverpine Forest', 1422: 'Western Plaguelands',
    1423: 'Eastern Plaguelands', 1424: 'Hillsbrad Foothills', 1425: 'The Hinterlands', 1426: 'Dun Morogh',
    1427: 'Searing Gorge', 1428: 'Burning Steppes', 1429: 'Elwynn Forest', 1430: 'Deadwind Pass',
    1431: 'Duskwood', 1432: 'Loch Modan', 1433: 'Redridge Mountains', 1434: 'Stranglethorn Vale',
    1435: 'Swamp of Sorrows', 1436: 'Westfall', 1437: 'Wetlands', 1438: 'Teldrassil', 1439: 'Darkshore',
    1440: 'Ashenvale', 1441: 'Thousand Needles', 1442: 'Stonetalon Mountains', 1443: 'Desolace',
    1444: 'Feralas', 1445: 'Dustwallow Marsh', 1446: 'Tanaris', 1447: 'Azshara', 1448: 'Felwood',
    1449: "Un'Goro Crater", 1450: 'Moonglade', 1451: 'Silithus', 1452: 'Winterspring',
    1453: 'Stormwind City', 1454: 'Orgrimmar', 1455: 'Ironforge', 1456: 'Thunder Bluff', 1457: 'Darnassus',
    1458: 'Undercity', 1459: 'Alterac Valley', 1460: 'Warsong Gulch', 1461: 'Arathi Basin',
    2482: 'Mount Hyjal', 2521: 'Zephras Isle', 2524: 'Darkspear Isles', 2652: "Shen'dralas",
}
TILE = 256
VISIBLE = (1002, 668)  # WoW world map canvas inside the 4x3 tile grid


def load_art_index(art_root):
    """FileDataID -> BLP path, from the addon's Data.lua table."""
    data_lua = os.path.join(art_root, '..', 'Data', 'Data.lua')
    index = {}
    with open(data_lua, encoding='utf-8') as handle:
        for file_id, path in re.findall(r'\[(\d+)\]\s*=\s*"([^"]+)"', handle.read()):
            index[int(file_id)] = os.path.join(art_root, *path.split('\\\\')) + '.blp'
    return index


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--art', required=True, help='MapFixForever Art folder')
    parser.add_argument('--dbc', required=True, help='Folder containing the matching client UiMap*.db2 tables')
    parser.add_argument('--quality', type=int, default=84)
    args = parser.parse_args()

    art_index = load_art_index(os.path.abspath(args.art))
    table = lambda name: DB2(os.path.join(args.dbc, name + '.db2'))
    ui_map, assignment, x_art, art_tile = table('UiMap'), table('UiMapAssignment'), table('UiMapXMapArt'), table('UiMapArtTile')

    maps = {}
    for map_id, cols, _ in ui_map.records():
        if cols[4][0] != 0:  # System 1 = taxi/flight-map duplicates
            continue
        maps[map_id] = {'id': map_id, 'parent': cols[2][0], 'type': cols[5][0],
                        'name': {'fr': ui_map.string(map_id, 0), 'en': NAMES_EN.get(map_id, ui_map.string(map_id, 0))},
                        'assignments': []}
    for _, cols, _ in assignment.records():
        target = maps.get(cols[4][0])
        if not target:
            continue
        ui_min = [as_float(v) for v in cols[0]]
        ui_max = [as_float(v) for v in cols[1]]
        region = [as_float(v) for v in cols[2]]
        target['assignments'].append({'map': cols[6][0], 'area': cols[7][0], 'order': cols[5][0],
                                      'region': [round(region[0], 3), round(region[1], 3), round(region[3], 3), round(region[4], 3)],
                                      'ui': [round(ui_min[0], 5), round(ui_min[1], 5), round(ui_max[0], 5), round(ui_max[1], 5)]})

    art_for_map = {rel: cols[1][0] for _, cols, rel in x_art.records() if cols[0][0] == 0}
    tiles = {}
    for _, cols, art_id in art_tile.records():
        if cols[2][0] == 0:  # base layer only
            tiles.setdefault(art_id, []).append((cols[0][0], cols[1][0], cols[3][0]))

    out_dir = os.path.join(WWW, 'assets', 'img', 'worldmap')
    os.makedirs(out_dir, exist_ok=True)
    built = 0
    for map_id, entry in maps.items():
        grid = tiles.get(art_for_map.get(map_id), [])
        missing = [fid for _, _, fid in grid if fid not in art_index]
        if not grid or missing:
            entry['image'] = None
            print(f'  {map_id} {entry["name"]["en"]}: no art ({len(missing)} of {len(grid)} tiles unavailable)')
            continue
        rows = max(r for r, _, _ in grid) + 1
        columns = max(c for _, c, _ in grid) + 1
        canvas = Image.new('RGB', (columns * TILE, rows * TILE))
        for row, column, file_id in grid:
            with Image.open(art_index[file_id]) as tile:
                canvas.paste(tile.convert('RGB').resize((TILE, TILE)), (column * TILE, row * TILE))
        canvas = canvas.crop((0, 0, min(VISIBLE[0], canvas.width), min(VISIBLE[1], canvas.height)))
        name = f'{map_id}.webp'
        canvas.save(os.path.join(out_dir, name), 'WEBP', quality=args.quality, method=6)
        entry['image'] = 'assets/img/worldmap/' + name
        entry['size'] = list(canvas.size)
        built += 1

    document = {'schema': 1, 'source': 'Forever client UiMap* tables and MapFixForever map art', 'maps': sorted(maps.values(), key=lambda m: m['id'])}
    os.makedirs(os.path.join(WWW, 'assets', 'data'), exist_ok=True)
    with open(os.path.join(WWW, 'assets', 'data', 'world-map.json'), 'w', encoding='utf-8') as handle:
        json.dump(document, handle, ensure_ascii=False, separators=(',', ':'))
    print(f'Built {built} map images, {len(maps)} maps calibrated.')
    return 0 if built else 1


if __name__ == '__main__':
    sys.exit(main())
