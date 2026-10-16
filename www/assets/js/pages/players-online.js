(() => {
 'use strict';
 const search=document.getElementById('rosterSearch'), faction=document.getElementById('rosterFaction'), cls=document.getElementById('rosterClass');
 if(!search)return;
 const cards=[...document.querySelectorAll('[data-player-search]')], count=document.getElementById('rosterCount'), empty=document.getElementById('rosterNoResults');
 const filters=[...document.querySelectorAll('[data-presence-filter]')];
 const normalize=value=>value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase();
 let kind='all';
 const update=()=>{
  const query=normalize(search.value.trim());let visible=0;
  cards.forEach(card=>{const show=(kind==='all'||card.dataset.presenceType===kind)&&(faction.value==='all'||card.dataset.playerFaction===faction.value)&&(cls.value==='all'||card.dataset.playerClass===cls.value)&&(!query||normalize(card.dataset.playerSearch).includes(query));card.hidden=!show;if(show)visible++;});
  count.textContent=String(visible);empty.hidden=visible!==0||cards.length===0;
 };
 search.addEventListener('input',update);faction.addEventListener('change',update);cls.addEventListener('change',update);
 filters.forEach(button=>button.addEventListener('click',()=>{kind=button.dataset.presenceFilter;filters.forEach(other=>other.setAttribute('aria-pressed',String(other===button)));update();}));
 document.getElementById('rosterClear').onclick=()=>{search.value='';faction.value=cls.value='all';filters.find(x=>x.dataset.presenceFilter==='all').click();search.focus();};
 document.querySelectorAll('.od-portrait img').forEach(image=>image.addEventListener('error',()=>{image.hidden=true;},{once:true}));
 document.getElementById('rosterRefresh').addEventListener('click',event=>{const link=event.currentTarget;if(link.getAttribute('aria-busy')==='true'){event.preventDefault();return;}link.setAttribute('aria-busy','true');});
 update();
})();
