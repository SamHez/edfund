"""Indent block HTML while keeping inline text and script contents intact."""
from html.parser import HTMLParser
VOID=set('area base br col embed hr img input link meta param source track wbr'.split())
INLINE=set('a abbr b br button em h1 h2 h3 h4 i label option p small span strong summary svg textarea title script'.split())
class Parser(HTMLParser):
 def __init__(self):
  super().__init__(convert_charrefs=False); self.root=['', '', []]; self.stack=[self.root]
 def handle_starttag(self,tag,attrs):
  node=[tag,self.get_starttag_text(),[]]; self.stack[-1][2].append(node)
  if tag not in VOID:self.stack.append(node)
 def handle_startendtag(self,tag,attrs):self.stack[-1][2].append(self.get_starttag_text())
 def handle_endtag(self,tag):
  if len(self.stack)>1 and self.stack[-1][0]==tag:self.stack.pop()
 def handle_data(self,data):self.stack[-1][2].append(data)
 def handle_entityref(self,name):self.handle_data('&'+name+';')
 def handle_charref(self,name):self.handle_data('&#'+name+';')
 def handle_decl(self,decl):self.handle_data('<!'+decl+'>')
 def handle_comment(self,data):self.handle_data('<!--'+data+'-->')
def compact(node):
 if isinstance(node,str):return node
 tag,start,children=node
 return start+''.join(map(compact,children))+('' if tag in VOID else '</'+tag+'>')
def lines(node,depth=0):
 if isinstance(node,str):return [('  '*depth)+node.strip()] if node.strip() else []
 tag,start,children=node; prefix='  '*depth
 if tag in INLINE or tag in VOID:return [prefix+compact(node)]
 out=[prefix+start]
 for child in children:out.extend(lines(child,depth+1))
 out.append(prefix+'</'+tag+'>');return out
def pretty_html(text):
 parser=Parser();parser.feed(text)
 return '\n'.join(line for node in parser.root[2] for line in lines(node))+'\n'
