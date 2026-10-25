(() => {
  'use strict';
  const boot = JSON.parse(document.getElementById('lm-boot').textContent);
  const L = boot.labels, lang = boot.lang;
  const $ = id => document.getElementById(id);
  const frame = $('lm-frame'), viewport = $('lm-viewport'), canvas = $('lm-canvas'), image = $('lm-image');
  const zonesLayer = $('lm-zones'), markersLayer = $('lm-markers'), hover = $('lm-hover');
  const CLASS_COLORS = {1:'#c69b6d',2:'#f48cba',3:'#aad372',4:'#fff468',5:'#ffffff',6:'#c41e3b',7:'#2359ff',8:'#68ccef',9:'#9382c9',10:'#00ff98',11:'#ff7c0a',12:'#a330c9',13:'#33937f'};
  const WORLD = 947;
  let maps = new Map(), current = WORLD, players = [], realm = boot.realm, timer = 0, focusGuid = 0, feedGeneration = 0;
  const filters = {faction: 'all', bots: true, search: ''};
  const view = {scale: 1, x: 0, y: 0};

  const escapeHtml = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const nameOf = map => map ? (map.name[lang] || map.name.en) : '';

  /* ---------- projection: world (x north, y west) -> normalised map uv ---------- */
  function project(map, worldMap, x, y, loose) {
    if (!map) return null;
    for (const a of map.assignments) {
      if (a.map !== worldMap) continue;
      const [minX, minY, maxX, maxY] = a.region;
      if (!loose && (x < minX || x > maxX || y < minY || y > maxY)) continue;
      return {u: a.ui[0] + (maxY - y) / (maxY - minY) * (a.ui[2] - a.ui[0]),
              v: a.ui[1] + (maxX - x) / (maxX - minX) * (a.ui[3] - a.ui[1])};
    }
    return null;
  }
  const regionArea = a => (a.region[2] - a.region[0]) * (a.region[3] - a.region[1]);

  /** The most detailed map with artwork that shows this player. */
  function homeMap(player) {
    let best = null, bestScore = Infinity;
    for (const map of maps.values()) {
      if (!map.image) continue;
      for (const a of map.assignments) {
        if (a.map !== player.map || !project({assignments: [a]}, player.map, player.x, player.y)) continue;
        const score = (a.area === player.zone ? 0 : 1) * 1e12 + regionArea(a);
        if (score < bestScore) { bestScore = score; best = map; }
      }
    }
    return best;
  }
  function zoneName(player) {
    for (const map of maps.values()) if (map.assignments.some(a => a.area && a.area === player.zone && a.map === player.map)) return nameOf(map);
    const home = homeMap(player);
    return home && home.id !== WORLD ? nameOf(home) : (player.map > 1 ? L.instance : L.unknownZone);
  }

  /* ---------- view transform (zoom / pan) ---------- */
  function clampView() {
    const w = viewport.clientWidth, h = viewport.clientHeight;
    view.scale = Math.min(8, Math.max(1, view.scale));
    view.x = Math.min(0, Math.max(w - w * view.scale, view.x));
    view.y = Math.min(0, Math.max(h - h * view.scale, view.y));
  }
  function applyView(animate) {
    clampView();
    canvas.classList.toggle('is-animated', !!animate);
    canvas.style.transform = `translate(${view.x}px,${view.y}px) scale(${view.scale})`;
    canvas.style.setProperty('--inv', String(1 / view.scale));
    frame.classList.toggle('is-zoomed', view.scale > 1.01);
  }
  function zoomAt(factor, cx, cy, animate) {
    const next = Math.min(8, Math.max(1, view.scale * factor)), ratio = next / view.scale;
    view.x = cx - (cx - view.x) * ratio; view.y = cy - (cy - view.y) * ratio; view.scale = next;
    applyView(animate);
  }
  function resetView(animate) { view.scale = 1; view.x = 0; view.y = 0; applyView(animate); }
  function centerOn(u, v, scale) {
    const w = viewport.clientWidth, h = viewport.clientHeight;
    view.scale = scale; view.x = w / 2 - u * w * scale; view.y = h / 2 - v * h * scale; applyView(true);
  }

  let hintTimer = 0;
  viewport.addEventListener('wheel', event => {
    // Plain wheel keeps scrolling the page; Ctrl/⌘ + wheel zooms the map.
    if (!event.ctrlKey && !event.metaKey) {
      frame.classList.add('show-hint'); clearTimeout(hintTimer);
      hintTimer = setTimeout(() => frame.classList.remove('show-hint'), 1400);
      return;
    }
    event.preventDefault();
    const r = viewport.getBoundingClientRect();
    zoomAt(event.deltaY < 0 ? 1.25 : 0.8, event.clientX - r.left, event.clientY - r.top, false);
  }, {passive: false});
  let drag = null;
  const pointers = new Map();
  viewport.addEventListener('pointerdown', event => {
    if (event.target.closest('.lm-marker')) return;
    pointers.set(event.pointerId, {x: event.clientX, y: event.clientY});
    drag = {x: event.clientX, y: event.clientY, vx: view.x, vy: view.y, moved: false, pinch: pointers.size === 2 ? pinchDistance() : 0, scale: view.scale};
    viewport.setPointerCapture(event.pointerId);
  });
  function pinchDistance() { const [a, b] = [...pointers.values()]; return Math.hypot(a.x - b.x, a.y - b.y); }
  viewport.addEventListener('pointermove', event => {
    if (pointers.has(event.pointerId)) pointers.set(event.pointerId, {x: event.clientX, y: event.clientY});
    if (drag && pointers.size === 2 && drag.pinch) {
      const r = viewport.getBoundingClientRect(), [a, b] = [...pointers.values()];
      zoomAt(drag.scale * pinchDistance() / drag.pinch / view.scale, (a.x + b.x) / 2 - r.left, (a.y + b.y) / 2 - r.top, false);
      drag.moved = true; return;
    }
    if (drag) {
      const dx = event.clientX - drag.x, dy = event.clientY - drag.y;
      if (Math.abs(dx) + Math.abs(dy) > 4) drag.moved = true;
      if (drag.moved) { view.x = drag.vx + dx; view.y = drag.vy + dy; applyView(false); frame.classList.add('is-dragging'); hideHover(); return; }
    }
    hoverZone(event);
  });
  const endDrag = event => {
    pointers.delete(event.pointerId);
    if (!drag) return;
    const clicked = !drag.moved && event.type === 'pointerup';
    drag = pointers.size ? drag : null; frame.classList.remove('is-dragging');
    if (clicked) { const zone = zoneAt(event); if (zone) navigate(zone.id); }
  };
  viewport.addEventListener('pointerup', endDrag);
  viewport.addEventListener('pointercancel', endDrag);
  viewport.addEventListener('pointerleave', () => { if (!drag) hideHover(); });
  frame.querySelectorAll('[data-zoom]').forEach(button => button.addEventListener('click', () => {
    const step = Number(button.dataset.zoom);
    if (!step) return resetView(true);
    zoomAt(step > 0 ? 1.6 : 0.625, viewport.clientWidth / 2, viewport.clientHeight / 2, true);
  }));
  new ResizeObserver(() => applyView(false)).observe(viewport);

  /* ---------- child zones of the current map (hover + click to open) ---------- */
  let zoneRects = [];
  function buildZones() {
    const map = maps.get(current); zoneRects = [];
    for (const child of maps.values()) {
      if (child.parent !== current || !child.image || child.type === 6) continue;
      for (const a of child.assignments) {
        // A continent's region is larger than its painted area on the world map: project loosely, then clip.
        const p1 = project(map, a.map, a.region[2], a.region[3], true), p2 = project(map, a.map, a.region[0], a.region[1], true);
        if (!p1 || !p2) continue;
        const box = (map.assignments.find(pa => pa.map === a.map) || {ui: [0, 0, 1, 1]}).ui;
        const clipU = n => Math.min(box[2], Math.max(box[0], n)), clipV = n => Math.min(box[3], Math.max(box[1], n));
        const r = {id: child.id, name: nameOf(child), u1: clipU(p1.u), v1: clipV(p1.v), u2: clipU(p2.u), v2: clipV(p2.v)};
        if (r.u2 <= r.u1 || r.v2 <= r.v1) continue;
        r.area = (r.u2 - r.u1) * (r.v2 - r.v1);
        zoneRects.push(r);
        break;
      }
    }
    zonesLayer.innerHTML = '';
  }
  function eventUv(event) {
    const r = canvas.getBoundingClientRect();
    return {u: (event.clientX - r.left) / r.width, v: (event.clientY - r.top) / r.height};
  }
  function zoneAt(event) {
    const {u, v} = eventUv(event);
    return zoneRects.filter(z => u >= z.u1 && u <= z.u2 && v >= z.v1 && v <= z.v2).sort((a, b) => a.area - b.area)[0] || null;
  }
  function hoverZone(event) {
    if (event.target.closest('.lm-marker')) return;
    const zone = zoneAt(event);
    viewport.style.cursor = zone ? 'pointer' : '';
    if (!zone) { hideHover(); zonesLayer.innerHTML = ''; return; }
    const count = players.filter(p => visible(p) && project(maps.get(zone.id), p.map, p.x, p.y)).length;
    zonesLayer.innerHTML = `<span class="lm-zone-box" style="left:${zone.u1 * 100}%;top:${zone.v1 * 100}%;width:${(zone.u2 - zone.u1) * 100}%;height:${(zone.v2 - zone.v1) * 100}%"></span>`;
    showHover(event, `<strong>${escapeHtml(zone.name)}</strong><small>${count} ${escapeHtml(L.players)} · ${escapeHtml(L.enter)}</small>`);
  }
  function showHover(event, html) {
    const r = frame.getBoundingClientRect();
    hover.innerHTML = html; hover.hidden = false;
    const x = Math.min(event.clientX - r.left + 16, r.width - hover.offsetWidth - 8);
    const y = Math.min(event.clientY - r.top + 16, r.height - hover.offsetHeight - 8);
    hover.style.transform = `translate(${Math.max(8, x)}px,${Math.max(8, y)}px)`;
  }
  function hideHover() { hover.hidden = true; }

  /* ---------- rendering ---------- */
  const visible = p => (filters.faction === 'all' || p.faction === filters.faction) && (filters.bots || !p.bot)
    && (!filters.search || p.name.toLowerCase().includes(filters.search));

  function renderCrumbs() {
    const chain = []; let map = maps.get(current);
    while (map) { chain.unshift(map); map = maps.get(map.parent); }
    $('lm-crumbs').innerHTML = chain.map((m, i) => i === chain.length - 1
      ? `<span aria-current="page">${escapeHtml(nameOf(m))}</span>`
      : `<button type="button" data-map="${m.id}">${escapeHtml(nameOf(m))}</button>`).join('<i aria-hidden="true">›</i>');
  }
  function renderMarkers() {
    const map = maps.get(current), html = [];
    let here = 0;
    for (const p of players) {
      if (!visible(p)) continue;
      const point = project(map, p.map, p.x, p.y);
      if (!point || point.u < 0 || point.u > 1 || point.v < 0 || point.v > 1) continue;
      here++;
      const cls = `lm-marker is-${p.bot ? 'bot' : p.faction}${p.guid === focusGuid ? ' is-focus' : ''}`;
      html.push(`<button type="button" class="${cls}" data-guid="${p.guid}" style="left:${point.u * 100}%;top:${point.v * 100}%" aria-label="${escapeHtml(p.name)}, ${escapeHtml(L.level)} ${p.level}"><span></span></button>`);
    }
    markersLayer.innerHTML = html.join('');
    frame.dataset.here = String(here);
  }
  function renderRoster() {
    const list = players.filter(visible), roster = $('lm-roster');
    if (!players.length) { roster.innerHTML = `<li class="lm-empty">${escapeHtml(L.nobody)}</li>`; }
    else if (!list.length) { roster.innerHTML = `<li class="lm-empty">${escapeHtml(L.empty)}</li>`; }
    else roster.innerHTML = list.slice(0, 300).map(p => `
      <li><button type="button" class="lm-player${p.guid === focusGuid ? ' is-focus' : ''}" data-guid="${p.guid}">
        <img src="${escapeHtml(boot.base)}img/accountimg/race/${escapeHtml(p.icon)}" alt="" loading="lazy" width="36" height="36">
        <span class="lm-player-copy"><strong style="color:${CLASS_COLORS[p.class] || '#ddd'}">${escapeHtml(p.name)}</strong>
        <small>${escapeHtml(L.level)} ${p.level} · ${escapeHtml(p.raceName)} ${escapeHtml(p.className)}</small>
        <small class="lm-player-zone">${escapeHtml(zoneName(p))}</small></span>
        <span class="lm-faction is-${p.bot ? 'bot' : p.faction}">${p.bot ? escapeHtml(L.bot) : ''}</span>
      </button></li>`).join('');
    // Zones with the most adventurers (filtered).
    const counts = new Map();
    for (const p of list) { const home = homeMap(p); if (home && home.type !== 1 && home.type !== 2) counts.set(home.id, (counts.get(home.id) || 0) + 1); }
    $('lm-zone-list').innerHTML = [...counts.entries()].sort((a, b) => b[1] - a[1]).slice(0, 8)
      .map(([id, n]) => `<button type="button" data-map="${id}"${id === current ? ' aria-current="true"' : ''}>${escapeHtml(nameOf(maps.get(id)))} <b>${n}</b></button>`).join('');
  }
  function renderStats() {
    const humans = players.filter(p => filters.bots || !p.bot);
    $('lm-count-all').textContent = humans.length;
    $('lm-count-alliance').textContent = humans.filter(p => p.faction === 'alliance').length;
    $('lm-count-horde').textContent = humans.filter(p => p.faction === 'horde').length;
  }
  function render() { renderMarkers(); renderRoster(); renderStats(); }

  function navigate(id, keepView) {
    const map = maps.get(id);
    if (!map || !map.image) return;
    current = id; hideHover();
    // Absolute URL: the page has a <base href>, so a bare "#map=" would point at the site root.
    if (location.hash !== '#map=' + id) history.replaceState(null, '', location.pathname + location.search + '#map=' + id);
    frame.classList.add('is-loading');
    image.onload = () => frame.classList.remove('is-loading');
    image.src = boot.base + map.image;
    image.alt = nameOf(map);
    if (!keepView) resetView(false);
    buildZones(); renderCrumbs(); render();
  }
  function focusPlayer(guid) {
    const player = players.find(p => p.guid === guid);
    if (!player) return;
    focusGuid = guid;
    const home = homeMap(player);
    if (home && home.id !== current) navigate(home.id);
    const point = project(maps.get(current), player.map, player.x, player.y);
    render();
    if (point) centerOn(point.u, point.v, Math.max(view.scale, 2.2));
  }

  /* ---------- events ---------- */
  document.addEventListener('click', event => {
    const target = event.target.closest('[data-map]');
    if (target) { navigate(Number(target.dataset.map)); return; }
    const row = event.target.closest('.lm-player');
    if (row) { focusPlayer(Number(row.dataset.guid)); frame.scrollIntoView({behavior: 'smooth', block: 'nearest'}); return; }
    const marker = event.target.closest('.lm-marker');
    if (marker) {
      const player = players.find(p => p.guid === Number(marker.dataset.guid));
      if (player) location.href = `${boot.base}character?guid=${player.guid}&realm=${realm}`;
    }
  });
  markersLayer.addEventListener('pointerover', event => {
    const marker = event.target.closest('.lm-marker');
    if (!marker) return;
    const p = players.find(x => x.guid === Number(marker.dataset.guid));
    if (!p) return;
    zonesLayer.innerHTML = ''; viewport.style.cursor = 'pointer';
    showHover(event, `<span class="lm-hover-player"><img src="${escapeHtml(boot.base)}img/accountimg/race/${escapeHtml(p.icon)}" alt="" width="34" height="34"><span><strong style="color:${CLASS_COLORS[p.class] || '#ddd'}">${escapeHtml(p.name)}</strong><small>${escapeHtml(L.level)} ${p.level} · ${escapeHtml(p.raceName)} ${escapeHtml(p.className)}</small><small>${escapeHtml(zoneName(p))}${p.bot ? ' · Bot' : ''}</small></span></span><em>${escapeHtml(L.armory)} ↗</em>`);
  });
  markersLayer.addEventListener('pointerout', event => { if (event.target.closest('.lm-marker')) hideHover(); });
  document.querySelectorAll('[data-faction]').forEach(button => button.addEventListener('click', () => {
    filters.faction = button.dataset.faction;
    document.querySelectorAll('[data-faction]').forEach(b => b.setAttribute('aria-pressed', String(b === button)));
    render();
  }));
  $('lm-bots').addEventListener('change', event => { filters.bots = event.target.checked; render(); });
  $('lm-search').addEventListener('input', event => { filters.search = event.target.value.trim().toLowerCase(); render(); });
  $('lm-realm')?.addEventListener('change', event => { realm = Number(event.target.value); focusGuid = 0; players = []; render(); refresh(); });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && current !== WORLD) { const parent = maps.get(maps.get(current).parent); if (parent?.image) navigate(parent.id); }
  });

  /* ---------- live feed ---------- */
  async function refresh() {
    const generation = ++feedGeneration;
    clearTimeout(timer);
    try {
      const response = await fetch(`${boot.base}map_api?realm=${realm}`, {headers: {Accept: 'application/json'}, cache: 'no-store'});
      if (!response.ok) throw new Error(String(response.status));
      const data = await response.json();
      if (generation !== feedGeneration) return;
      players = data.players || [];
      const time = new Date(data.updated * 1000).toLocaleTimeString(lang === 'fr' ? 'fr-FR' : 'en-GB', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
      $('lm-updated').textContent = `${L.updated} ${time}`;
      frame.classList.remove('is-offline');
      render();
    } catch (error) {
      if (generation !== feedGeneration) return;
      $('lm-updated').textContent = L.error;
      frame.classList.add('is-offline');
    }
    if (!document.hidden) timer = setTimeout(refresh, boot.refresh * 1000);
  }
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); else clearTimeout(timer); });

  fetch(`${boot.base}assets/data/world-map.json?v=1`).then(r => r.json()).then(data => {
    for (const map of data.maps) maps.set(map.id, map);
    const fromHash = Number((location.hash.match(/map=(\d+)/) || [])[1]);
    navigate(maps.get(fromHash)?.image ? fromHash : WORLD);
    refresh();
  }).catch(() => {
    $('lm-updated').textContent = L.error;
    frame.classList.add('is-offline');
  });
})();
