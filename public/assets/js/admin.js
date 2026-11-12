'use strict';
// Shared confirmation modal: replaces the browser's native confirm() everywhere in the CMS.
window.edfundConfirm=({title='Are you sure?',text='',ok='Confirm',cancel='Cancel',danger=false}={})=>new Promise(resolve=>{
 let dialog=document.getElementById('edfund-confirm');
 if(!dialog){
  dialog=document.createElement('dialog');dialog.id='edfund-confirm';dialog.className='confirm-dialog';dialog.setAttribute('aria-labelledby','edfund-confirm-title');
  dialog.innerHTML='<h2 id="edfund-confirm-title"></h2><p id="edfund-confirm-text"></p><div class="confirm-actions"><button type="button" id="edfund-confirm-cancel" class="confirm-cancel"></button><button type="button" id="edfund-confirm-ok"></button></div>';
  document.body.append(dialog);
  dialog.addEventListener('click',e=>{if(e.target===dialog)dialog.close('cancel');});
 }
 const okButton=dialog.querySelector('#edfund-confirm-ok'),cancelButton=dialog.querySelector('#edfund-confirm-cancel');
 dialog.querySelector('#edfund-confirm-title').textContent=title;const body=dialog.querySelector('#edfund-confirm-text');body.textContent=text;body.hidden=!text;
 okButton.textContent=ok;cancelButton.textContent=cancel;okButton.className=danger?'confirm-danger':'';
 okButton.onclick=()=>dialog.close('ok');cancelButton.onclick=()=>dialog.close('cancel');
 dialog.returnValue='';const done=()=>{dialog.removeEventListener('close',done);resolve(dialog.returnValue==='ok');};dialog.addEventListener('close',done);
 dialog.showModal();cancelButton.focus();
});
// Shared pagination: edfundPager(nav, {page, pages, onPage}) draws Previous / numbered / Next buttons.
window.edfundPager=(nav,{page,pages,onPage})=>{
 nav.replaceChildren();if(pages<=1)return;
 const add=(label,target,{current=false,disabled=false,aria}={})=>{const b=document.createElement('button');b.type='button';b.textContent=label;b.disabled=disabled;if(aria)b.setAttribute('aria-label',aria);if(current)b.setAttribute('aria-current','true');b.onclick=()=>onPage(target);nav.append(b);};
 add('‹',page-1,{disabled:page<=1,aria:'Previous page'});
 const shown=new Set([1,pages,page-1,page,page+1]);let last=0;
 for(let n=1;n<=pages;n++){if(!shown.has(n)||n<1)continue;if(n-last>1){const gap=document.createElement('span');gap.textContent='…';gap.className='pager-gap';nav.append(gap);}add(String(n),n,{current:n===page,aria:'Page '+n});last=n;}
 add('›',page+1,{disabled:page>=pages,aria:'Next page'});
};
(() => {
 // Announce the same existing save statuses with consistent, non-colour-only feedback.
 for(const id of ['save-status','post-status','library-status','page-upload-status']){
  const status=document.getElementById(id);if(!status)continue;status.setAttribute('aria-live','polite');status.setAttribute('aria-atomic','true');
  const update=()=>{const text=status.textContent.trim();status.dataset.state=/loading|uploading|saving|publishing|creating/i.test(text)?'loading':/unsaved|draft|trash/i.test(text)?'draft':/published|saved|updated|uploaded|restored/i.test(text)?'success':'neutral';};new MutationObserver(update).observe(status,{childList:true,subtree:true,characterData:true});update();
 }
})();
