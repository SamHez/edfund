#!/usr/bin/env python3
"""Build EdFund's dependency-free static demonstration website."""
from pathlib import Path
from html import escape as E
from datetime import date
from hashlib import sha256
import json
import re
from format_html import pretty_html
from icons import icon

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / 'public'
C = json.loads((ROOT / 'source/site.json').read_text())
IMAGES = json.loads((ROOT / 'source/images.json').read_text())
BASE = '/' + C.get('base_path', '').strip('/') if C.get('base_path', '').strip('/') else ''
ORIGIN = C['origin'].rstrip('/') + BASE
NAV = [('/', 'Home'), ('/about-us/', 'About Us'), ('/our-work/', 'Our Work'), ('/our-team/', 'Our Team'), ('/stories-updates/', 'Stories & Updates'), ('/gallery/', 'Gallery'), ('/volunteer/', 'Volunteer'), ('/contact/', 'Contact')]
PAGES = {}

def with_base_path(html):
    """Prefix local URL attributes, including every responsive image candidate."""
    if not BASE:
        return html
    def rewrite(match):
        name, quote, value = match.groups()
        if name in ('srcset', 'imagesrcset'):
            value = ', '.join((BASE + entry.strip() if entry.strip().startswith('/') and not entry.strip().startswith('//') else entry.strip()) for entry in value.split(','))
        elif value.startswith('/') and not value.startswith('//'):
            value = BASE + value
        return name + '=' + quote + value + quote
    return re.sub(r"\b(href|src|srcset|imagesrcset)=([\"'])(.*?)\2", rewrite, html)

def button(label, href='/contact/', kind='primary'):
    return f'<a class="button {kind}" href="{E(href)}">{E(label)} <span aria-hidden="true">↗</span></a>'

def donate(label='Donate', kind='primary'):
    return button(label, C.get('donation_url') or '/contact/#giving', kind)

def eyebrow(text):
    return f'<p class="eyebrow">{E(text)}</p>'

def photo(name, cls='', eager=False, sizes='(max-width: 700px) 100vw, 50vw'):
    im = IMAGES[name]
    src = f'/assets/images/edfund/{name}.webp'
    responsive = f' srcset="/assets/images/edfund/{name}-mobile.webp {im["mobile_width"]}w, {src} {im["width"]}w" sizes="{sizes}"' if im.get('mobile_width') and im['mobile_width'] != im['width'] else ''
    return f'<img class="{cls}" src="{src}"{responsive} alt="{E(im["alt"])}" width="{im["width"]}" height="{im["height"]}" {"fetchpriority=high" if eager else "loading=lazy"} decoding="async">'

def brand(panel=False):
    logo = C['navigation_logo']
    cls = 'brand brand-panel' if panel else 'brand'
    return f'<a class="{cls}" href="/" aria-label="EdFund Uganda Foundation home"><img src="{E(logo["src"])}" width="{logo["width"]}" height="{logo["height"]}" alt="EdFund Uganda Foundation"></a>'

def socials():
    return ''.join(f'<a href="{E(s["url"])}" rel="noopener noreferrer">{E(s["name"])}</a>' for s in C['socials'])

def header(route):
    links = ''.join(f'<a href="{p}"'+(' aria-current="page"' if route == p else '')+f'>{n}</a>' for p, n in NAV)
    return f'''<a class="skip-link" href="#main">Skip to content</a><div class="topbar"><div class="container topbar-inner"><a href="mailto:{C['email']}">{icon('mail')}{C['email']}</a><a class="top-phone" href="tel:{C['telephone']}">{C['phone']}</a><span class="top-location">{icon('pin')}{C['location']}</span><div class="socials">{socials()}</div></div></div><header class="site-header"><div class="container nav-wrap">{brand()}<button class="menu-toggle" aria-controls="main-nav" aria-expanded="false">Menu <span aria-hidden="true">☰</span></button><nav id="main-nav" aria-label="Main navigation">{links}<span class="mobile-donate">{donate()}</span></nav><div class="desktop-donate">{donate()}</div></div></header>'''

def footer():
    return f'''<footer class="site-footer"><div class="container footer-grid"><div>{brand(True)}<p>Education, technology and community.<br>Opening opportunities in Kamuli, Uganda.</p><div class="socials">{socials()}</div></div><div><h2>Discover EdFund</h2>{''.join(f'<a href="{p}">{n}</a>' for p,n in NAV[1:5])}</div><div><h2>Be part of the work</h2><a href="/gallery/">Our gallery</a><a href="/volunteer/">Volunteer</a><a href="/contact/#partnerships">Partner with us</a><a href="/contact/#giving">Giving enquiries</a></div><div><h2>Let’s connect</h2><a href="mailto:{C['email']}">{C['email']}</a><a href="tel:{C['telephone']}">{C['phone']}</a><p>{C['address']}</p><a class="footer-action" href="/contact/">Contact our team ↗</a></div></div><div class="container footer-bottom"><span>© {date.today().year} {C['name']}</span><span><a href="/privacy-policy/">Privacy policy</a><a href="#top">Back to top ↑</a></span></div></footer>'''

def cta():
    return '<section class="final-cta"><div class="container"><div>'+eyebrow('A future worth building')+'<h2>Help us improve<br>education in Kamuli.</h2></div><div><p>Be part of the next chapter.<br>Start a conversation with EdFund.</p>'+donate('Support EdFund', 'white')+'</div></div></section>'

def banner(kicker, title, description, image=None):
    settings = C['page_banners'].get(kicker, {})
    title = settings.get('title', title)
    image = settings.get('image', image) or 'learning-together'
    visual = photo(image, eager=True, sizes="100vw")
    watermark = '<img class="banner-watermark" src="/assets/images/edfund-mark.svg" width="200" height="200" alt="" aria-hidden="true">'
    return f'<section class="page-banner photo-banner">{visual}<div class="container"><p class="banner-breadcrumb"><a href="/">Home</a><span aria-hidden="true">/</span>{E(title)}</p><h1>{E(title)}</h1><p>{E(description)}</p></div>{watermark}</section>'

def section(content, cls=''):
    return f'<section class="section {cls}"><div class="container">{content}</div></section>'

def heading(kicker, title, link=None):
    return '<div class="section-heading"><div>'+eyebrow(kicker)+f'<h2>{E(title)}</h2></div>'+(f'<a class="text-link" href="{link[1]}">{link[0]} ↗</a>' if link else '')+'</div>'

def split(image, kicker, title, content, reverse=False):
    return section(f'<div class="split {"reverse" if reverse else ""}"><div class="split-image">{photo(image)}</div><div class="split-copy">{eyebrow(kicker)}<h2>{E(title)}</h2>{content}</div></div>')

def focus_cards():
    return '<div class="focus-grid">'+''.join(f'<article class="focus-card reveal"><a class="card-photo" href="/our-work/#{x["anchor"]}" aria-label="Explore {x["name"].lower()}">{photo(x["image"])}</a><div class="card-body"><span class="card-number">0{i+1}</span><h3>{x["name"]}</h3><p>{x["text"]}</p><a class="text-link" href="/our-work/#{x["anchor"]}">Explore {x["name"].lower()} ↗</a></div></article>' for i,x in enumerate(C['focus']))+'</div>'

def story_cards(limit=None, rows=False):
    return '<div class="story-list '+('rows' if rows else 'story-grid')+'">'+''.join(f'<article class="story-card reveal"><a class="story-photo" href="/stories-updates/{s["slug"]}/" aria-label="Read: {E(s["title"])}">{photo(s["image"])}</a><div class="story-copy"><p class="story-meta"><span>{E(s["category"])}</span><time datetime="{s["date"]}">{date.fromisoformat(s["date"]).strftime("%d %B %Y")}</time></p><h{2 if rows else 3}><a href="/stories-updates/{s["slug"]}/">{E(s["title"])}</a></h{2 if rows else 3}><p>{E(s["excerpt"])}</p><a class="text-link" href="/stories-updates/{s["slug"]}/">Read the story ↗</a></div></article>' for s in C['stories'][:limit])+'</div>'

def gallery(preview=False):
    names = C['gallery_preview'] if preview else C['gallery']
    items = ''
    for n, name in enumerate(names):
        im = IMAGES[name]
        visual = photo(name, sizes='(max-width: 600px) 100vw, (max-width: 900px) 50vw, 60vw' if n == 0 else '(max-width: 600px) 50vw, 33vw')
        caption = f'<span class="gallery-caption"><small>{E(im["category"])}</small><span>{E(im["alt"])}</span></span>'
        href = f'/assets/images/edfund/{name}.webp'
        attributes = f' data-caption="{E(im["alt"])}"'
        visual = f'<a class="gallery-open {"preview-link" if preview else ""}" href="{href}"{attributes} aria-label="{E(("Explore gallery: " if preview else "Enlarge: ")+im["alt"])}">{visual}{caption}<span class="enlarge" aria-hidden="true">↗</span></a>'
        items += f'<figure data-category="{E(im["category"])}">{visual}</figure>'
    return '<div class="gallery-grid '+('preview' if preview else 'gallery-mosaic')+'">'+items+'</div>'

def hero():
    slides = []
    for i, slide in enumerate(C['hero_slides']):
        tag = 'h1' if i == 0 else 'h2'
        hidden = '' if i == 0 else ' aria-hidden="true" inert'
        content = f'<div class="container hero-content"><div class="hero-copy">{eyebrow(slide["eyebrow"])}<{tag} class="hero-title">{E(slide["title"])}<br><em>{E(slide["accent"])}</em></{tag}><p>{E(slide["text"])}</p><div class="button-row">{button("Explore Our Work","/our-work/")}{donate("Support EdFund","hero-outline")}</div></div></div>'
        slides.append(f'<div class="hero-slide {"is-active" if i == 0 else ""}" id="hero-slide-{i}" role="group" aria-roledescription="slide" aria-label="{i+1} of {len(C["hero_slides"])}"{hidden} style="--photo-position:{slide["position"]}">'+photo(slide['image'],eager=i==0,sizes='100vw')+content+'</div>')
    dots = ''.join(f'<button class="slide-dot" aria-label="Show photograph {i+1}" aria-controls="hero-slide-{i}" aria-pressed="{str(i==0).lower()}" data-slide="{i}"><span class="sr-only">{i+1}</span></button>' for i in range(len(slides)))
    return f'<section class="hero hero-slider" aria-label="Education and opportunity in Kamuli" aria-roledescription="carousel" data-interval="{C["hero_slide_interval_ms"]}" data-transition="{C["hero_transition_ms"]}" style="--hero-height-desktop:{int(C["hero_height_desktop_px"])}px;--hero-height-mobile:{int(C["hero_height_mobile_px"])}px;--slide-duration:{C["hero_slide_interval_ms"]+C["hero_transition_ms"]}ms;--slide-fade:{C["hero_transition_ms"]}ms">'+''.join(slides)+'<button type="button" class="hero-arrow hero-arrow-prev" data-hero-prev aria-label="Previous slide"><span aria-hidden="true">←</span></button><button type="button" class="hero-arrow hero-arrow-next" data-hero-next aria-label="Next slide"><span aria-hidden="true">→</span></button>'+f'<div class="hero-bottom container"><div class="hero-location">{icon("pin")}<span>Rooted in Kamuli. Open to possibility.</span></div><div class="hero-controls"><span class="slide-counter" aria-hidden="true">01 / 03</span>{dots}</div></div></section>'

def impact():
    approved = [i for i in C['impact'] if i['verified'] and i['value'] is not None]
    if not approved:
        return ''
    body = ''.join(f'<div class="kpi"><span class="kpi-icon">{icon(i["icon"])}</span><div><strong>{E(str(i["value"]))}</strong><span>{E(i["label"])}</span></div></div>' for i in approved)
    return '<section class="kpi-section" aria-label="EdFund in numbers"><div class="container"><div class="kpi-panel">'+body+'</div><p class="kpi-source">Education in action <span>·</span> Figures reported by <a href="https://edfundfoundation.org/">EdFund Uganda Foundation ↗</a></p></div></section>'

def who_we_are(homepage=False):
    details = C['who_we_are']
    paragraphs = ''.join(f'<p>{E(text)}</p>' for text in details['paragraphs'])
    if not homepage:
        paragraphs = paragraphs.replace(E(details['name']), '<strong>'+E(details['name'])+'</strong>', 1)
    principles = ''.join(f'<article class="who-principle {kind}"><div class="who-principle-heading"><span class="principle-icon">{icon(ic)}</span><h3>{title}</h3></div><p>{E(text)}</p></article>' for kind,ic,title,text in [('vision-card' if homepage else 'mission-card','people','Our Mission',C['mission']),('vision-card','leaf','Our Vision',C['vision'])])
    visual = f'<figure class="who-photo">{photo(details["image"])}<figcaption>{E(details.get("home_image_caption", details["image_caption"]) if homepage else details["image_caption"])}</figcaption></figure>'
    copy = '<div class="who-intro-copy">'+eyebrow('We are EdFund Uganda Foundation')+f'<h2>{E(details["heading"])}</h2><div class="who-narrative">{paragraphs}</div><a class="text-link" href="/about-us/">Discover EdFund ↗</a></div>'
    return section('<div class="who-intro-grid">'+visual+copy+'</div><div class="who-principles">'+principles+'</div>', 'who-section')

def video_section():
    cards = ''
    for video in C['videos']:
        url = 'https://www.youtube.com/watch?v='+video['id']
        poster = photo(video['poster'])
        cards += f'<article class="video-card"><div class="video-frame" data-video-id="{video["id"]}" data-video-title="{E(video["title"])}"><a class="video-play" href="{url}" aria-label="Play video: {E(video["title"])}">{poster}<span class="play-icon" aria-hidden="true">▶</span><span class="video-platform">Watch on YouTube ↗</span></a></div><div class="video-copy"><h3>{E(video["title"])}</h3><p>{E(video["description"])}</p><a class="text-link" href="{url}">Open on YouTube ↗</a></div></article>'
    return section(heading('See the work','From Kamuli, in motion.')+'<div class="video-grid">'+cards+'</div><p class="video-note">Select a video to load the YouTube player.</p>', 'soft video-section')

def photo_viewer():
    first = C['why_uganda']['chapters'][0]['images'][0]
    viewer = f'<dialog id="why-lightbox" aria-labelledby="why-lightbox-title" aria-describedby="why-lightbox-caption"><div class="why-viewer-toolbar"><h2 id="why-lightbox-title">Photo gallery</h2><button type="button" data-why-close aria-label="Close photo gallery">Close ×</button></div><div class="why-viewer-stage"><button type="button" class="why-viewer-prev" data-why-photo-prev aria-label="Previous photograph">←</button><img src="/assets/images/edfund/{first}.webp" width="{IMAGES[first]["width"]}" height="{IMAGES[first]["height"]}" alt="" loading="lazy"><button type="button" class="why-viewer-next" data-why-photo-next aria-label="Next photograph">→</button></div><p id="why-lightbox-caption" aria-live="polite"></p><div class="why-viewer-thumbnails" aria-label="Photographs in this chapter"></div></dialog>'
    return viewer

def kitchen_project():
    def card(name, label, stage):
        im = IMAGES[name]
        image = photo(name, sizes='(max-width:700px) 90vw, 25vw')
        if name.startswith('classrooms-'):
            image = image.replace('<img ', '<img style="object-fit:contain" ', 1)
        return f'<figure class="kitchen-photo {stage}"><a class="kitchen-photo-link" href="/assets/images/edfund/{name}.webp" data-caption="{E(im["alt"])}" aria-label="Enlarge: {E(im["alt"])}">{image}<span class="why-enlarge" aria-hidden="true">↗</span></a><figcaption>{E(label)}</figcaption></figure>'
    stories = [
        ('kitchen', 'The kitchen', 'A better place to cook and eat.', [
            'From open-fire cooking and outdoor meals to a dedicated kitchen and dining hall.',
            'The 2010 photographs show meals being prepared in large drums over open fires. Cooking took place among smoke and firewood, with the pots resting directly above the flames.',
            'By 2015, the school kitchen had fuel-efficient stoves built into raised cooking surfaces. The photographs show the new cooking area and a cook using one of the fitted stoves: a clear change in the spaces used to prepare school meals.'
        ], [('kitchen-drums-2010','Before · 2010 — cooking over open fires'),('kitchen-beans-posho-2010','Before · 2010 — preparing school meals')], [('kitchen-stove-2015','After · 2015 — cooking on a fitted stove'),('kitchen-efficient-stoves-2015','After · 2015 — fuel-efficient stoves')]),
        ('dining', 'The dining space', 'A dedicated place to share a meal.', [
            'The earlier photographs document both meal preparation over open fires and children eating outdoors beside the old kitchen. Together, they show the conditions surrounding a daily part of school life.',
            'The completed kitchen and dining hall appears in the 2012 photographs. Inside, children sit together at tables and benches in a covered dining space. Outside, the new building brings the cooking and dining facilities together on the school campus.',
            'These before-and-after views show how rebuilding extends beyond classrooms to the everyday spaces where children eat and spend time together.'
        ], [('kitchen-outdoor-meals-2010','Before · 2010 — meals outdoors'),('kitchen-dining-construction-2012','Before · 2012 — kitchen and dining hall under construction')], [('kitchen-dining-inside-2012','After · 2012 — a dedicated dining hall'),('kitchen-dining-completed-2012','After · 2012 — the completed building')])
    ]
    stories.append(('dormitory', 'Girls’ and boys’ dormitories', 'Better places to rest and grow.', [
        'Rebuilding school accommodation is part of EdFund’s wider response at Kamuli Township Primary School. Alongside classrooms and shared facilities, dormitories are an important part of everyday school life for girls and boys.',
        'The before photographs show the girls’ older accommodation: beds arranged in existing rooms beneath the building’s roof structure. They provide a closer view of the living spaces included in the school’s rebuilding story.',
        'The after photographs focus on the new girls’ dormitory, showing its interior with rows of bunk beds and the 2025 opening ceremony outside the building. Together, these views document the change from the older accommodation to the new dormitory.'
    ], [('girls-dormitory-old-interior-1','Before — inside the older girls’ accommodation'),('girls-dormitory-old-interior-2','Before — beds in the older girls’ accommodation'),('girls-teachers-old-housing','Before — older housing for boys dorm')], [('girls-dormitory-new-interior','After — inside the new girls’ dormitory'),('girls-dormitory-opening-2025','After · 2025 — new girls’ dormitory opening'),('hero-main','After — the new boys dormitory')]))
    stories.append(('classrooms', 'Classroom reconstruction', 'Better classrooms. Brighter beginnings.', [
        'Classroom rebuilding is central to EdFund’s work at Kamuli Township Primary School. Renewing existing buildings connects the foundation’s education mission with the spaces children use to learn each day.',
        'The before photograph shows an older classroom block with weathered walls and timber supports along its covered walkway. It records the condition of the building before renovation.',
        'The after photograph brings together two views of the renovated classroom building. Renewed walls, windows and a covered walkway show the transformation from the older structure to a refreshed learning environment.'
    ], [('classrooms-before','Before — the classroom building before renovation')], [('classrooms-renovated','After — the renovated classroom building')]))
    rows = ''
    for key,label,title,paragraphs,before,after in stories:
        copy = '<div class="response-project-copy">'+eyebrow('Before & after · '+label)+f'<h3 id="{key}-title">{E(title)}</h3>'+''.join(f'<p>{E(text)}</p>' for text in paragraphs)+'</div>'
        photos = ''.join('<div class="response-photo-group"><h4>'+stage+'</h4>'+''.join(card(name,caption,stage.lower()) for name,caption in items)+'</div>' for stage,items in [('Before',before),('After',after)])
        rows += f'<section class="response-project" aria-labelledby="{key}-title" data-project-title="{E(label)}">{copy}<div class="response-photo-grid">{photos}</div></section>'
    return '<div class="kitchen-project response-projects">'+rows+'</div>'

def why_uganda():
    content = C['why_uganda']
    tabs, panels = [], []
    for i, chapter in enumerate(content['chapters']):
        tabs.append(f'<button id="why-tab-{i}" role="tab" aria-controls="why-panel-{i}" aria-selected="{str(i == 0).lower()}" tabindex="{0 if i == 0 else -1}"><span class="why-tab-number">0{i+1}</span><span class="why-tab-label">{E(chapter["label"])}</span></button>')
        images = ''
        for j, name in enumerate(chapter['images']):
            alt = E(IMAGES[name]['alt'])
            link = f'<a class="why-photo-link" href="/assets/images/edfund/{name}.webp" data-why-gallery="{i}" data-why-photo="{j}" data-caption="{alt}" aria-label="Enlarge photograph {j+1}: {alt}">'+photo(name, sizes='(max-width:700px) 90vw, (max-width:1050px) 55vw, 40vw')+'<span class="why-enlarge" aria-hidden="true">↗</span></a>'
            images += f'<figure>{link}<figcaption>{alt}</figcaption></figure>'
        paragraphs = ''.join(f'<p>{E(text)}</p>' for text in chapter.get('paragraphs', [chapter['text']]))
        source = f'<a class="why-source" href="{E(chapter["source"])}">{E(chapter["source_label"])} ↗</a>' if chapter.get('source') else ''
        copy = f'<div class="why-story-text"><p class="why-chapter-kicker">Chapter 0{i+1}<span aria-hidden="true"> / </span>04</p><h3>{E(chapter["title"])}</h3><div class="why-prose">{paragraphs}</div>{source}</div>'
        visual = f'<div class="why-photo-collage" data-photo-count="{len(chapter["images"])}">{images}</div>'
        panels.append(f'<article class="why-chapter" id="why-panel-{i}" role="tabpanel" aria-labelledby="why-tab-{i}">{copy}{visual}{kitchen_project() if i == 3 else ''}</article>')
    controls = '<div class="why-arrows" hidden><div class="why-arrow-sticky"><button type="button" data-why-prev aria-label="Previous chapter">←</button><button type="button" data-why-next aria-label="Next chapter">→</button></div></div>'
    heading_html = '<header class="why-section-heading">'+eyebrow(content['eyebrow'])+f'<h2>{E(content["title"])}</h2><p class="why-introduction">{E(content["introduction"])}</p></header>'
    return '<section class="section why-story" id="why-uganda"><div class="container">'+heading_html+'<div class="why-workspace"><div class="why-tabs" role="tablist" aria-orientation="vertical" aria-label="Explore Why Uganda" hidden>'+''.join(tabs)+'</div><div class="why-card-stage">'+controls+'<div class="why-chapters">'+''.join(panels)+'</div><p class="why-navigation-status" role="status" aria-live="polite" hidden></p></div></div></div></section>'

def home():
    intro=who_we_are(homepage=True)
    involved=section(heading('Make a connection','There is a place for you in this story.')+'<div class="involve-grid">'+''.join(f'<a href="{url}"><span>0{i+1} /</span><h3>{title} ↗</h3><p>{text}</p></a>' for i,(title,url,text) in enumerate([('Donate','/contact/#giving','Talk with us about supporting education and digital access.'),('Volunteer','/volunteer/','Bring your interests and experience to a conversation.'),('Partner with us','/contact/#partnerships','Explore how your organisation could support the work.')]))+'</div>','soft')
    return hero()+intro+impact()+video_section()+why_uganda()+section(heading('Our focus','Three connected paths forward.',('See all our work','/our-work/'))+focus_cards(),'soft')+split('digital-workshop','Featured programme · Technology','A space to learn the skills to go further.','<p>The Giebink Technology Center at Township Primary School connects learners, teachers and community members with practical digital training.</p><p>From preparing lessons to exploring business ideas, access to technology opens new possibilities.</p>'+button('Discover the technology centre','/our-work/#technology'))+section(heading('Stories & updates','Stories & Updates',('All stories','/stories-updates/'))+story_cards(3),'soft')+section(heading('Life at EdFund','A closer look at the work.',('Explore the gallery','/gallery/'))+gallery(True))+involved+cta()

def add(route,title,description,body):
    page_title = C.get('homepage_seo_title', title+' | '+C['name']) if route == '/' else title+' | '+C['name']
    PAGES[route]=(page_title,description,body)

add('/','Education and Digital Skills in Kamuli','Discover EdFund Uganda Foundation’s work in education, digital skills and community development in Kamuli, Uganda.',home())
about=banner('About EdFund','Today’s learners. Tomorrow’s leaders.','Investing in education, technology and the people of Kamuli, Uganda.','school-courtyard')
about+=who_we_are()
about+=split('school-courtyard','Rooted in Kamuli since 2015','Built on a local commitment.','<p>Founded by John C. Giebink in 2015, EdFund grew from a commitment to restoring school infrastructure in Kamuli. Its partnership with KAPIDA connects support with local project management.</p><p>From school buildings to the Giebink Technology Center, that commitment now brings together education, digital access and community learning.</p>',True)
about+=section(heading('Why Kamuli?','Investing where it matters.')+'<div class="about-goals">'+''.join(f'<article><span>0{i+1}</span><h3>{title}</h3><p>{text}</p></article>' for i,(title,text) in enumerate([('Places to learn','Restore and build school facilities that support safe, welcoming learning environments.'),('Tools to move forward','Connect learners and educators with computers, digital training and educational resources.'),('People to make it last','Support professional development for teachers and administrators, working alongside the local community.')]))+'</div>')+impact()+cta()
add('/about-us/','About Us', 'Learn about EdFund’s mission, vision, history and local commitment to education and digital opportunity in Kamuli.',about)
work=banner('Our work','Better spaces. New possibilities.','Education, technology and community support that connect to everyday life in Kamuli.')
for name,image,title,body in [('education','school-courtyard','Spaces where learning can thrive.','EdFund supports the renovation and construction of facilities at Kamuli Township Primary School. Its work includes classroom buildings, school accommodation and shared facilities that support a safer learning environment.'),('technology','computer-learning','Technology within reach.','The Giebink Technology Center brings computer and internet access to Township Primary School. Training supports teaching, school administration and practical digital learning for young people and community members.'),('community','training-community','Learning that reaches beyond the classroom.','EdFund works with KAPIDA in Kamuli. Teacher development, community digital training and shared learning spaces connect the school’s facilities with the ambitions of the wider community.')]:
    work+=f'<div id="{name}" class="anchor-section">'+split(image,name,title,f'<p>{body}</p>'+button('Read the latest stories','/stories-updates/','outline'),name=='technology')+'</div>'
work+=section(heading('In practice','Programmes with a local purpose.')+story_cards())+cta()
add('/our-work/','Our Work','Explore school infrastructure, digital training and community support through EdFund and KAPIDA in Kamuli, Uganda.',work)
team=banner('Our team','People behind the purpose.','Meet the team connecting education, local knowledge and practical experience.')+section('<div class="team-grid">'+''.join(f'<article class="team-card">{photo(t["image"])}<div><h2>{E(t["name"])}</h2><p>{E(t["role"])}</p></div></article>' for t in C['team'])+'</div>')+split('training-community','Working together','A shared commitment to Kamuli.','<p>EdFund’s partnership with KAPIDA brings local knowledge and project management to its education and community work.</p>'+button('Connect with the team','/contact/'))+cta()
add('/our-team/','Our Team','Meet EdFund Uganda Foundation’s verified leadership team, supporting education, facilities and operations in Kamuli.',team)
add('/stories-updates/','Stories & Updates','Read real stories from EdFund’s digital training programmes and the people learning at the Giebink Technology Center.',banner('Stories & updates','Learning. Shared.','News and perspectives from EdFund’s work in Kamuli.')+section(story_cards(rows=True))+cta())
for s in C['stories']:
    article=banner(s['category'],s['short_title'],date.fromisoformat(s['date']).strftime('%d %B %Y'),s['image'])
    article+=section('<article class="article"><figure>'+photo(s['image'])+'<figcaption>EdFund programme photograph from the supplied collection; not an identification of an individual participant.</figcaption></figure>'+''.join(f'<h2>{E(p["heading"])}</h2><p>{E(p["text"])}</p>' for p in s['sections'])+f'<p class="source-note">Adapted from EdFund’s published report. <a href="{E(s["source"])}">Read the original story ↗</a></p><a class="text-link" href="/stories-updates/">← All stories & updates</a></article>')
    add('/stories-updates/'+s['slug']+'/',s['title'],s['excerpt'],article+cta())
categories=sorted(set(IMAGES[n]['category'] for n in C['gallery']))
filters='<div class="gallery-filters" aria-label="Filter gallery" hidden><button aria-pressed="true" data-filter="all">All photographs</button>'+''.join(f'<button aria-pressed="false" data-filter="{E(x)}">{E(x)}</button>' for x in categories)+'</div><p class="sr-only" role="status" id="gallery-status"></p>'
add('/gallery/','Gallery','Explore authentic photographs of EdFund’s school facilities, technology centre, training and community activities in Kamuli.',banner('Gallery','The work, in focus.','A view into learning spaces and community life in Kamuli. Select a photograph for a closer look.')+section(filters+gallery())+cta())
vol=banner('Volunteer','Start with what you can share.','Interested in supporting education and digital opportunity? Let’s talk.')+split('training-community','Your time. Your experience.','A conversation can be the first step.','<p>Tell EdFund about your skills, interests and availability. You might be interested in education, technology or supporting the organisation’s wider work.</p><p>The team can discuss whether there is a suitable way to contribute. Current roles, timings and arrangements need to be agreed directly with EdFund.</p>'+button('Enquire about volunteering','mailto:'+C['email']+'?subject=Volunteering%20with%20EdFund'))+section('<div class="intro-grid"><div>'+eyebrow('When you get in touch')+'<h2>Help us get to know you.</h2></div><div><ul class="plain-list"><li>Your interests and relevant experience</li><li>Where you are based</li><li>The time you may be able to offer</li><li>Questions you would like to discuss</li></ul><p>An enquiry starts a conversation; it does not confirm a volunteer placement.</p></div></div>','soft')+cta()
add('/volunteer/','Volunteer','Enquire about sharing your skills and time with EdFund. Discuss your interests and availability directly with the team.',vol)
contact=banner('Contact','Good things start with a conversation.','For giving, volunteering or working together, get in touch with EdFund.')+section(f'<div class="contact-grid"><div>{eyebrow("Contact EdFund")}<h2>Let’s connect.</h2><dl><dt>Email</dt><dd><a href="mailto:{C["email"]}">{C["email"]}</a></dd><dt>US telephone</dt><dd><a href="tel:{C["telephone"]}">{C["phone"]}</a></dd><dt>US mailing address</dt><dd>{C["address"]}</dd><dt>Our work</dt><dd>Kamuli, eastern Uganda</dd></dl><div class="socials">{socials()}</div></div><div class="contact-panels"><article id="giving"><h2>Support EdFund</h2><p>To discuss a donation and the available ways to give, contact EdFund directly.</p><p class="small">This website does not process payments. The email link opens your email app so you can review and send your message.</p>{button("Ask about giving","mailto:"+C["email"]+"?subject=Giving%20to%20EdFund")}</article><article id="partnerships"><h2>Partner with us</h2><p>Interested in a conversation about education, digital access or school facilities in Kamuli?</p>{button("Discuss a partnership","mailto:"+C["email"]+"?subject=Partnering%20with%20EdFund","outline")}</article></div></div>')
add('/contact/','Contact','Contact EdFund by email or US telephone for donation enquiries, volunteering and partnerships supporting education in Kamuli.',contact)
privacy=banner('Privacy policy','Your privacy matters.','How this local demonstration website handles information.')+section('<div class="article"><h2>Information on this website</h2><p>This static website does not include an enquiry form, account system, advertising analytics or tracking cookies. Photographs, fonts and scripts are hosted with the website.</p><h2>Email and telephone links</h2><p>Contact links open your email or telephone app. Nothing is sent automatically. Information you choose to send is handled by your provider and EdFund. Share only what is needed for your enquiry.</p><h2>External websites</h2><p>Story sources and social profiles lead to other websites with their own privacy practices. No social feeds or maps are embedded. Video cards load a YouTube player only when you select them, using youtube-nocookie.com. Once loaded, YouTube processes information under its own privacy policy. You can also open each video directly on YouTube.</p><h2>Hosting logs</h2><p>A hosting provider may process routine access logs, including IP addresses and requested pages, for site operation and security. The provider, retention period and any additional organisational privacy procedures must be confirmed before public launch.</p><h2>Privacy enquiries</h2><p>Contact <a href="mailto:'+C['email']+'">'+C['email']+'</a> with questions about your information.</p></div>')
add('/privacy-policy/','Privacy Policy','Read how this static EdFund demonstration handles contact links, external websites and routine hosting information.',privacy)
add('/404.html','Page Not Found','Find your way back to EdFund Uganda Foundation.',banner('Page not found','Let’s find your way back.','The page you requested could not be found.')+section(button('Return home','/')))

def render(route,title,desc,body):
    canonical=ORIGIN+route
    org={'@type':'NGO','@id':ORIGIN+'/#organisation','name':C['name'],'url':ORIGIN+'/','email':C['email'],'telephone':C['phone'],'logo':ORIGIN+C['navigation_logo']['src'],'sameAs':[s['url'] for s in C['socials']]}
    graph=[org,{'@type':'WebSite','@id':ORIGIN+'/#website','url':ORIGIN+'/','name':C['name'],'publisher':{'@id':org['@id']}},{'@type':'WebPage','@id':canonical+'#webpage','url':canonical,'name':title,'description':desc,'isPartOf':{'@id':ORIGIN+'/#website'},'inLanguage':'en'}]
    if '/stories-updates/' in route and route!='/stories-updates/':
        s=next(s for s in C['stories'] if route.endswith(s['slug']+'/'))
        graph.append({'@type':'Article','headline':s['title'],'datePublished':s['date'],'image':ORIGIN+f'/assets/images/edfund/{s["image"]}.webp','publisher':{'@id':org['@id']},'isBasedOn':s['source']})
    schema=json.dumps({'@context':'https://schema.org','@graph':graph},ensure_ascii=False).replace('<','\\u003c')
    robots='index,follow,max-image-preview:large' if C['publish_ready'] and route!='/404.html' else 'noindex,follow'
    preload = ''
    if route == '/':
        first = C['hero_slides'][0]['image']
        image = IMAGES[first]
        src = f'/assets/images/edfund/{first}.webp'
        candidates = f'/assets/images/edfund/{first}-mobile.webp {image["mobile_width"]}w, {src} {image["width"]}w' if image.get('mobile_width') else f'{src} {image["width"]}w'
        preload = f'<link rel="preload" as="image" href="{src}" imagesrcset="{candidates}" imagesizes="100vw">'

    return with_base_path(pretty_html(f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0B2B40"><title>{E(title)}</title><meta name="description" content="{E(desc)}"><meta name="robots" content="{robots}"><link rel="canonical" href="{canonical}"><meta property="og:url" content="{canonical}"><meta property="og:type" content="website"><meta property="og:site_name" content="{C['name']}"><meta property="og:title" content="{E(title)}"><meta property="og:description" content="{E(desc)}"><meta property="og:image" content="{ORIGIN}/assets/images/edfund/learning-together.webp"><meta property="og:image:alt" content="Learners and trainers working together at the technology centre"><meta name="twitter:card" content="summary_large_image"><link rel="icon" href="{E(C['favicon'])}" type="image/png"><link rel="preload" href="/assets/fonts/manrope-variable.ttf" as="font" type="font/ttf" crossorigin>{preload}<link rel="stylesheet" href="/assets/css/styles.css?v={sha256((OUT / 'assets/css/styles.css').read_bytes()).hexdigest()[:12]}"><script src="/assets/js/main.js?v={sha256((OUT / 'assets/js/main.js').read_bytes()).hexdigest()[:12]}" defer></script><script type="application/ld+json">{schema}</script></head><body id="top">{header(route)}<main id="main">{body}</main>{footer()}{photo_viewer() if 'gallery-open' in body or 'why-photo-link' in body else ''}</body></html>'''))

if __name__=='__main__':
    generated=set()
    for route,(title,desc,body) in PAGES.items():
        dest=OUT/(route.lstrip('/') if route.endswith('.html') else route.lstrip('/')+'index.html')
        dest.parent.mkdir(parents=True,exist_ok=True)
        dest.write_text(render(route,title,desc,body));generated.add(dest)
    for path in OUT.rglob('*.html'):
        if path not in generated:path.unlink()
    # Remove empty retired route directories.
    for path in sorted(OUT.rglob('*'),reverse=True):
        if path.is_dir() and not any(path.iterdir()):path.rmdir()
    (OUT/'robots.txt').write_text('User-agent: *\nAllow: /\nSitemap: '+ORIGIN+'/sitemap.xml\n')
    (OUT/'sitemap.xml').write_text('<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'+''.join(f'<url><loc>{ORIGIN+r}</loc></url>' for r in PAGES if r!='/404.html')+'</urlset>\n')
    print(f'Built {len(PAGES)} EdFund pages. '+('Production indexing enabled.' if C['publish_ready'] else 'Local demonstration: noindex enabled.'))
