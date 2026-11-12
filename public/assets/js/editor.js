'use strict';
(() => {
  const $=id=>document.getElementById(id), frame=$('page-preview');
  let slug=new URLSearchParams(location.search).get('document')==='shared'?'shared':'home';
  let state,templates,media,selected,dirty=false,timer,previewSequence=0,busy=false;
  $('document').value=slug;
  const token=document.querySelector('input[name="csrf"]').value;
  const notice=message=>$('editor-notice').textContent=message;
  const status=message=>$('save-status').textContent=message;
  const active=()=>state.content.sections.find(s=>s.id===selected);
  function markDirty(){dirty=true;status('Unsaved changes');clearTimeout(timer);timer=setTimeout(preview,650);}
  async function request(body){
    const options=body?{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf:token,data:JSON.stringify(body)})}:{};
    const response=await fetch('/admin/content?document='+slug,{...options,credentials:'same-origin',redirect:'error'});
    if(!response.headers.get('content-type')?.includes('application/json'))throw Error('The editor is unavailable or your session expired. Reload and sign in again.');
    const result=await response.json();if(!response.ok)throw Error(result.error||'The request could not be completed. Reload and sign in if needed.');return result;
  }
  function history(){
    $('revision').replaceChildren();state.history.forEach(r=>{const o=new Option('#'+r.id+' · '+r.action+' · '+r.created_at+' UTC'+(Number(r.id)===state.published?' · published':''),r.id);$('revision').add(o);});
  }
  function sections(){
    $('section-list').replaceChildren();state.content.sections.forEach((s,index)=>{const b=document.createElement('button');b.type='button';b.className='section-choice'+(s.id===selected?' selected':'');b.textContent=(index+1)+'. '+templates[s.template].label;b.setAttribute('aria-pressed',s.id===selected?'true':'false');b.onclick=()=>select(s.id);$('section-list').append(b);});
    const i=state.content.sections.findIndex(s=>s.id===selected);
    $('up').disabled=slug==='shared'||i<=1;$('down').disabled=slug==='shared'||i===0||i===state.content.sections.length-1;
    $('remove').disabled=slug==='shared'||i===0;$('add').disabled=slug==='shared';$('layout').disabled=slug==='shared';
  }
  function select(id){if(!state.content.sections.some(s=>s.id===id))return;selected=id;sections();groups();}
  function groups(){
    const t=templates[active().template];$('field-title').textContent=t.label;$('field-group').replaceChildren();
    [...new Set(Object.values(t.fields).map(f=>f.group))].forEach(g=>$('field-group').add(new Option(g,g)));fields();
  }
  function fields(){
    $('fields').replaceChildren();const section=active(),t=templates[section.template];
    for(const [key,f] of Object.entries(t.fields)){
      if(f.group!==$('field-group').value)continue;
      const label=document.createElement('label');label.textContent=f.label;
      if(f.type==='image'){
        const image=media.find(m=>m.id===section.values[key]);const thumbnail=document.createElement('img');thumbnail.src=image.path;thumbnail.alt='Selected picture';thumbnail.className='field-picture';label.append(thumbnail);
        const button=document.createElement('button');button.type='button';button.textContent='Choose picture';button.setAttribute('aria-label','Choose picture');button.onclick=()=>pickMedia(key);label.append(button);
      }else{
        const input=document.createElement(f.type==='text'&&section.values[key].length>100?'textarea':'input');input.value=section.values[key];input.maxLength=f.max;input.dataset.field=key;
        if(input.tagName==='TEXTAREA')input.rows=5;
        input.addEventListener('input',()=>{section.values[key]=input.value;markDirty();});label.append(input);
      }
      $('fields').append(label);
    }
  }
  function pickMedia(key){
    const picker=$('media-picker');$('media-search').value='';
    const draw=()=>{
      $('media-grid').replaceChildren();const term=$('media-search').value.toLowerCase();
      media.filter(m=>m.label.toLowerCase().includes(term)).forEach(m=>{const b=document.createElement('button');b.type='button';const img=document.createElement('img');img.src=m.path;img.loading='lazy';img.alt='';const text=document.createElement('span');text.textContent=m.label;b.append(img,text);b.onclick=()=>{active().values[key]=m.id;picker.close();fields();markDirty();};$('media-grid').append(b);});
    };$('media-search').oninput=draw;draw();picker.showModal();
  }
  async function preview(){
    clearTimeout(timer);if(!state)return;const sequence=++previewSequence;
    let scroll=0,tab=null;try{scroll=frame.contentWindow.scrollY;tab=frame.contentDocument.querySelector('.why-tabs [aria-selected="true"]')?.id;}catch(_){}
    try{const r=await request({action:'preview',content:state.content});if(sequence!==previewSequence)return;
      frame.onload=()=>{try{if(tab)frame.contentDocument.getElementById(tab)?.click();frame.contentWindow.scrollTo(0,scroll);}catch(_) {}};frame.src=r.url;notice('');
    }catch(e){if(sequence===previewSequence)notice(e.message);}
  }
  async function load(){
    try{const r=await request();state=r.state;templates=r.templates;media=r.media;selected=state.content.sections[0].id;dirty=false;
      $('layout').replaceChildren(new Option('Choose a layout…',''));Object.values(templates).filter(t=>Number(t.approved)===1).forEach(t=>$('layout').add(new Option(t.label,t.id)));
      sections();groups();history();status(state.draft===state.published?'Published · version '+state.version:'Saved draft · version '+state.version);$('editor').setAttribute('aria-busy','false');await preview();
    }catch(e){notice(e.message);}
  }
  async function mutate(action){
    if(busy)return;
    if(action==='publish'&&!confirm('Publish these changes to the website?'))return;
    if(action==='restore'&&!confirm('Restore this revision as a new draft? Unsaved changes will be replaced.'))return;
    busy=true;clearTimeout(timer);previewSequence++;$('editor').inert=true;['save','publish','restore'].forEach(id=>$(id).disabled=true);
    try{const body={action,version:state.version};if(action==='restore')body.revision=Number($('revision').value);else body.content=state.content;
      const r=await request(body);const group=$('field-group').value;state=r.state;dirty=false;notice('');history();if(action==='restore'){select(state.content.sections[0].id);await preview();}else{sections();groups();if([...$('field-group').options].some(o=>o.value===group)){$('field-group').value=group;fields();}}
      status((action==='publish'?'Published':action==='restore'?'Restored as draft':'Draft saved')+' · version '+state.version);
    }catch(e){notice(e.message);status('Not saved');}
    finally{busy=false;$('editor').inert=false;['save','publish','restore'].forEach(id=>$(id).disabled=false);}
  }
  $('save').onclick=()=>mutate('save');$('publish').onclick=()=>mutate('publish');$('restore').onclick=()=>mutate('restore');$('refresh').onclick=preview;
  $('field-group').onchange=fields;$('close-media').onclick=()=>$('media-picker').close();
  $('desktop').onclick=()=>{frame.classList.remove('mobile-preview');$('desktop').setAttribute('aria-pressed','true');$('mobile').setAttribute('aria-pressed','false');};
  $('mobile').onclick=()=>{frame.classList.add('mobile-preview');$('mobile').setAttribute('aria-pressed','true');$('desktop').setAttribute('aria-pressed','false');};
  function move(delta){const a=state.content.sections,i=a.findIndex(s=>s.id===selected),j=i+delta;if(i<1||j<1||j>=a.length)return;[a[i],a[j]]=[a[j],a[i]];sections();markDirty();}
  $('up').onclick=()=>move(-1);$('down').onclick=()=>move(1);
  $('remove').onclick=()=>{if(slug==='shared'||state.content.sections[0].id===selected||!confirm('Remove this section from the draft? Revision history will retain previous versions.'))return;const i=state.content.sections.findIndex(s=>s.id===selected);state.content.sections.splice(i,1);select(state.content.sections[Math.max(0,i-1)].id);markDirty();};
  $('add').onclick=()=>{const t=templates[$('layout').value];if(!t||slug!=='home')return;const id=Array.from(crypto.getRandomValues(new Uint8Array(12)),n=>n.toString(16).padStart(2,'0')).join('');state.content.sections.push({id,template:t.id,values:structuredClone(t.defaults)});select(id);markDirty();};
  $('document').onchange=()=>{if(dirty&&!confirm('Leave unsaved changes?')){$('document').value=slug;return;}slug=$('document').value;window.history.replaceState(null,'','/admin/pages?document='+slug);clearTimeout(timer);load();};
  $('reload').onclick=()=>{if(!dirty||confirm('Discard unsaved changes and load the newest saved version?'))load();};
  window.addEventListener('message',event=>{if(event.origin!==location.origin||event.source!==frame.contentWindow||!event.data||Object.keys(event.data).sort().join(',')!=='id,type'||event.data.type!=='edfund:select'||typeof event.data.id!=='string')return;select(event.data.id);});
  window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
  load();
})();
