'use strict';
(() => {
 const link=document.getElementById('editor-return');if(!link)return;
 const params=new URLSearchParams(location.search),anchor=params.get('anchor'),initialRoute=link.dataset.publicRoute;
 const routes=JSON.parse(link.dataset.pageRoutes||'{}');
 function update(route,label,migrate=false){
  if(typeof route!=='string'||!/^\/(?:[a-z0-9-]+\/)*$/.test(route))return;
  const old=link.dataset.publicRoute;
  if(migrate&&old!==route){try{const state=sessionStorage.getItem('edfund.browse.'+old);if(state)sessionStorage.setItem('edfund.browse.'+route,state);}catch(_){}}
  link.dataset.publicRoute=route;link.href=route+(route===initialRoute&&anchor&&/^[A-Za-z][\w:.-]{0,100}$/.test(anchor)?'#'+anchor:'');link.querySelector('span').textContent=label;link.setAttribute('aria-label',label);
 }
 update(link.dataset.publicRoute,link.querySelector('span').textContent);
 document.addEventListener('edfund:document-changed',()=>{const slug=new URLSearchParams(location.search).get('document')||'home';if(routes[slug])update(routes[slug],'Return to page');});
 document.addEventListener('edfund:story-state',event=>{const slug=event.detail?.published_slug,published=event.detail?.published&&!event.detail?.trashed;update(published&&/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)?'/stories-updates/'+slug+'/':'/stories-updates/',published?'Return to story':'View stories',true);});
 link.addEventListener('click',()=>{
  try{const key='edfund.browse.'+link.dataset.publicRoute,state=JSON.parse(sessionStorage.getItem(key)||'null');if(state){state.restore=true;sessionStorage.setItem(key,JSON.stringify(state));}}catch(_){}
 });
})();
