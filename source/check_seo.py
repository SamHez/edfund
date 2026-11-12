"""Validate metadata, crawlability and structured data in generated pages."""
from pathlib import Path
from html.parser import HTMLParser
import json
import xml.etree.ElementTree as ET
ROOT=Path(__file__).resolve().parent.parent
PUBLIC=ROOT/'public'
class Page(HTMLParser):
 def __init__(self):
  super().__init__();self.meta={};self.links={};self.title='';self.schema=[];self.in_title=False;self.in_schema=False;self.raw=''
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='meta':self.meta[a.get('name',a.get('property'))]=a.get('content')
  if tag=='link':self.links[a.get('rel')]=a.get('href')
  if tag=='title':self.in_title=True
  if tag=='script' and a.get('type')=='application/ld+json':self.in_schema=True;self.raw=''
 def handle_data(self,data):
  if self.in_title:self.title+=data
  if self.in_schema:self.raw+=data
 def handle_endtag(self,tag):
  if tag=='title':self.in_title=False
  if tag=='script' and self.in_schema:self.schema.append(json.loads(self.raw));self.in_schema=False
pages=[];errors=[];titles=set();descriptions=set()
config=json.loads((ROOT/'source/site.json').read_text())
origin=config['origin'].rstrip('/')+('/'+config.get('base_path','').strip('/') if config.get('base_path','').strip('/') else '')
urls={e.text for e in ET.parse(PUBLIC/'sitemap.xml').iter('{http://www.sitemaps.org/schemas/sitemap/0.9}loc')}
for path in sorted(PUBLIC.rglob('*.html')):
 p=Page();p.feed(path.read_text());label=str(path.relative_to(PUBLIC))
 if path.name=='404.html':
  assert p.meta.get('robots')=='noindex,follow'
  assert p.links.get('canonical') not in urls
  continue
 route='/' if label=='index.html' else '/'+label.removesuffix('index.html')
 expected=origin+route
 tests={'unique_title':p.title not in titles,'unique_description':p.meta.get('description') not in descriptions,'canonical':p.links.get('canonical')==expected,'sitemap':expected in urls,'indexing_mode':('index' if config['publish_ready'] else 'noindex') in p.meta.get('robots','').split(','),'description':bool(p.meta.get('description')),'social_url':p.meta.get('og:url')==expected,'structured_data':bool(p.schema and p.schema[0].get('@graph'))}
 for key,ok in tests.items():
  if not ok:errors.append(label+': '+key)
 titles.add(p.title);descriptions.add(p.meta.get('description'));pages.append({'page':label,'title':p.title,'checks':tests})
result={'pages':pages,'errors':errors,'scope':'Local technical SEO checks; live indexing, server redirects and field performance require deployment.'}
(ROOT/'docs/seo-checks.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps({'pages':len(pages),'errors':errors},indent=2))
raise SystemExit(bool(errors))
