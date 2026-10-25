const assert = require('node:assert/strict');
const {readFileSync, existsSync} = require('node:fs');
const {join} = require('node:path');
const {runInNewContext} = require('node:vm');
const root = join(__dirname, '..');
const catalogue = JSON.parse(readFileSync(join(root, 'www/assets/data/world-map.json'), 'utf8'));
assert.equal(catalogue.schema, 1);
assert.ok(catalogue.maps.some(map => map.id === 947 && map.image));
for (const map of catalogue.maps) {
  if (map.image) assert.ok(existsSync(join(root, 'www', map.image)), `Missing map artwork: ${map.image}`);
  for (const a of map.assignments) {
    assert.ok(a.region.every(Number.isFinite) && a.ui.every(Number.isFinite));
    assert.ok(a.region[2] > a.region[0] && a.region[3] > a.region[1], 'Projection has a nonzero extent');
  }
}
function element(dataset = {}) {
  const classes = new Set();
  return {dataset, textContent: '', innerHTML: '', value: '', checked: true, listeners: {},
    clientWidth: 1000, clientHeight: 668, style: {setProperty() {}},
    classList: {add(x) {classes.add(x);}, remove(x) {classes.delete(x);}, toggle(x, yes) {yes ? classes.add(x) : classes.delete(x);}, contains(x) {return classes.has(x);}},
    addEventListener(name, fn) {this.listeners[name] = fn;}, setAttribute() {},
    querySelectorAll() {return [];}, getBoundingClientRect() {return {left: 0, top: 0, width: 1000, height: 668};}};
}
const nodes = new Map();
const get = id => {if (!nodes.has(id)) nodes.set(id, element()); return nodes.get(id);};
get('lm-boot').textContent = JSON.stringify({base: '/', lang: 'en', realm: 7, refresh: 20,
  labels: {world: 'World', level: 'Level', bot: 'Bot', empty: 'No match', nobody: 'Nobody', error: 'Feed unavailable', updated: 'Updated'}});
const factions = ['all', 'alliance', 'horde'].map(faction => element({faction}));
const document = {hidden: false, listeners: {}, getElementById: get,
  querySelectorAll: query => query === '[data-faction]' ? factions : [],
  addEventListener(name, fn) {this.listeners[name] = fn;}};
const assignment = {map: 0, area: 12, region: [-100, -100, 100, 100], ui: [0, 0, 1, 1]};
const mapData = {maps: [{id: 947, parent: 0, type: 1, name: {en: 'Azeroth'}, image: 'world.webp', assignments: [assignment]}]};
const base = {map: 0, zone: 12, x: 0, y: 0, level: 60, raceName: 'Human', className: 'Warrior', class: 1, icon: 'human.png'};
const players = [{...base, guid: 1, name: '<script>Hero</script>', faction: 'alliance', bot: false},
  {...base, guid: 2, name: 'Bot', faction: 'horde', bot: true},
  {...base, guid: 3, name: 'Rogue', faction: 'horde', bot: false}];
let fail = false;
const requests = [], location = {hash: '', pathname: '/map', search: ''};
const delayed = new Map();
runInNewContext(readFileSync(join(root, 'www/assets/js/pages/live-map.js'), 'utf8'), {
  document, location, history: {replaceState(_s, _t, url) {location.hash = url.slice(url.indexOf('#'));}},
  ResizeObserver: class {observe() {}}, setTimeout() {return 1;}, clearTimeout() {},
  fetch: async url => {requests.push(url); if (delayed.has(url)) return delayed.get(url); return {ok: !fail, status: 503,
    json: async () => url.includes('world-map.json') ? mapData : {players, updated: 1}};},
});
const settled = async () => {for (let i = 0; i < 8; i++) await new Promise(resolve => setImmediate(resolve));};
(async () => {
  await settled();
  assert.equal(get('lm-frame').dataset.here, '3');
  assert.match(get('lm-markers').innerHTML, /left:50%;top:50%/);
  assert.ok(!get('lm-roster').innerHTML.includes('<script>'));
  assert.match(get('lm-roster').innerHTML, /&lt;script&gt;/);
  assert.equal(location.hash, '#map=947');
  factions[1].listeners.click();
  assert.equal(get('lm-frame').dataset.here, '1');
  factions[0].listeners.click();
  get('lm-bots').listeners.change({target: {checked: false}});
  assert.equal(get('lm-frame').dataset.here, '2');
  get('lm-search').listeners.input({target: {value: 'rogue'}});
  assert.equal(get('lm-frame').dataset.here, '1');
  get('lm-search').listeners.input({target: {value: 'missing'}});
  assert.match(get('lm-roster').innerHTML, /No match/);
  get('lm-realm').listeners.change({target: {value: '8'}});
  await settled();
  assert.ok(requests.some(url => url.includes('map_api?realm=8')));
  get('lm-search').listeners.input({target: {value: ''}});
  let resolveOld, resolveNew;
  delayed.set('/map_api?realm=9', new Promise(resolve => {resolveOld = resolve;}));
  delayed.set('/map_api?realm=10', new Promise(resolve => {resolveNew = resolve;}));
  get('lm-realm').listeners.change({target: {value: '9'}});
  assert.equal(get('lm-frame').dataset.here, '0', 'Previous realm positions clear immediately');
  get('lm-realm').listeners.change({target: {value: '10'}});
  resolveNew({ok: true, json: async () => ({players: [{...base, guid: 10, name: 'Latest realm', faction: 'alliance', bot: false}], updated: 2})});
  await settled();
  resolveOld({ok: true, json: async () => ({players: [{...base, guid: 9, name: 'Stale realm', faction: 'horde', bot: false}], updated: 1})});
  await settled();
  assert.match(get('lm-roster').innerHTML, /Latest realm/);
  assert.ok(!get('lm-roster').innerHTML.includes('Stale realm'), 'A slow previous realm response cannot replace the selected realm');
  delayed.clear();
  fail = true;
  document.listeners.visibilitychange();
  await settled();
  assert.equal(get('lm-updated').textContent, 'Feed unavailable');
  assert.ok(get('lm-frame').classList.contains('is-offline'));
  console.log(`Live map projection, filters, escaping, realm switching and failure handling passed; ${catalogue.maps.filter(m => m.image).length} map images verified.`);
})().catch(error => {console.error(error); process.exitCode = 1;});
