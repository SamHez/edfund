'use strict';
(() => {
 const bar=document.getElementById('edfund-admin-toolbar');if(!bar)return;
 const route=bar.dataset.publicRoute,edit=document.getElementById('toolbar-edit'),key='edfund.browse.'+route;
 const read=()=>{try{return JSON.parse(sessionStorage.getItem(key)||'null');}catch(_){return null;}};
 if(edit)edit.addEventListener('click',()=>{
  const offset=bar.offsetHeight+(document.querySelector('.site-header')?.offsetHeight||0),sections=[...document.querySelectorAll('main>[data-cms-section]')];
  const section=sections.map(el=>{const r=el.getBoundingClientRect();return {el,visible:Math.max(0,Math.min(r.bottom,innerHeight)-Math.max(r.top,offset))};}).sort((a,b)=>b.visible-a.visible).find(item=>item.visible>0)?.el;
  const url=new URL(edit.href);if(section)url.searchParams.set('section',section.dataset.cmsSection);
  const slides=[...document.querySelectorAll('.hero-slider .hero-slide')],active=slides.findIndex(el=>el.classList.contains('is-active'));if(section&&active>=0&&section.contains(slides[active]))url.searchParams.set('slide',String(active+1));
  if(/^#[A-Za-z][\w:.-]{0,100}$/.test(location.hash))url.searchParams.set('anchor',location.hash.slice(1));edit.href=url.pathname+url.search;
  const state={time:Date.now(),scroll:scrollY,hash:location.hash,tab:document.querySelector('.why-tabs [aria-selected=true]')?.id,restore:false};
  try{sessionStorage.setItem(key,JSON.stringify(state));}catch(_){/* Optional browsing context; editing still works without storage. */}
 });
 document.querySelectorAll('form[action="/admin/new-story"]').forEach(form=>form.addEventListener('submit',()=>{form.querySelector('button').disabled=true;}));
 const state=read();
 if(state?.restore&&Date.now()-state.time<8*60*60*1000){
  try{sessionStorage.removeItem(key);}catch(_){}
  const restore=()=>{if(/^why-tab-[0-3]$/.test(state.tab||''))document.getElementById(state.tab)?.click();if(Number.isFinite(state.scroll)&&state.scroll>=0)window.scrollTo({top:Math.min(state.scroll,document.documentElement.scrollHeight-innerHeight),behavior:'instant'});};
  if(document.readyState==='complete')restore();else window.addEventListener('load',restore,{once:true});
 }
 window.addEventListener('pageshow',event=>{if(event.persisted)location.reload();});
})();
