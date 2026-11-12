'use strict';
(() => {
 const $=id=>document.getElementById(id),token=document.querySelector('[name=csrf]').value;
 const fail=e=>$('post-error').textContent=e.message;
 async function request(body,id){const options=body?{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf:token,data:JSON.stringify(body)})}:{};const response=await fetch('/admin/posts-data'+(id?'?id='+id:''),{...options,redirect:'error'});if(!response.headers.get('content-type')?.includes('application/json'))throw Error('The session expired or the editor is unavailable. Reload and sign in.');const r=await response.json();if(!response.ok)throw Error(r.error||'The action could not be completed.');return r;}
 if($('posts-app')){
  async function load(){try{const r=await request();$('post-list').replaceChildren();for(const p of r.posts){const card=document.createElement('article');card.className='status-card';const a=document.createElement('a');a.href='/admin/post?id='+p.id;a.textContent=p.title;const text=document.createElement('p');text.textContent=p.trashed?'In trash':p.published?'Published (may also have a draft)':'Draft';card.append(a,text);$('post-list').append(card);}}catch(e){fail(e);}}
  $('new-post').onclick=async()=>{try{const r=await request({action:'create'});location.href='/admin/post?id='+r.state.id;}catch(e){fail(e);}};load();return;
 }
 const id=Number(new URLSearchParams(location.search).get('id')),frame=$('post-frame');let state,media=[],dirty=false,timer,sequence=0,busy=false;
 const keys=['title','card_title','excerpt','slug','category','author','published_at','featured_image','featured_alt','featured_caption','seo_title','seo_description'];
 function mark(){dirty=true;$('post-status').textContent='Unsaved changes';clearTimeout(timer);timer=setTimeout(preview,750);}
 function fillMedia(select,value){select.replaceChildren(new Option('Choose picture…',''));for(const m of media.filter(m=>!m.deleted_at)){select.add(new Option(m.label,m.id));}select.value=value;}
 function featured(){const m=media.find(m=>m.id===state.content.featured_image);$('featured-preview').hidden=!m;if(m)$('featured-preview').src=m.thumbnail;}
 function history(){const s=$('post-revision');s.replaceChildren();for(const r of state.history){s.add(new Option('#'+r.id+' · '+r.action+' · '+r.created_at+' UTC'+(Number(r.id)===state.published?' · published':''),r.id));}}
 function controls(){for(const key of keys){const input=$('post-'+key);if(key==='featured_image')fillMedia(input,state.content[key]);else input.value=state.content[key];input.oninput=()=>{state.content[key]=input.value;if(key==='featured_image'){const m=media.find(m=>m.id===input.value);if(m){state.content.featured_alt=m.alt;state.content.featured_caption=m.caption;$('post-featured_alt').value=m.alt;$('post-featured_caption').value=m.caption;}featured();}mark();};}featured();history();$('post-trash').textContent=state.trashed?'Restore from trash as draft':'Move to trash';$('post-publish').disabled=state.trashed;blocks();}
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
  if(action==='publish'){if(!confirm('Publish this story?'))return;if(state.published_slug&&state.content.slug!==state.published_slug){rename=confirm('Change the published URL and redirect the previous URL?');if(!rename)return;}}
  if(['restore','trash','untrash'].includes(action)&&!confirm(action==='trash'?'Move this story to trash? Its public URL will stop serving.':'Restore as an unpublished draft?'))return;
  busy=true;clearTimeout(timer);sequence++;$('post-editor').inert=true;
  try{const r=await request({id,version:state.version,action,content:state.content,revision:Number($('post-revision').value),rename});state=r.state;dirty=false;controls();$('post-error').textContent='';$('post-status').textContent=(action==='publish'?'Published':action==='trash'?'In trash':action==='untrash'||action==='restore'?'Restored as draft':'Draft saved')+' · version '+state.version;await preview();}catch(e){fail(e);}finally{busy=false;$('post-editor').inert=false;}
 }
 $('post-save').onclick=()=>mutate('save');$('post-publish').onclick=()=>mutate('publish');$('post-restore').onclick=()=>mutate('restore');$('post-trash').onclick=()=>mutate(state.trashed?'untrash':'trash');$('post-preview').onclick=preview;
 $('post-mobile').onclick=()=>frame.classList.add('mobile-preview');$('post-desktop').onclick=()=>frame.classList.remove('mobile-preview');
 $('post-reload').onclick=()=>{if(!dirty||confirm('Discard unsaved changes and reload?'))load();};
 $('post-refresh-media').onclick=async()=>{try{const r=await fetch('/admin/media-data').then(r=>r.json());media=r.media;fillMedia($('post-featured_image'),state.content.featured_image);blocks();featured();}catch(e){fail(e);}};
 $('post-add-block').onclick=()=>{const type=$('post-block-type').value;state.content.blocks.push(type==='text'?{type,delta:{ops:[{insert:'\n'}]}}:type==='image'?{type,media:'',alt:'',caption:''}:{type,video:'',title:'',poster:''});blocks();mark();};
 window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});load();
})();
