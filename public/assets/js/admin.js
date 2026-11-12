'use strict';
(() => {
 const body=document.body,sidebar=document.getElementById('admin-sidebar');
 if(!sidebar)return;
 body.classList.add('admin-ready');
 const toggle=document.getElementById('sidebar-toggle'),collapse=document.getElementById('sidebar-collapse'),backdrop=document.getElementById('sidebar-backdrop'),workspace=document.querySelector('.admin-workspace'),mobile=matchMedia('(max-width: 900px)'),account=document.querySelector('.account-menu');
 let open=false;
 function drawer(value,focus=true){
  open=value&&mobile.matches;body.classList.toggle('sidebar-open',open);backdrop.hidden=!open;toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',open?'Close navigation':'Open navigation');
  sidebar.inert=mobile.matches&&!open;workspace.inert=open;
  if(open){account.open=false;(sidebar.querySelector('[aria-current="page"]')||sidebar.querySelector('.sidebar-link')).focus();}
  else if(focus)toggle.focus();
 }
 function compact(value){body.classList.toggle('sidebar-compact',value);collapse.setAttribute('aria-expanded',String(!value));collapse.setAttribute('aria-label',value?'Expand sidebar':'Collapse sidebar');collapse.querySelector('span').textContent=value?'Expand sidebar':'Collapse sidebar';}
 try{compact(localStorage.getItem('edfund.sidebar.compact')==='true');}catch(_){/* Storage restrictions must not affect navigation. */}
 collapse.onclick=()=>{const value=!body.classList.contains('sidebar-compact');compact(value);try{localStorage.setItem('edfund.sidebar.compact',String(value));}catch(_){}};
 toggle.onclick=()=>drawer(!open);backdrop.onclick=()=>drawer(false);document.getElementById('sidebar-close').onclick=()=>drawer(false);
 mobile.addEventListener('change',()=>drawer(false,false));drawer(false,false);
 document.addEventListener('keydown',e=>{
  if(e.key==='Escape'){if(open){e.preventDefault();drawer(false);}else if(account.open){account.open=false;account.querySelector('summary').focus();}}
  if(open&&e.key==='Tab'){
   const links=[...sidebar.querySelectorAll('a,button')].filter(el=>el.getClientRects().length&&!el.disabled),first=links[0],last=links[links.length-1];
   if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}
  }
 });
 document.addEventListener('click',e=>{if(account.open&&!account.contains(e.target))account.open=false;});
 account.addEventListener('focusout',()=>{setTimeout(()=>{if(!account.contains(document.activeElement))account.open=false;},0);});
 function pageNavigation(){if(location.pathname!=='/admin/pages')return;const shared=new URLSearchParams(location.search).get('document')==='shared';document.getElementById('admin-page-title').textContent=shared?'Settings':'Pages';document.querySelectorAll('[data-admin-nav]').forEach(link=>{if(link.dataset.adminNav===(shared?'settings':'pages'))link.setAttribute('aria-current','page');else link.removeAttribute('aria-current');});document.title=(shared?'Settings':'Pages')+' | EdFund administration';}
 document.addEventListener('edfund:document-changed',pageNavigation);window.addEventListener('popstate',pageNavigation);
 // Announce the same existing save statuses with consistent, non-colour-only feedback.
 for(const id of ['save-status','post-status','library-status','page-upload-status']){
  const status=document.getElementById(id);if(!status)continue;status.setAttribute('aria-live','polite');status.setAttribute('aria-atomic','true');
  const update=()=>{const text=status.textContent.trim();status.dataset.state=/loading|uploading|saving|publishing|creating/i.test(text)?'loading':/unsaved|draft|trash/i.test(text)?'draft':/published|saved|updated|uploaded|restored/i.test(text)?'success':'neutral';};new MutationObserver(update).observe(status,{childList:true,subtree:true,characterData:true});update();
 }
})();
