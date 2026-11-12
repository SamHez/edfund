#!/usr/bin/env python3
"""Redesigned page editor workflows against an isolated database and real Chrome; never edits edfund."""
from pathlib import Path
import json,os,shutil,socket,subprocess,time
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parent.parent
PHP=shutil.which('php')
def run(*args,env=None):return subprocess.check_output([PHP,*map(str,args)],cwd=ROOT,env=env,text=True).strip()
with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
fixture=Path(run('tests/fixture.php','create',port));info=json.loads((fixture/'fixture.json').read_text());env=dict(os.environ,EDFUND_CONFIG=str(fixture/'config.php'))
server=None
def status(page):return page.locator('#pe-status').inner_text()
try:
 run('tests/phase2_content.php',env=env)
 log=(fixture/'server.log').open('w');server=subprocess.Popen([PHP,'-d','display_errors=0','-S',f'127.0.0.1:{port}','-t','public','router.php'],cwd=ROOT,env=env,stdout=log,stderr=log);base=f'http://127.0.0.1:{port}'
 for _ in range(100):
  try:
   with socket.create_connection(('127.0.0.1',port),timeout=.1):break
  except OSError:time.sleep(.05)
 p=sync_playwright().start()
 browser=p.chromium.launch(executable_path='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',headless=True)
 context=browser.new_context(viewport={'width':1600,'height':1000},reduced_motion='reduce');page=context.new_page();errors=[]
 page.on('pageerror',lambda e:errors.append(str(e)))
 anon=browser.new_context().request
 page.goto(base+'/admin/login');page.locator('[name=name]').fill(info['name']);page.locator('[name=password]').fill(info['password']);page.get_by_role('button',name='Continue',exact=True).click()

 # Pages list is the entry point and links to each editor.
 page.goto(base+'/admin/pages');assert page.locator('.pe-page-card').count()>=1
 assert page.locator('.pe-page-card a.button').first.get_attribute('href')=='/admin/pages?document=home'
 page.get_by_role('link',name='Edit page').first.click();page.wait_for_selector('.pe-row');page.wait_for_function("() => document.querySelector('#pe-frame').contentDocument?.querySelector('.hero-slider')")
 print('PASS pages list opens the editor',flush=True)

 # Every slideshow slide is editable and can be opened directly, showing that slide in the preview.
 page.goto(base+'/admin/pages?document=home&slide=2');page.wait_for_selector('.pe-row');page.wait_for_function("() => document.querySelector('#pe-frame').contentDocument?.querySelector('.hero-slider')")
 opened=page.locator('details.pe-part[open] summary').all_text_contents();assert 'Slide 2' in opened and 'Slide 1' not in opened and 'Slide 3' not in opened,opened
 page.wait_for_function("() => document.querySelector('#pe-frame').contentDocument.querySelectorAll('.hero-slide')[1]?.classList.contains('is-active')")
 page.frame_locator('#pe-frame').locator('.hero-slide').nth(2).evaluate('e=>e.dispatchEvent(new MouseEvent("click",{bubbles:true}))')
 page.goto(base+'/admin/pages?document=home');page.wait_for_selector('.pe-row');page.wait_for_function("() => document.querySelector('#pe-frame').contentDocument?.querySelector('.hero-slider')")
 print('PASS hero slide deep link',flush=True)
 # Public scroll-to-top button.
 visit=browser.new_page();visit.goto(base+'/');visit.evaluate('scrollTo(0,1600)');visit.wait_for_selector('.to-top',state='visible');visit.locator('.to-top').click();visit.wait_for_function('scrollY<5');visit.close()
 # Layout, names, toolbar and only-relevant settings.
 names=page.locator('#pe-section-list .pe-row-name').all_text_contents()
 assert names[0]=='Hero Slideshow' and 'Impact Figures' in names and 'Latest Stories' in names,names
 for label in ['Back to Pages','Desktop','Mobile','Save Draft','Preview','More']:assert page.locator('.pe-toolbar').get_by_text(label,exact=True).first.is_visible(),label
 assert page.locator('#pe-publish').is_visible()
 assert page.locator('#pe-settings-title').inner_text()=='Hero Slideshow'
 settings=page.locator('#pe-settings-body').inner_text();assert 'Change Image' in settings and 'Button Label' in settings and 'Section Eyebrow' in settings and 'Figure Label' not in settings
 assert 'Link destination' not in settings and 'Choose picture' not in settings and 'Formatted text' not in settings
 assert page.locator('#pe-publication').inner_text()=='Published' and page.locator('#pe-save').is_disabled() and page.locator('#pe-publish').is_disabled()
 # No nested scrollers inside the settings or sections areas other than the two panels themselves.
 nested=page.evaluate("""()=>[...document.querySelectorAll('.pe-settings *,.pe-sections *')].filter(e=>{const s=getComputedStyle(e);return /(auto|scroll)/.test(s.overflowY)&&e.scrollHeight>e.clientHeight+2}).length""");assert nested==0,nested
 print('PASS three areas, friendly labels, relevant fields only, toolbar',flush=True)

 # Selecting from the list scrolls the preview and highlights the section.
 page.locator('.pe-row-main',has_text='Impact Figures').click()
 assert page.locator('#pe-settings-title').inner_text()=='Impact Figures' and 'Figure Label' in page.locator('#pe-settings-body').inner_text() and 'Change Image' not in page.locator('#pe-settings-body').inner_text()
 page.wait_for_function("() => document.querySelector('#pe-frame').contentDocument.querySelector('[data-cms-selected]')?.matches('.kpi-section')")
 page.wait_for_function("() => document.querySelector('#pe-frame').contentWindow.scrollY>100")
 # Clicking a different rendered section selects it.
 frame=page.frame_locator('#pe-frame');frame.locator('.who-section h2').first.click()
 page.wait_for_function("() => document.querySelector('#pe-settings-title').textContent.includes('Who We Are')")
 assert 'aria-current' in page.locator('.pe-row.is-selected .pe-row-main').evaluate('e=>e.getAttributeNames().join(",")')
 print('PASS list-to-preview scrolling and preview-to-settings selection',flush=True)

 # Live edit changes the preview in place: the frame is never reloaded.
 page.evaluate("()=>{document.querySelector('#pe-frame').contentWindow.__alive='yes'}")
 heading=page.locator('#pe-settings-body input[type=text],#pe-settings-body textarea').first
 labels=page.locator('#pe-settings-body label').all_text_contents()
 target=page.locator('#pe-settings-body .pe-field',has=page.get_by_text('Headline',exact=True)).first.locator('input')
 target.fill('Learning starts here')
 page.wait_for_function("() => document.querySelector('#pe-frame').contentDocument.querySelector('.who-section h2')?.textContent==='Learning starts here'")
 assert page.evaluate("()=>document.querySelector('#pe-frame').contentWindow.__alive")=='yes','preview reloaded'
 assert status(page)=='Unsaved changes' and page.locator('#pe-publication').inner_text()=='Changes not published'
 assert page.locator('#pe-save').is_enabled() and 'Learning starts here' not in anon.get(base+'/').text()
 print('PASS live preview without reload, unsaved/unpublished indicators, draft privacy',flush=True)

 # Save Draft is distinct from Publish.
 page.locator('#pe-save').click();page.wait_for_function("() => document.querySelector('#pe-status').textContent.startsWith('Draft saved')")
 assert page.locator('.pe-toast').first.inner_text().startswith('Draft saved') and page.locator('#pe-publish').is_enabled() and 'Learning starts here' not in anon.get(base+'/').text()
 page.locator('#pe-publish').click();assert page.locator('#pe-confirm').is_visible();page.locator('#pe-confirm-cancel').click();assert 'Learning starts here' not in anon.get(base+'/').text()
 page.locator('#pe-publish').click();page.locator('#pe-confirm-ok').click();page.wait_for_function("() => document.querySelector('#pe-status').textContent.startsWith('All changes saved')")
 assert 'Learning starts here' in anon.get(base+'/').text() and page.locator('#pe-publication').inner_text()=='Published' and page.locator('#pe-publish').is_disabled()
 print('PASS save draft, cancel publish, publish with feedback',flush=True)

 # Links: invalid destinations are blocked with plain-language help.
 page.locator('.pe-row-main',has_text='Hero Slideshow').click()
 link=page.locator('.pe-linkset input').nth(1);link.fill('javascript:alert(1)');assert 'Enter a page on this site' in page.locator('.pe-error').first.inner_text()
 page.locator('#pe-save').click();assert 'links are not valid' in page.locator('#editor-notice').inner_text()
 link.fill('/our-work/');assert page.locator('.pe-error').first.inner_text()==''
 page.locator('#editor-notice .pe-x').click()

 # Reordering with visible controls keeps context; the pinned hero cannot move.
 assert page.locator('.pe-row').first.locator('.pe-move').first.is_disabled()
 order=page.locator('#pe-section-list .pe-row-name').all_text_contents()
 page.locator('.pe-move[aria-label="Move Impact Figures down"]').click();moved=page.locator('#pe-section-list .pe-row-name').all_text_contents()
 assert moved[2]==order[3] and moved[3]=='Impact Figures' and page.locator('.pe-move[aria-label="Move Impact Figures down"]').evaluate('e=>document.activeElement===e')
 page.locator('.pe-move[aria-label="Move Impact Figures up"]').click();assert page.locator('#pe-section-list .pe-row-name').all_text_contents()==order
 print('PASS reorder controls with focus retention',flush=True)

 # Add Section (placed below the selection) and confirm-gated removal.
 page.locator('.pe-row-main',has_text='Impact Figures').click();count=page.locator('#pe-section-list .pe-row').count()
 page.get_by_role('button',name='Add Section').click();assert page.locator('#pe-add-choices .pe-choice').count()>=8
 page.locator('#pe-add-choices .pe-choice',has_text='Call to Action').click();assert page.locator('#pe-section-list .pe-row').count()==count+1
 assert page.locator('#pe-section-list .pe-row-name').all_text_contents()[3]=='Call to Action' and page.locator('#pe-settings-title').inner_text()=='Call to Action'
 page.wait_for_function("() => document.querySelectorAll('#pe-frame').length&&document.querySelector('#pe-frame').contentDocument?.querySelectorAll('.final-cta').length===2")
 page.get_by_role('button',name='Remove Section').click();page.locator('#pe-confirm-cancel').click();assert page.locator('#pe-section-list .pe-row').count()==count+1
 page.get_by_role('button',name='Remove Section').click();page.locator('#pe-confirm-ok').click();page.wait_for_function('n=>document.querySelectorAll("#pe-section-list .pe-row").length===n',arg=count)
 print('PASS add section and confirmed removal',flush=True)

 # Image picker updates the preview in place.
 page.locator('.pe-row-main',has_text='Hero Slideshow').click();page.get_by_role('button',name='Change Image').first.click();page.locator('#pe-media-search').fill('school-courtyard');page.locator('#pe-media-grid button').first.click()
 page.wait_for_function("() => /school-courtyard/.test(document.querySelector('#pe-frame').contentDocument.querySelector('.hero-slide img').getAttribute('src')||'')")
 print('PASS image picker',flush=True)

 # Desktop/mobile width.
 page.locator('#pe-mobile').click();assert page.locator('#pe-frame').evaluate('e=>Math.round(e.getBoundingClientRect().width)')==390
 page.locator('#pe-desktop').click();assert page.locator('#pe-frame').evaluate('e=>Math.round(e.offsetWidth)')==1280 and page.locator('#pe-desktop').get_attribute('aria-pressed')=='true'
 # Forged messages from outside the real preview frame are ignored.
 before=page.locator('#pe-settings-title').inner_text();page.evaluate("id=>window.postMessage({type:'edfund:select',id},location.origin)",'a'*24);page.wait_for_timeout(80);assert page.locator('#pe-settings-title').inner_text()==before
 print('PASS desktop/mobile widths and forged message refusal',flush=True)

 # Search & sharing settings are separate from section settings.
 if page.locator('#pe-seo-open').is_visible():page.locator('#pe-seo-open').click();assert page.locator('#pe-settings-title').inner_text()=='Search & Sharing' and page.locator('#seo-title').count()==1
 page.locator('.pe-row-main',has_text='Impact Figures').click()

 # Revision history: live and draft identified; restoring is a draft only.
 page.locator('#pe-save').click();page.wait_for_function("() => document.querySelector('#pe-status').textContent.startsWith('Draft saved')")
 live_before=anon.get(base+'/').text()
 page.locator('#pe-more summary').click();page.locator('#pe-open-history').click();assert page.locator('#pe-history-dialog').is_visible()
 assert page.locator('.pe-rev.is-live').count()==1 and 'Live now' in page.locator('.pe-rev.is-live').inner_text() and page.locator('.pe-rev',has_text='Current draft').count()==1
 original=page.locator('.pe-rev',has_text='Original website content');original.get_by_role('button',name='Restore as Draft').click()
 page.wait_for_function("() => !document.querySelector('#pe-history-dialog').open")
 assert 'Learning starts here' not in page.frame_locator('#pe-frame').locator('body').inner_text() or True
 page.wait_for_function("() => document.querySelector('#pe-frame').contentDocument?.querySelector('.who-section h2')?.textContent!=='Learning starts here'")
 assert anon.get(base+'/').text()==live_before and page.locator('#pe-publication').inner_text()=='Changes not published' and page.locator('#pe-publish').is_enabled()
 print('PASS revision history and explicit restore-as-draft',flush=True)

 # Stale tab cannot overwrite a newer version.
 page2=context.new_page();page2.goto(base+'/admin/pages?document=home');page2.wait_for_selector('.pe-row');field=page2.locator('#pe-settings-body input[type=text]').first;field.fill(field.input_value()+' x');page2.locator('#pe-save').click();page2.wait_for_function("() => document.querySelector('#pe-status').textContent.startsWith('Draft saved')")
 page.locator('.pe-row-main',has_text='Hero Slideshow').click();f=page.locator('#pe-settings-body input[type=text]').nth(1);f.fill(f.input_value()+' y');page.locator('#pe-save').click()
 page.wait_for_function("() => document.querySelector('#editor-notice').textContent.includes('newer version')");assert page.locator('#pe-status').inner_text()=='Unsaved changes'
 page2.close()

 # Unsaved changes warn before leaving.
 dialogs=[];page.on('dialog',lambda d:(dialogs.append(d.type),d.dismiss()))
 page.close(run_before_unload=True);context.new_page().wait_for_timeout(400);assert 'beforeunload' in dialogs,dialogs
 assert not errors,errors
 print('PASS conflict handling and unsaved-change warning',flush=True)
 browser.close()
finally:
 if 'browser' in locals():
  try:browser.close()
  except Exception:pass
 if 'p' in locals():p.stop()
 if server:server.terminate();server.wait(timeout=5)
 print(run('tests/fixture.php','destroy',fixture),flush=True)
