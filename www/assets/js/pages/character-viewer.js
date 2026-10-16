(() => {
  'use strict';
  const payload = document.getElementById('character-viewer-data');
  if (!payload) return;
  const cfg = JSON.parse(payload.textContent);
  const frame = document.getElementById('character-model-frame');
  const stage = document.getElementById('character-model-3d');
  const status = document.getElementById('character-model-status');
  const statusText = document.getElementById('character-model-status-text');
  const expand = document.getElementById('character-model-expand');
  const pause = document.getElementById('character-model-pause');
  let model, timer, controller, generation=0, paused = false, expanded = false, oldOverflow = '', previousFocus;
  const retry=document.getElementById('character-model-retry');
  const assetNote=document.getElementById('character-model-asset-note');
  const initialParent = frame.parentNode;
  const nextSibling = frame.nextSibling;
  function error(message) {
    status.hidden = false; status.classList.add('is-error');
    statusText.textContent = message;
    frame.setAttribute('aria-busy', 'false');
    retry.disabled=false;
  }
  function resize() {
    if (!model?.renderer) return;
    const width = Math.max(1, stage.clientWidth), height = Math.max(1, stage.clientHeight);
    model.aspect = width / height;
    model.renderer.onResize(width, height, model.aspect);
  }
  function setExpanded(value) {
    expanded = value;
    if (value) {
      previousFocus = document.activeElement; oldOverflow = document.body.style.overflow;
      document.body.append(frame); document.body.style.overflow = 'hidden';
      frame.setAttribute('role', 'dialog'); frame.setAttribute('aria-modal', 'true');
      frame.setAttribute('aria-label', cfg.labels.inspect);
    } else {
      initialParent.insertBefore(frame, nextSibling); document.body.style.overflow = oldOverflow;
      frame.removeAttribute('role'); frame.removeAttribute('aria-modal');
      previousFocus?.focus();
    }
    frame.classList.toggle('is-expanded', value);
    expand.textContent = value ? cfg.labels.close : cfg.labels.expand;
    expand.setAttribute('aria-expanded', String(value));
    requestAnimationFrame(() => {resize(); if (value) expand.focus();});
  }
  expand.addEventListener('click', () => setExpanded(!expanded));
  document.addEventListener('keydown', event => {
    if (!expanded) return;
    if (event.key === 'Escape') {event.preventDefault(); setExpanded(false);}
    if (event.key === 'Tab') {
      const controls = [...frame.querySelectorAll('button:not([disabled]),[tabindex="0"]:not([hidden])')];
      const first = controls[0], last = controls[controls.length - 1];
      if (event.shiftKey && document.activeElement === first) {event.preventDefault(); last.focus();}
      else if (!event.shiftKey && document.activeElement === last) {event.preventDefault(); first.focus();}
    }
  });
  new ResizeObserver(resize).observe(stage);
  pause.onclick = () => {
    paused = !paused; model?.method('setAnimPaused', [paused]);
    pause.textContent = paused ? cfg.labels.play : cfg.labels.pause;
    pause.setAttribute('aria-pressed', String(paused));
  };
  document.getElementById('character-model-reset').onclick = () => {
    if (!model?.renderer || !cfg.camera) return;
    model.setZoom(1); model.setOffset(0, 0);
    model.renderer.azimuth = cfg.camera.azimuth;
    model.renderer.zenith = cfg.camera.zenith;
  };
  document.querySelectorAll('[data-character-zoom]').forEach(button => button.onclick = () => {
    if (!model?.renderer) return;
    const zoom = model.renderer.zoom;
    zoom.target = Math.max(-zoom.range[1], Math.min(-zoom.range[0], zoom.target + Number(button.dataset.characterZoom) * 1.6));
  });
  retry.onclick = start;
  async function start() {
    const current=++generation; controller?.abort(); const requestController=new AbortController();controller=requestController;
    clearInterval(timer); model?.destroy(); model = null;
    retry.disabled=true; cfg.camera=null; assetNote.hidden=true;
    paused=false;pause.textContent=cfg.labels.pause;pause.setAttribute('aria-pressed','false');
    stage.replaceChildren(); status.hidden = false; status.classList.remove('is-error');
    statusText.textContent = cfg.labels.loading; frame.setAttribute('aria-busy', 'true');
    if (!cfg.model || !window.jQuery || !window.ZamModelViewer) return error(cfg.labels.failed);
    try {
      const timeout=setTimeout(()=>requestController.abort(),20000);
      let response;
      try{response=await fetch(cfg.contentPath + 'meta/charactercustomization2/' + cfg.race + '_' + cfg.gender + '.json',{signal:controller.signal});}finally{clearTimeout(timeout);}
      if (!response.ok) throw new Error('Appearance metadata unavailable');
      const appearance = await response.json();
      if(current!==generation)return;
      // Start with all valid model defaults, then apply saved choices supported
      // by this metadata version. Partial/new options must not erase the skin.
      const options = appearance.Options.flatMap(option => {
        const saved = cfg.customizations.find(x => x.optionId === option.Id && option.Choices.some(c => c.Id === x.choiceId));
        const fallback = [...option.Choices].sort((a,b) => a.OrderIndex - b.OrderIndex)[0];
        return saved ? [saved] : fallback ? [{optionId:option.Id, choiceId:fallback.Id}] : [];
      });
      window.WH = window.WH || {}; window.WH.debug = window.WH.debug || (() => {});
      jQuery(document).off('ajaxError.foreverCharacter').on('ajaxError.foreverCharacter',(_event,xhr,settings)=>{
        if(xhr.status>=400&&String(settings.url).startsWith(cfg.contentPath+'meta/')){
          assetNote.textContent=cfg.labels.partialShort;assetNote.title=cfg.labels.partial;assetNote.setAttribute('aria-label',cfg.labels.partial);assetNote.hidden=false;
        }
      });
      model = new ZamModelViewer({type:ZamModelViewer.WOW, contentPath:cfg.contentPath,
        container:jQuery(stage), hd:true, aspect:stage.clientWidth / Math.max(1,stage.clientHeight), cls:cfg.class,
        charCustomization:{race:cfg.race, gender:cfg.gender, options, sheathMain:-1, sheathOff:-1},
        items:cfg.items, models:{type:ZamModelViewer.Wow.Types.CHARACTER,id:cfg.model}, mount:{type:8,id:0}});
      const began = Date.now();
      timer = setInterval(() => {
        if (model?.method('isLoaded')) {
          clearInterval(timer); status.hidden = true; frame.setAttribute('aria-busy', 'false');retry.disabled=false;
          cfg.camera = {azimuth:model.renderer.azimuth,zenith:model.renderer.zenith}; resize();
        } else if (Date.now() - began > 45000) {clearInterval(timer); error(cfg.labels.failed);}
      }, 200);
    } catch (e) {if(current!==generation)return;console.warn('ForeverCMS character viewer:', e.message); error(cfg.labels.failed);}
  }
  window.addEventListener('pagehide', () => {generation++;controller?.abort();clearInterval(timer);model?.destroy();model=null;window.jQuery?.(document).off('ajaxError.foreverCharacter');});
  window.addEventListener('pageshow', event => {if(event.persisted){if(expanded)setExpanded(false);start();}});
  start();
})();
