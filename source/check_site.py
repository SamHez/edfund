"""Check generated HTML links, landmarks, image references and SEO basics."""
from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urlsplit, unquote
import json

ROOT = Path(__file__).resolve().parent.parent/'public'
CONFIG=json.loads((ROOT.parent/'source/site.json').read_text())
BASE='/' + CONFIG.get('base_path','').strip('/') if CONFIG.get('base_path','').strip('/') else ''
def local_path(path):
 if BASE:
  assert path.startswith(BASE+'/'), 'Local URL outside configured base path: '+path
  return path[len(BASE):].lstrip('/')
 return path.lstrip('/')
class Page(HTMLParser):
 def __init__(self):
  super().__init__(); self.ids=set(); self.links=[]; self.assets=[];self.h1=0;self.main=0;self.title=0;self.errors=[]
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if 'id' in a:
   if a['id'] in self.ids:self.errors.append('Duplicate id '+a['id'])
   self.ids.add(a['id'])
  if tag=='h1':self.h1+=1
  if tag=='main':self.main+=1
  if tag=='title':self.title+=1
  if tag=='a':self.links.append(a.get('href',''))
  if tag in ['img','script'] and a.get('src'):self.assets.append(a['src'])
  if tag=='img':
   if 'alt' not in a:self.errors.append('Missing image alt')
   if not a.get('width') or not a.get('height'):self.errors.append('Missing image dimensions')
   if a.get('srcset'):
    self.assets.extend(item.strip().split()[0] for item in a['srcset'].split(','))
  if tag=='link' and a.get('rel') in ['stylesheet','icon']:self.assets.append(a['href'])

pages={}
for path in ROOT.rglob('*.html'):
 p=Page();p.feed(path.read_text());pages[path.resolve()]=p
errors=[]
for file,p in pages.items():
 for error in p.errors:errors.append(f'{file.relative_to(ROOT)}: {error}')
 if (p.h1,p.main,p.title)!=(1,1,1):errors.append(f'{file}: expected one h1/main/title, got {p.h1,p.main,p.title}')
 for asset in p.assets:
  if asset.startswith('/') and not (ROOT/local_path(urlsplit(asset).path)).is_file():errors.append(f'{file}: missing asset {asset}')
 for link in p.links:
  u=urlsplit(link)
  if u.scheme or u.netloc:continue
  if not link:errors.append(f'{file}: empty link');continue
  if u.path.startswith('/'):dest=ROOT/local_path(u.path)
  else:dest=file.parent/u.path if u.path else file
  if dest.is_dir():dest=dest/'index.html'
  dest=dest.resolve()
  if not dest.is_file():errors.append(f'{file}: missing target {link}');continue
  if u.fragment and dest in pages and unquote(u.fragment) not in pages[dest].ids:errors.append(f'{file}: missing anchor {link}')
 for bad in ['Lorem ipsum','javascript:void','[CONFIRM']:
  if bad in file.read_text():errors.append(f'{file}: stale demo text {bad}')
report={'html_pages':len(pages),'internal_links':sum(len(p.links) for p in pages.values()),'errors':errors}
print(json.dumps(report,indent=2))
assert not errors
