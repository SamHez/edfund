'use strict';
(() => {
 const $=id=>document.getElementById(id),token=document.querySelector('[name=csrf]').value;
 const fail=e=>$('post-error').textContent=e.message;
 async function request(body,id){const options=body?{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf:token,data:JSON.stringify(body)})}:{};const response=await fetch('/admin/posts-data'+(id?'?id='+id:''),{...options,redirect:'error'});if(!response.headers.get('content-type')?.includes('application/json'))throw Error('The session expired or the editor is unavailable. Reload and sign in.');const r=await response.json();if(!response.ok)throw Error(r.error||'The action could not be completed.');return r;}
 if($('posts-app')){
  let all=[],pageNo=1;const PER=12;
  function drawList(){
   const term=$('post-search').value.trim().toLowerCase();const hits=all.filter(p=>(p.title+' '+p.category).toLowerCase().includes(term));const pages=Math.max(1,Math.ceil(hits.length/PER));pageNo=Math.min(pageNo,pages);
   $('post-list').replaceChildren();
   if(!all.length){const empty=document.createElement('div');empty.className='empty-state';const title=document.createElement('h2');title.textContent='Your next story starts here';const text=document.createElement('p');text.textContent='Choose New story to write your first update. You can save it as a draft.';empty.append(title,text);$('post-list').append(empty);}
   else if(!hits.length){const empty=document.createElement('p');empty.className='empty-state';empty.textContent='No stories match your search.';$('post-list').append(empty);}
   const grid=document.createElement('div');grid.className='story-grid-admin';
   for(const p of hits.slice((pageNo-1)*PER,pageNo*PER)){
    const card=document.createElement('a');card.className='story-admin-card';card.href='/admin/post?id='+p.id;
    const thumb=document.createElement('span');thumb.className='story-thumb';if(p.thumb){const img=document.createElement('img');img.src=p.thumb;img.alt='';img.loading='lazy';thumb.append(img);}
    const body=document.createElement('span');body.className='story-body';const title=document.createElement('strong');title.textContent=p.title;
    const meta=document.createElement('span');meta.className='story-meta-line';meta.textContent=[p.category,p.updated?new Date(p.updated.replace(' ','T')+'Z').toLocaleDateString(undefined,{dateStyle:'medium'}):''].filter(Boolean).join(' · ');
    const badge=document.createElement('span');badge.className='pe-badge '+(p.trashed?'':p.published?'is-published':'is-draft');badge.textContent=p.trashed?'In trash':p.published?'Published':'Draft';
    body.append(title,meta,badge);card.append(thumb,body);grid.append(card);}
   if(grid.children.length)$('post-list').append(grid);
   $('post-status').textContent=hits.length+' '+(hits.length===1?'story':'stories');
   edfundPager($('post-pager'),{page:pageNo,pages,onPage:n=>{pageNo=n;drawList();}});
  }
  async function load(){$('post-status').textContent='Loading stories…';$('post-list').setAttribute('aria-busy','true');try{const r=await request();all=r.posts;drawList();}catch(e){$('post-status').textContent='';fail(e);}finally{$('post-list').setAttribute('aria-busy','false');}}
  $('post-search').oninput=()=>{pageNo=1;drawList();};
  $('new-post').onclick=async()=>{$('new-post').disabled=true;$('post-status').textContent='Creating draft…';try{const r=await request({action:'create'});location.href='/admin/post?id='+r.state.id;}catch(e){$('new-post').disabled=false;$('post-status').textContent='';fail(e);}};load();return;
 }
 const id=Number(new URLSearchParams(location.search).get('id')),frame=$('post-frame');let state,media=[],dirty=false,timer,sequence=0,busy=false;
 const keys=['title','card_title','excerpt','slug','category','author','published_at','featured_image','featured_alt','featured_caption','seo_title','seo_description'];
 function mark(){dirty=true;$('post-status').textContent='Unsaved changes';clearTimeout(timer);timer=setTimeout(preview,750);}
 function fillMedia(select,value){select.replaceChildren(new Option('Choose picture…',''));for(const m of media.filter(m=>!m.deleted_at)){select.add(new Option(m.label,m.id));}select.value=value;}
 function featured(){const m=media.find(m=>m.id===state.content.featured_image);$('featured-preview').hidden=!m;if(m)$('featured-preview').src=m.thumbnail;}
 function history(){const s=$('post-revision');s.replaceChildren();for(const r of state.history){s.add(new Option('#'+r.id+' · '+r.action+' · '+r.created_at+' UTC'+(Number(r.id)===state.published?' · published':''),r.id));}}
 function controls(){document.dispatchEvent(new CustomEvent('edfund:story-state',{detail:state}));for(const key of keys){const input=$('post-'+key);if(key==='featured_image')fillMedia(input,state.content[key]);else input.value=state.content[key];input.oninput=()=>{state.content[key]=input.value;if(key==='featured_image'){const m=media.find(m=>m.id===input.value);if(m){state.content.featured_alt=m.alt;state.content.featured_caption=m.caption;$('post-featured_alt').value=m.alt;$('post-featured_caption').value=m.caption;}featured();}mark();};}featured();history();$('post-trash').textContent=state.trashed?'Restore from trash as draft':'Move to trash';$('post-publish').disabled=state.trashed;blocks();}
 function inputField(box,label,value,change,kind='input'){const l=document.createElement('label');l.textContent=label;const field=document.createElement(kind);field.value=value;field.maxLength=label.includes('YouTube')?1000:2000;field.oninput=()=>{change(field.value);mark();};l.append(field);box.append(l);return field;}
 function blocks(){const container=$('post-blocks');container.replaceChildren();state.content.blocks.forEach((block,index)=>{
  const box=document.createElement('section');box.className='post-block';const heading=document.createElement('h3');heading.textContent=(index+1)+'. '+({text:'Formatted text',image:'Picture',youtube:'YouTube video'}[block.type]);box.append(heading);
  const toolbar=document.createElement('div');toolbar.className='block-controls';for(const [label,delta] of [['↑',-1],['↓',1],['Remove',0]]){const b=document.createElement('button');b.type='button';b.textContent=label;b.setAttribute('aria-label',label==='Remove'?'Remove block':delta<0?'Move block up':'Move block down');b.disabled=delta<0?index===0:delta>0?index===state.content.blocks.length-1:state.content.blocks.length===1;b.onclick=()=>{const a=state.content.blocks;if(delta){[a[index],a[index+delta]]=[a[index+delta],a[index]];}else a.splice(index,1);blocks();mark();};toolbar.append(b);}box.append(toolbar);
  if(block.type==='text'){const holder=document.createElement('div');holder.className='rich-editor';box.append(holder);const quill=new Quill(holder,{theme:'snow',formats:['bold','italic','link','header','list'],modules:{toolbar:[[{header:[2,3,false]}],['bold','italic','link'],[{list:'ordered'},{list:'bullet'}],['clean']]}});quill.setContents(block.delta,'silent');quill.on('text-change',()=>{block.delta={ops:quill.getContents().ops};mark();});}
  else{
   const label=document.createElement('label');label.textContent=block.type==='image'?'Picture':'Local video poster';const select=document.createElement('select');const key=block.type==='image'?'media':'poster';fillMedia(select,block[key]);select.onchange=()=>{block[key]=select.value;if(block.type==='image'){const m=media.find(m=>m.id===select.value);block.alt=m?.alt||'';block.caption=m?.caption||'';blocks();}mark();};label.append(select);box.append(label);
   if(block.type==='image'){inputField(box,'Image description',block.alt,v=>block.alt=v);inputField(box,'Caption',block.caption,v=>block.caption=v,'textarea');}
   else{inputField(box,'YouTube link',/^[A-Za-z0-9_-]{11}$/.test(block.video)?'https://www.youtube.com/watch?v='+block.video:block.video,v=>block.video=v);inputField(box,'Video title',block.title,v=>block.title=v);}
  }container.append(box);
 });}
 async function preview(){clearTimeout(timer);if(!state)return;const n=++sequence;try{const r=await request({action:'preview',id,content:state.content});if(n===sequence){frame.src=r.url;$('post-error').textContent='';}}catch(e){if(n===sequence)fail(e);}}
 async function load(){try{const r=await request(null,id);state=r.state;media=r.media;dirty=false;controls();$('post-status').textContent=(state.trashed?'In trash':state.draft===state.published?'Published':'Draft')+' · version '+state.version;await preview();}catch(e){fail(e);}}
 async function mutate(action){if(busy||!state)return;let rename=false;
  if(action==='publish'){if(!await edfundConfirm({title:'Publish this story?',text:'It will go live on the website right away.',ok:'Publish Story'}))return;if(state.published_slug&&state.content.slug!==state.published_slug){rename=await edfundConfirm({title:'Change the published URL?',text:'The previous URL will redirect to the new one.',ok:'Change URL'});if(!rename)return;}}
  if(['restore','trash','untrash'].includes(action)&&!await edfundConfirm(action==='trash'?{title:'Move this story to trash?',text:'Its public URL will stop working until you restore it.',ok:'Move to Trash',danger:true}:{title:'Restore as an unpublished draft?',text:'The story stays private until you publish it.',ok:'Restore Draft'}))return;
  busy=true;$('post-status').textContent=action==='publish'?'Publishing…':'Saving changes…';clearTimeout(timer);sequence++;$('post-editor').inert=true;
  try{const r=await request({id,version:state.version,action,content:state.content,revision:Number($('post-revision').value),rename});state=r.state;dirty=false;controls();$('post-error').textContent='';$('post-status').textContent=(action==='publish'?'Published':action==='trash'?'In trash':action==='untrash'||action==='restore'?'Restored as draft':'Draft saved')+' · version '+state.version;await preview();}catch(e){$('post-status').textContent='Not saved';fail(e);}finally{busy=false;$('post-editor').inert=false;}
 }
 $('post-save').onclick=()=>mutate('save');$('post-publish').onclick=()=>mutate('publish');$('post-restore').onclick=()=>mutate('restore');$('post-trash').onclick=()=>mutate(state.trashed?'untrash':'trash');$('post-preview').onclick=preview;
 function viewport(mobile){frame.classList.toggle('mobile-preview',mobile);$('post-mobile').setAttribute('aria-pressed',String(mobile));$('post-desktop').setAttribute('aria-pressed',String(!mobile));}viewport(false);$('post-mobile').onclick=()=>viewport(true);$('post-desktop').onclick=()=>viewport(false);
 $('post-reload').onclick=async()=>{if(!dirty||await edfundConfirm({title:'Discard unsaved changes?',text:'Your edits will be replaced by the newest saved version.',ok:'Discard and Reload',danger:true}))load();};
 $('post-refresh-media').onclick=async()=>{try{const r=await fetch('/admin/media-data').then(r=>r.json());media=r.media;fillMedia($('post-featured_image'),state.content.featured_image);blocks();featured();}catch(e){fail(e);}};
 $('post-add-block').onclick=()=>{const type=$('post-block-type').value;state.content.blocks.push(type==='text'?{type,delta:{ops:[{insert:'\n'}]}}:type==='image'?{type,media:'',alt:'',caption:''}:{type,video:'',title:'',poster:''});blocks();mark();};
 window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});load();
})();
