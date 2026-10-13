(() => {
  'use strict';
  const boot=window.ForeverPageStudio, status=document.getElementById('editorStatus');
  if(!window.grapesjs){status.textContent='The visual editor could not load. Reload the page.';return;}
  let revision=Number(boot.page?.revision||0), dirty=false, loading=true;
  const baseCss='body{margin:0;background:#0b1018;color:#dbe1eb;font-family:Arial,sans-serif}section{padding:32px}h1,h2{font-family:Georgia,serif;color:#edcf79}a{color:#eccd77}img{max-width:100%}.forever-columns{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:24px}.forever-box{padding:24px;border:1px solid #65542a;background:#0b1018}';
  const editor=grapesjs.init({container:'#pageEditor',height:'760px',storageManager:false,telemetry:false,noticeOnUnload:false,canvas:{styles:['/assets/css/tailwind.css','/assets/css/forever-theme.css','/node_modules/@fortawesome/fontawesome-free/css/all.min.css'],scripts:[]},assetManager:{upload:false},deviceManager:{devices:[{name:'Desktop',width:''},{name:'Tablet',width:'768px'},{name:'Mobile',width:'390px'}]},styleManager:{sectors:[{name:'Layout',open:true,buildProps:['display','width','max-width','min-height','margin','padding','flex-direction','justify-content','align-items','gap']},{name:'Typography',open:false,buildProps:['font-family','font-size','font-weight','letter-spacing','color','line-height','text-align']},{name:'Appearance',open:false,buildProps:['background-color','background-image','background-size','background-position','border','border-radius','box-shadow','opacity']} ]}});
  const blocks=[
    ['hero','Hero','<section style="text-align:center;padding:70px 24px"><p>{{site_title}}</p><h1 style="font-size:48px">Your adventure starts here</h1><p>Write your introduction here.</p><a href="/register">Create an account</a></section>'],
    ['section','Section','<section><h2>Section title</h2><p>Write your content here.</p></section>'],
    ['columns','Two columns','<section class="forever-columns"><div class="forever-box"><h2>First column</h2><p>Your text.</p></div><div class="forever-box"><h2>Second column</h2><p>Your text.</p></div></section>'],
    ['text','Text','<p data-gjs-type="text">Double-click to edit this text.</p>'],['heading','Heading','<h2>Heading</h2>'],
    ['image','Image',{type:'image',src:'/img/forever/wallpapers/brand-logo.jpg'}],
    ['link','Button','<a href="/register" style="display:inline-block;background:#d4af50;color:#111;padding:14px 24px;text-decoration:none">Create an account</a>'],
    ['list','Instructions','<ol><li>Download the launcher.</li><li>Create your account.</li><li>Choose your server and play.</li></ol>'],
    ['table','Table','<table style="width:100%;border-collapse:collapse"><tbody><tr><th>Requirement</th><th>Details</th></tr><tr><td>Client version</td><td>Your version</td></tr><tr><td>Connection</td><td>{{realmlist}}</td></tr></tbody></table>'],
    ['quote','Notice','<aside class="forever-box"><strong>Important</strong><p>Add your server-specific information here.</p></aside>'],
    ['video','Video file','<video controls style="width:100%" src="https://example.com/your-video.mp4"></video>'],
    ['divider','Divider','<hr style="border:0;border-top:1px solid #65542a;margin:28px 0">'],
    ['realmlist','Realmlist','<div class="forever-box"><h2>Connect to {{site_title}}</h2><p>Your realmlist / portal:</p><code>{{realmlist}}</code></div>'],
    ['launcher','Launcher download','<div class="forever-box"><h2>Launcher</h2><p>Install the launcher to join us.</p><a href="{{launcher_url}}">Download the launcher</a></div>'],
    ['discord','Discord','<div class="forever-box"><h2>Our community</h2><a href="{{discord_url}}">Join Discord</a></div>']
  ];
  blocks.forEach(([id,label,content])=>editor.BlockManager.add(id,{label,content,category:'Page blocks'}));
  editor.on('update',()=>{if(loading)return;dirty=true;status.textContent='Unsaved changes';});
  async function importInstalled(automatic=false){
    if(!automatic&&dirty&&!confirm('Replace unsaved canvas content with the installed page layout?'))return;
    loading=true;status.textContent='Loading the installed How to join us page…';
    try{
      const url=new URL('/how_to_play',location.origin);url.searchParams.set('lang',boot.locale);url.searchParams.set('original','1');
      const response=await fetch(url,{credentials:'same-origin'});if(!response.ok)throw Error('Installed page could not load.');
      const doc=new DOMParser().parseFromString(await response.text(),'text/html'),main=doc.querySelector('main');if(!main)throw Error('This page has no editable content region.');
      main.querySelectorAll('script,iframe,form,input,button').forEach(e=>e.remove());
      main.querySelectorAll('*').forEach(el=>{for(const attr of [...el.attributes])if(attr.name.startsWith('on'))el.removeAttribute(attr.name);});
      const realmlist=main.querySelector('#realmlist-text');if(realmlist)realmlist.textContent='{{realmlist}}';
      if(boot.launcherUrl)main.querySelectorAll('a[href]').forEach(a=>{if(a.getAttribute('href')===boot.launcherUrl)a.setAttribute('href','{{launcher_url}}');});
      editor.setComponents(main.innerHTML);editor.getWrapper().addClass('how_to_play');
      editor.setStyle(baseCss+'\n'+[...doc.querySelectorAll('style')].map(e=>e.textContent).join('\n')+'\n.fade-up{opacity:1!important;animation:none!important}');
      dirty=!automatic;status.textContent='Installed layout loaded. Double-click any text to edit; select images or links to change their properties.';
    }catch(error){status.textContent=error.message;status.classList.add('error');}
    finally{await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));loading=false;dirty=!automatic;}
  }
  editor.on('load',async()=>{
    if(boot.page?.project_json){try{const project=JSON.parse(boot.page.project_json);if(project.pages?.length)editor.loadProjectData(project);else{editor.setComponents(boot.page.html||'');editor.setStyle(boot.page.css||baseCss);}}catch{editor.setComponents(boot.page.html||'');editor.setStyle(boot.page.css||baseCss);}dirty=false;status.textContent='Saved layout loaded.';}
    else await importInstalled(true);
    editor.Panels.getButton('views','open-blocks')?.set('active',true);
    await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));loading=false;dirty=false;
  });
  async function save(action,restoreId=null){
    status.textContent='Saving…';status.classList.remove('error');document.querySelectorAll('.studio-toolbar button').forEach(b=>b.disabled=true);
    try{
      const url=new URL(location.href);url.searchParams.set('api','page');
      const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:boot.csrf,action,restore_id:restoreId,revision,title:document.getElementById('pageTitle').value,html:editor.getHtml(),css:editor.getCss(),project:editor.getProjectData()})});
      const result=await response.json();if(!response.ok||!result.ok)throw Error(result.error||'Save failed.');
      revision=result.revision;dirty=false;document.getElementById('pageState').textContent=result.enabled?'Published':'Original page';
      if(action==='restore'){editor.setComponents(result.html);editor.setStyle(result.css);if(result.project?.pages?.length)editor.loadProjectData(result.project);dirty=false;}
      status.textContent=action==='publish'?'Published successfully':action==='disable'?'Original page restored':action==='restore'?'Revision restored to draft':'Draft saved';
    }catch(error){status.textContent=error.message;status.classList.add('error');}
    finally{document.querySelectorAll('.studio-toolbar button').forEach(b=>b.disabled=false);}
  }
  document.getElementById('saveDraft').onclick=()=>save('draft');document.getElementById('publishPage').onclick=()=>save('publish');document.getElementById('originalPage').onclick=()=>save('disable');
  document.getElementById('importOriginal').onclick=()=>importInstalled();document.getElementById('pageRevision').onchange=e=>{if(e.target.value)save('restore',e.target.value);};
  document.getElementById('pageTitle').oninput=()=>{dirty=true;status.textContent='Unsaved changes';};
  window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
})();
