const {readFileSync}=require('node:fs');
const {runInNewContext}=require('node:vm');
const {join}=require('node:path');
const assert=require('node:assert/strict');
// These files must be shipped together; the live page requires the presence helper.
for (const file of ['pages/players_online.php','includes/online_presence.php','includes/online_roster_view.php','assets/css/players-online.css','assets/js/pages/players-online.js']) {
    assert.ok(require('node:fs').existsSync(join(__dirname,'../www',file)), `Missing Players Online release dependency: ${file}`);
}
function element(extra={}){return {value:'',hidden:false,textContent:'',listeners:{},attrs:{},addEventListener(name,fn){this.listeners[name]=fn},setAttribute(k,v){this.attrs[k]=v},getAttribute(k){return this.attrs[k]},focus(){},...extra}}
const fields=Object.fromEntries(['rosterSearch','rosterFaction','rosterClass','rosterCount','rosterNoResults','rosterClear','rosterRefresh'].map(x=>[x,element()]));
fields.rosterFaction.value=fields.rosterClass.value='all';
const cards=[element({dataset:{presenceType:'player',playerFaction:'alliance',playerClass:'1',playerSearch:'Éléonore Guerrier High Order Alliance'}}),element({dataset:{presenceType:'bot',playerFaction:'horde',playerClass:'8',playerSearch:'Bot Mage Orc Horde'}}),element({dataset:{presenceType:'player',playerFaction:'alliance',playerClass:'8',playerSearch:'Test Mage Humain Alliance'}})];
const filters=['all','player','bot'].map(kind=>element({dataset:{presenceFilter:kind},click(){this.listeners.click()}}));
const document={getElementById:id=>fields[id],querySelectorAll:query=>query==='[data-player-search]'?cards:query==='[data-presence-filter]'?filters:[]};
runInNewContext(readFileSync(join(__dirname,'../www/assets/js/pages/players-online.js'),'utf8'),{document});
let checks=0;const check=(value,expected,label)=>{assert.equal(value,expected,label);console.log('PASS '+label);checks++};
check(fields.rosterCount.textContent,'3','All characters visible initially');
filters[2].click();check(fields.rosterCount.textContent,'1','Bot filter is character-specific');
check(cards[1].hidden,false,'Actual bot stays visible');check(cards[0].hidden,true,'Human is hidden by bot filter');
filters[0].click();fields.rosterSearch.value='eleonore';fields.rosterSearch.listeners.input();check(fields.rosterCount.textContent,'1','Accent-insensitive search finds the character');
fields.rosterClass.value='8';fields.rosterClass.listeners.change();check(fields.rosterNoResults.hidden,false,'Conflicting filters show a meaningful empty result');
fields.rosterClear.onclick();check(fields.rosterCount.textContent,'3','Clear filters restores all rows');check(fields.rosterNoResults.hidden,true,'Clear filters removes empty-result message');
fields.rosterFaction.value='alliance';fields.rosterFaction.listeners.change();check(fields.rosterCount.textContent,'2','Faction filter retains both Alliance players');
fields.rosterClass.value='8';fields.rosterClass.listeners.change();check(fields.rosterCount.textContent,'1','Class and faction filters combine correctly');
console.log(`${checks} client-side checks passed. No server state was changed.`);
