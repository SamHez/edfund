'use strict';
// Visual page editor: section list, live preview and per-section settings.
// All content is plain validated text, media identifiers and links. The server revalidates everything.
(() => {
  const $ = id => document.getElementById(id);
  const root = $('editor');
  const slug = root.dataset.slug;
  const frame = $('pe-frame');
  const token = document.querySelector('input[name="csrf"]').value;
  const narrow = matchMedia('(max-width: 1100px)');
  const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)');

  let state = null;        // Server state: version, draft/published ids, content, history.
  let templates = {};
  let media = [];
  let page = { label: '', route: null };
  let selected = null;     // A section id, or 'seo' for the search & sharing settings.
  let dirty = false;
  let editSeq = 0;         // Increases with every edit so a finished save cannot hide newer typing.
  let busy = false;
  let savedAt = null;
  let mode = 'desktop';
  let patchTimer = 0;
  let previewSeq = 0;
  const pendingPatches = new Set();
  const partOpen = {};     // Remembers which parts of a section are expanded.
  const invalid = new Map();

  // ---------- small helpers ----------
  function h(tag, props = {}, ...children) {
    const node = document.createElement(tag);
    for (const [key, value] of Object.entries(props)) {
      if (value === false || value == null) continue;
      if (key === 'class') node.className = value;
      else if (key === 'text') node.textContent = value;
      else if (key.startsWith('on')) node.addEventListener(key.slice(2), value);
      else node.setAttribute(key, value === true ? '' : String(value));
    }
    node.append(...children.flat().filter(child => child != null && child !== false));
    return node;
  }
  const icon = path => {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('width', '16'); svg.setAttribute('height', '16');
    svg.setAttribute('fill', 'none'); svg.setAttribute('stroke', 'currentColor'); svg.setAttribute('stroke-width', '2');
    svg.setAttribute('stroke-linecap', 'round'); svg.setAttribute('stroke-linejoin', 'round'); svg.setAttribute('aria-hidden', 'true');
    svg.classList.add('pe-icon');
    const p = document.createElementNS('http://www.w3.org/2000/svg', 'path'); p.setAttribute('d', path); svg.append(p);
    return svg;
  };
  const ICON_UP = 'm6 15 6-6 6 6';
  const ICON_DOWN = 'm6 9 6 6 6-6';
  const ICON_CHANGE = 'M4 7h16M4 7l3-3M4 7l3 3M20 17H4m16 0-3-3m3 3-3 3';
  const sections = () => state.content.sections;
  const visibleSections = () => sections().filter(s => templates[s.template].kind !== 'seo');
  const find = id => sections().find(s => s.id === id);
  const formatTime = value => {
    const date = new Date(String(value).replace(' ', 'T') + 'Z');
    return isNaN(date) ? String(value) : date.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
  };
  const announce = message => { $('pe-announce').textContent = ''; setTimeout(() => { $('pe-announce').textContent = message; }, 30); };

  function sectionNames() {
    const totals = {}; const seen = {}; const result = {};
    visibleSections().forEach(s => { const n = templates[s.template].name; totals[n] = (totals[n] || 0) + 1; });
    visibleSections().forEach(s => {
      const n = templates[s.template].name; seen[n] = (seen[n] || 0) + 1;
      result[s.id] = totals[n] > 1 ? n + ' ' + seen[n] : n;
    });
    return result;
  }
  const nameOf = id => sectionNames()[id] || 'Section';

  async function request(body) {
    const options = body ? { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ csrf: token, data: JSON.stringify(body) }) } : {};
    const response = await fetch('/admin/content?document=' + encodeURIComponent(slug), { ...options, credentials: 'same-origin', redirect: 'error' });
    if (!response.headers.get('content-type')?.includes('application/json')) throw Object.assign(Error('The editor is unavailable or your session expired. Reload and sign in again.'), { status: response.status });
    const result = await response.json();
    if (!response.ok) throw Object.assign(Error(result.error || 'The request could not be completed. Reload and sign in if needed.'), { status: response.status });
    return result;
  }

  // ---------- feedback ----------
  function toast(message, { link, tone = 'success' } = {}) {
    const node = h('div', { class: 'pe-toast is-' + tone }, h('span', { text: message }));
    if (link) node.append(h('a', { href: link.href, target: '_blank', rel: 'noopener', text: link.text }));
    $('pe-toasts').append(node);
    setTimeout(() => node.remove(), tone === 'error' ? 9000 : 5500);
  }
  function showAlert(message, action) {
    const box = $('editor-notice');
    box.replaceChildren();
    if (!message) return;
    box.append(h('span', { text: message }));
    if (action) box.append(h('button', { type: 'button', class: 'pe-btn', text: action.label, onclick: action.run }));
    box.append(h('button', { type: 'button', class: 'pe-x', 'aria-label': 'Dismiss message', text: '×', onclick: () => showAlert('') }));
  }
  function previewNote(message) {
    const note = $('pe-hint');
    note.textContent = message || 'Click any section to edit it. Changes show here instantly and stay private until you publish.';
    note.classList.toggle('is-warning', Boolean(message));
  }
  function confirmDialog({ title, text, ok = 'Confirm', danger = false }) {
    return new Promise(resolve => {
      const dialog = $('pe-confirm');
      $('pe-confirm-title').textContent = title; $('pe-confirm-text').textContent = text;
      const okButton = $('pe-confirm-ok'); okButton.textContent = ok; okButton.classList.toggle('danger', danger);
      dialog.returnValue = '';
      const done = () => { dialog.removeEventListener('close', done); resolve(dialog.returnValue === 'ok'); };
      dialog.addEventListener('close', done);
      dialog.showModal(); $('pe-confirm-cancel').focus();
    });
  }

  // ---------- publication state ----------
  const unpublished = () => state.draft !== state.published;
  function renderState() {
    if (!state) return;
    const changes = dirty || unpublished();
    const badge = $('pe-publication');
    badge.textContent = changes ? 'Changes not published' : 'Published';
    badge.className = 'pe-badge ' + (changes ? 'is-draft' : 'is-published');
    const status = $('pe-status');
    let text; let tone;
    if (busy) { text = busy === 'publish' ? 'Publishing…' : busy === 'restore' ? 'Restoring…' : 'Saving…'; tone = 'busy'; }
    else if (dirty) { text = 'Unsaved changes'; tone = 'unsaved'; }
    else if (unpublished()) { text = 'Draft saved' + (savedAt ? ' · ' + savedAt : ''); tone = 'draft'; }
    else { text = 'All changes saved' + (savedAt ? ' · ' + savedAt : ''); tone = 'saved'; }
    status.textContent = text; status.dataset.tone = tone;
    $('pe-save').disabled = !state || Boolean(busy) || !dirty;
    $('pe-publish').disabled = !state || Boolean(busy) || !changes;
    $('pe-publish').textContent = changes ? 'Publish' : 'Published';
    $('pe-publish').title = changes ? 'Publish your changes to the live website' : 'Everything is already published';
    $('pe-view-live').href = page.route || '/';
  }
  const clock = () => new Date().toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });

  function markDirty(sectionId) {
    dirty = true; editSeq++; renderState();
    if (sectionId && sectionId !== 'seo') { pendingPatches.add(sectionId); clearTimeout(patchTimer); patchTimer = setTimeout(flushPatches, 350); }
  }

  // ---------- preview ----------
  function postToFrame(message) { try { frame.contentWindow?.postMessage(message, location.origin); } catch (_) { /* The frame is still loading. */ } }
  let pendingSlide = null;
  // Open one slideshow slide's settings and show that slide in the preview.
  function focusSlide(id, index) {
    const section = find(id); if (!section || !Number.isInteger(index) || index < 0) return;
    const groups = new Set(Object.values(templates[section.template].fields).map(f => f.group));
    groups.forEach(g => { if (/^Slide \d+$/.test(g)) partOpen[id + '|' + g] = g === 'Slide ' + (index + 1); });
    pendingSlide = index;
  }
  function sendSelection(scroll) {
    if (selected && selected !== 'seo' && find(selected)) postToFrame({ type: 'edfund:select', id: selected, name: nameOf(selected), scroll });
    else postToFrame({ type: 'edfund:clear' });
    if (pendingSlide !== null && selected && selected !== 'seo') { postToFrame({ type: 'edfund:slide', id: selected, slide: pendingSlide }); pendingSlide = null; }
  }
  async function flushPatches() {
    const ids = [...pendingPatches]; pendingPatches.clear();
    for (const id of ids) {
      if (!find(id)) continue;
      try {
        const result = await request({ action: 'section', section: id, content: state.content });
        if (result.reload) await refreshPreview(); else postToFrame({ type: 'edfund:patch', id, html: result.html });
        previewNote('');
      } catch (error) {
        previewNote('Preview paused: ' + error.message + ' Your last valid preview is still shown.');
      }
    }
  }
  async function refreshPreview({ reveal = null } = {}) {
    if (!state) return;
    const sequence = ++previewSeq;
    let scroll = 0; let tab = null;
    try { scroll = frame.contentWindow.scrollY; tab = frame.contentDocument.querySelector('.why-tabs [aria-selected="true"]')?.id; } catch (_) { /* First load. */ }
    try {
      const result = await request({ action: 'preview', content: state.content });
      if (sequence !== previewSeq) return;
      frame.onload = () => {
        try {
          if (scroll === 0 && !tab) {
            const route = document.getElementById('editor-return')?.dataset.publicRoute;
            const saved = JSON.parse(sessionStorage.getItem('edfund.browse.' + route) || 'null');
            if (saved && Date.now() - saved.time < 28800000 && /^why-tab-[0-3]$/.test(saved.tab || '')) tab = saved.tab;
          }
        } catch (_) { /* Browser storage is optional. */ }
        try { if (tab) frame.contentDocument.getElementById(tab)?.click(); if (!reveal) frame.contentWindow.scrollTo(0, scroll); } catch (_) { /* Cross-document access is best effort. */ }
        sendSelection(Boolean(reveal));
      };
      frame.src = result.url; previewNote('');
    } catch (error) {
      if (sequence === previewSeq) previewNote('Preview paused: ' + error.message + ' Your last valid preview is still shown.');
    }
  }
  function layoutStage() {
    const stage = $('pe-stage'); const width = stage.clientWidth; const height = stage.clientHeight;
    if (!width || !height) return;
    const logical = mode === 'mobile' ? 390 : 1280;
    const scale = Math.min(1, width / logical);
    $('pe-device').style.width = Math.round(logical * scale) + 'px';
    frame.style.width = logical + 'px';
    frame.style.height = Math.round(height / scale) + 'px';
    frame.style.transform = 'scale(' + scale + ')';
  }
  function setMode(value) {
    mode = value;
    $('pe-desktop').setAttribute('aria-pressed', String(value === 'desktop'));
    $('pe-mobile').setAttribute('aria-pressed', String(value === 'mobile'));
    $('pe-device').classList.toggle('is-mobile', value === 'mobile');
    layoutStage();
  }

  // ---------- section list ----------
  function canMove(index, delta) {
    const target = index + delta;
    return slug !== 'shared' && index >= 1 && target >= 1 && target < sections().length;
  }
  function renderSections() {
    const names = sectionNames(); const list = $('pe-section-list'); list.replaceChildren();
    sections().forEach((section, index) => {
      const template = templates[section.template];
      if (template.kind === 'seo') return;
      const label = names[section.id];
      const up = h('button', { type: 'button', class: 'pe-move', 'aria-label': 'Move ' + label + ' up', title: 'Move up', disabled: !canMove(index, -1), onclick: () => move(section.id, -1, 'up') }, icon(ICON_UP));
      const down = h('button', { type: 'button', class: 'pe-move', 'aria-label': 'Move ' + label + ' down', title: 'Move down', disabled: !canMove(index, 1), onclick: () => move(section.id, 1, 'down') }, icon(ICON_DOWN));
      up.dataset.dir = 'up'; down.dataset.dir = 'down';
      const row = h('li', { class: 'pe-row' + (section.id === selected ? ' is-selected' : ''), 'data-id': section.id },
        h('button', { type: 'button', class: 'pe-row-main', 'aria-current': section.id === selected ? 'true' : 'false', onclick: () => select(section.id, { scroll: true, from: 'list' }) },
          h('span', { class: 'pe-row-name', text: label }), h('span', { class: 'pe-row-type', text: template.kind_label })),
        slug === 'shared' ? null : h('span', { class: 'pe-row-move' }, up, down));
      list.append(row);
    });
    $('pe-add-section').hidden = slug === 'shared';
    $('pe-seo-row').classList.toggle('is-selected', selected === 'seo');
    $('pe-seo-open').setAttribute('aria-current', selected === 'seo' ? 'true' : 'false');
    $('pe-seo-row').closest('.pe-extra').hidden = !state.content.seo;
  }
  function select(id, { scroll = false, from = '', slide = null } = {}) {
    if (id !== 'seo' && !find(id)) return;
    selected = id;
    if (slide !== null) focusSlide(id, slide);
    const url = new URL(location.href); url.searchParams.set('section', id);
    history.replaceState(null, '', url.pathname + url.search);
    renderSections(); renderSettings(); sendSelection(scroll && id !== 'seo');
    if (narrow.matches && from) setView('settings');
  }
  function setView(view) {
    document.querySelector('.pe-body').dataset.view = view;
    document.querySelectorAll('.pe-tabs [role="tab"]').forEach(tab => tab.setAttribute('aria-selected', String(tab.dataset.view === view)));
    if (view === 'preview') requestAnimationFrame(layoutStage);
  }
  function move(id, delta, direction) {
    const list = sections(); const index = list.findIndex(s => s.id === id);
    if (!canMove(index, delta)) return;
    [list[index], list[index + delta]] = [list[index + delta], list[index]];
    markDirty(); renderSections();
    const row = document.querySelector('.pe-row[data-id="' + id + '"]');
    (row?.querySelector('.pe-move[data-dir="' + direction + '"]:not(:disabled)') || row?.querySelector('.pe-move:not(:disabled)'))?.focus();
    announce(nameOf(id) + ' moved to position ' + (visibleSections().findIndex(s => s.id === id) + 1) + ' of ' + visibleSections().length + '.');
    refreshPreview({ reveal: id });
  }
  async function removeSection(id) {
    const index = sections().findIndex(s => s.id === id);
    if (slug === 'shared' || index < 1) return;
    const name = nameOf(id);
    const ok = await confirmDialog({ title: 'Remove “' + name + '”?', text: 'It will be removed from this page when you save. You can bring it back from Revision History.', ok: 'Remove Section', danger: true });
    if (!ok) return;
    sections().splice(index, 1);
    for (const key of [...invalid.keys()]) if (key.startsWith(id + ':')) invalid.delete(key);
    markDirty(); announce(name + ' removed.');
    select(sections()[Math.max(0, index - 1)].id);
    refreshPreview({ reveal: selected });
  }
  function openAddDialog() {
    const choices = $('pe-add-choices'); choices.replaceChildren();
    Object.values(templates).filter(t => Number(t.approved) === 1).forEach(template => {
      choices.append(h('button', { type: 'button', class: 'pe-choice', onclick: () => addSection(template.id) },
        h('strong', { text: template.name }), h('span', { text: template.description || template.kind_label })));
    });
    $('pe-add-dialog').showModal();
  }
  function addSection(templateId) {
    const template = templates[templateId];
    const id = Array.from(crypto.getRandomValues(new Uint8Array(12)), n => n.toString(16).padStart(2, '0')).join('');
    const index = Math.max(1, sections().findIndex(s => s.id === selected) + 1);
    sections().splice(index, 0, { id, template: template.id, values: structuredClone(template.defaults) });
    $('pe-add-dialog').close(); markDirty();
    select(id, { from: 'list' }); refreshPreview({ reveal: id });
    announce(template.name + ' added.'); toast(template.name + ' added. Fill in its details, then save.');
  }

  // ---------- settings panel ----------
  const LINK_PATTERN = /^(?:\/(?!\/)[^\s<>"'\\]*|#[A-Za-z][\w:.-]*|https:\/\/[^\s<>"'\\]+|mailto:[^\s<>"'\\]+|tel:\+?[0-9()-]{5,25})$/;
  const linkMessage = 'Enter a page on this site (like /contact/), a full https:// address, an email link or a phone link.';
  const groupTitle = (template, group) => group === template.label ? template.name : group.replace(/ \/ /g, ' › ');
  const stripNumber = title => title.replace(/ \d+$/, '');
  const mediaItem = id => media.find(m => m.id === id);

  function renderSettings() {
    const body = $('pe-settings-body'); body.replaceChildren();
    $('pe-settings').scrollTop = 0;
    if (selected === 'seo') { renderSeo(body); return; }
    const section = find(selected);
    if (!section) { body.append(h('p', { class: 'pe-empty', text: 'Select a section from the list or click one in the preview to edit it.' })); return; }
    const template = templates[section.template];
    body.append(h('header', { class: 'pe-settings-head' }, h('p', { class: 'pe-kicker', text: 'Section Settings' }), h('h3', { id: 'pe-settings-title', tabindex: '-1', text: nameOf(section.id) }), h('p', { class: 'pe-help', text: template.kind_label })));
    const groups = [...new Set(Object.values(template.fields).map(f => f.group))];
    const small = Object.keys(template.fields).length <= 40;
    for (const group of groups) {
      const entries = Object.entries(template.fields).filter(([, f]) => f.group === group && !f.managed_post && !f.paired && !f.internal);
      const managed = Object.values(template.fields).some(f => f.group === group && f.managed_post);
      if (!entries.length && !managed) continue;
      const nodes = [];
      if (managed) nodes.push(h('p', { class: 'pe-note', text: 'Story cards in this section come from your published stories. Use Stories & Updates to change them.' }));
      const items = new Map();
      for (const [key, field] of entries) {
        const item = field.item || 1;
        if (!items.has(item)) items.set(item, { noun: field.noun || 'Item', nodes: [] });
        items.get(item).nodes.push(field.pair && !template.fields[field.pair].internal ? linkControl(section, key, field, template.fields[field.pair], field.pair) : control(section, key, field));
      }
      for (const [number, item] of items) {
        if (items.size > 1) nodes.push(h('fieldset', { class: 'pe-item' }, h('legend', { text: item.noun + ' ' + number }), item.nodes));
        else nodes.push(...item.nodes);
      }
      if (groups.length === 1) { body.append(...nodes); continue; }
      const memory = section.id + '|' + group;
      const open = memory in partOpen ? partOpen[memory] : (small || group === groups[0]);
      const part = h('details', { class: 'pe-part', ...(open ? { open: true } : {}) }, h('summary', { text: groupTitle(template, group) }), h('div', { class: 'pe-part-body' }, nodes));
      part.addEventListener('toggle', () => {
        partOpen[memory] = part.open;
        const slide = /^Slide (\d+)$/.exec(group);
        if (part.open && slide) postToFrame({ type: 'edfund:slide', id: section.id, slide: Number(slide[1]) - 1 });
      });
      body.append(part);
    }
    if (slug !== 'shared' && sections().findIndex(s => s.id === section.id) > 0) {
      body.append(h('div', { class: 'pe-danger' }, h('button', { type: 'button', class: 'pe-btn danger-outline', text: 'Remove Section', onclick: () => removeSection(section.id) })));
    }
  }

  function control(section, key, field) {
    if (field.control === 'image') return imageControl(section, key, field);
    return textControl(section, key, field);
  }
  function fieldWrap(id, title, helpText, input, extra) {
    const help = helpText ? h('p', { class: 'pe-help', id: id + '-help', text: helpText }) : null;
    if (help) input.setAttribute('aria-describedby', id + '-help');
    return h('div', { class: 'pe-field' + (input.dataset.short ? ' is-short' : '') }, h('label', { for: id, text: title }), input, help, extra);
  }
  function textControl(section, key, field, titleOverride) {
    const id = 'f-' + section.id + '-' + key;
    const value = section.values[key];
    const youtube = field.type === 'youtube';
    const shown = youtube && /^[A-Za-z0-9_-]{11}$/.test(value) ? 'https://www.youtube.com/watch?v=' + value : value;
    const multiline = field.control === 'textarea' || shown.length > 90 || shown.includes('\n');
    const input = h(multiline ? 'textarea' : 'input', { id, maxlength: youtube ? 1000 : field.max, ...(multiline ? { rows: Math.min(10, Math.max(3, Math.ceil(shown.length / 48))) } : { type: 'text' }) });
    input.value = shown;
    if (field.control === 'short') input.dataset.short = 'true';
    input.addEventListener('input', () => { section.values[key] = input.value; markDirty(section.id); });
    return fieldWrap(id, titleOverride || field.title, field.help, input);
  }
  function linkControl(section, labelKey, labelField, linkField, linkKey) {
    const id = 'f-' + section.id + '-' + linkKey;
    const message = h('p', { class: 'pe-error', id: id + '-error', role: 'alert' });
    const input = h('input', { id, type: 'text', inputmode: 'url', maxlength: linkField.max, 'aria-describedby': id + '-help ' + id + '-error' });
    input.value = section.values[linkKey];
    const check = () => {
      const bad = input.value !== '' && !LINK_PATTERN.test(input.value);
      const entry = section.id + ':' + linkKey;
      if (bad) invalid.set(entry, input.id); else invalid.delete(entry);
      input.setAttribute('aria-invalid', String(bad)); message.textContent = bad ? linkMessage : '';
    };
    input.addEventListener('input', () => { section.values[linkKey] = input.value; check(); if (!invalid.has(section.id + ':' + linkKey)) markDirty(section.id); else { dirty = true; editSeq++; renderState(); } });
    check();
    const label = textControl(section, labelKey, labelField, stripNumber(labelField.title));
    const kind = labelField.role === 'breadcrumb' ? 'Breadcrumb' : /Button/.test(labelField.title) ? 'Button' : 'Link';
    return h('fieldset', { class: 'pe-linkset' }, h('legend', { text: kind }), label,
      h('div', { class: 'pe-field' }, h('label', { for: id, text: stripNumber(linkField.title) }), input, h('p', { class: 'pe-help', id: id + '-help', text: linkField.help }), message));
  }
  function imageControl(section, key, field) {
    const current = mediaItem(section.values[key]);
    const button = h('button', { type: 'button', class: 'pe-btn', 'data-image-key': key, onclick: () => openMedia(section.values[key], picked => {
      section.values[key] = picked.id;
      // The old description no longer fits a new picture, so offer the library description.
      const names = Object.keys(templates[section.template].fields);
      const alt = names.slice(names.indexOf(key) + 1).find(k => templates[section.template].fields[k].role === 'alt');
      if (alt && picked.alt) section.values[alt] = picked.alt;
      markDirty(section.id); renderSettings();
      document.querySelector('[data-image-key="' + key + '"]')?.focus();
      if (alt && !picked.alt) toast('Image changed. Update its description below.', { tone: 'info' });
    }) }, icon(ICON_CHANGE), 'Change Image');
    return h('div', { class: 'pe-field pe-image' }, h('span', { class: 'pe-label', text: field.title }),
      h('div', { class: 'pe-image-row' }, h('img', { src: current ? (current.thumbnail || current.path) : '', alt: '', width: '96', height: '72' }),
        h('div', {}, h('p', { class: 'pe-image-name', text: current ? current.label : 'Image not found' }), button)));
  }
  function renderSeo(body) {
    const seo = state.content.seo;
    body.append(h('header', { class: 'pe-settings-head' }, h('p', { class: 'pe-kicker', text: 'Page Settings' }), h('h3', { id: 'pe-settings-title', text: 'Search & Sharing' }),
      h('p', { class: 'pe-help', text: 'How this page appears in Google results and when it is shared. Keep titles and descriptions unique for each page.' })));
    const text = (id, title, key, max, tip, multi) => {
      const input = h(multi ? 'textarea' : 'input', { id, maxlength: max, ...(multi ? { rows: '4' } : { type: 'text' }) });
      input.value = seo[key];
      input.addEventListener('input', () => { seo[key] = input.value; markDirty('seo'); });
      return fieldWrap(id, title, tip, input);
    };
    body.append(text('seo-title', 'Page Title', 'title', 250, 'Shown as the headline in search results. Around 50–60 characters works well.', false));
    body.append(text('seo-description', 'Description', 'description', 500, 'A one or two sentence summary shown below the title. Around 150 characters works well.', true));
    const current = mediaItem(seo.image);
    body.append(h('div', { class: 'pe-field pe-image' }, h('span', { class: 'pe-label', text: 'Sharing Image' }),
      h('div', { class: 'pe-image-row' }, h('img', { src: current ? (current.thumbnail || current.path) : '', alt: '', width: '96', height: '72' }),
        h('div', {}, h('p', { class: 'pe-image-name', text: current ? current.label : 'No image chosen' }),
          h('button', { type: 'button', class: 'pe-btn', 'data-image-key': 'seo', onclick: () => openMedia(seo.image, picked => { seo.image = picked.id; if (picked.alt) seo.image_alt = picked.alt; markDirty('seo'); renderSettings(); }) }, icon(ICON_CHANGE), 'Change Image')))));
    body.append(text('seo-image-alt', 'Sharing Image Description', 'image_alt', 500, 'Describe the picture for people who cannot see it.', false));
  }

  // ---------- media picker ----------
  let pickCallback = null;
  function openMedia(currentId, onPick) {
    pickCallback = onPick; $('pe-media-search').value = ''; drawMedia(currentId); $('pe-media').showModal();
    $('pe-media-search').oninput = () => drawMedia(currentId);
  }
  function drawMedia(currentId) {
    const grid = $('pe-media-grid'); grid.replaceChildren();
    const term = $('pe-media-search').value.toLowerCase();
    media.filter(m => !m.deleted_at && m.label.toLowerCase().includes(term)).forEach(m => {
      grid.append(h('button', { type: 'button', class: 'pe-media-item', 'aria-pressed': String(m.id === currentId), onclick: () => { $('pe-media').close(); pickCallback?.(m); } },
        h('img', { src: m.thumbnail || m.path, alt: '', loading: 'lazy' }), h('span', { text: m.label })));
    });
    if (!grid.children.length) grid.append(h('p', { class: 'pe-empty', text: 'No pictures match your search. Try another name or upload a new picture.' }));
  }
  $('pe-upload').addEventListener('submit', async event => {
    event.preventDefault();
    const file = $('pe-upload-file').files[0]; if (!file) return;
    const data = new FormData(); data.set('csrf', token); data.set('action', 'upload'); data.set('image', file);
    const button = event.target.querySelector('button'); button.disabled = true; $('pe-upload-status').textContent = 'Uploading…';
    try {
      const response = await fetch('/admin/media-data', { method: 'POST', body: data, redirect: 'error' });
      const result = await response.json(); if (!response.ok) throw Error(result.error || 'Upload failed.');
      media = result.media; $('pe-upload-status').textContent = ''; event.target.reset();
      const uploaded = mediaItem(result.id); $('pe-media').close(); if (uploaded) pickCallback?.(uploaded);
    } catch (error) { $('pe-upload-status').textContent = error.message; }
    finally { button.disabled = false; }
  });

  // ---------- saving, publishing, restoring ----------
  function applyServerState(next, { keepContent = false } = {}) {
    state.version = next.version; state.draft = next.draft; state.published = next.published; state.history = next.history;
    if (!keepContent) state.content = next.content;
  }
  function firstInvalid() { const first = invalid.values().next(); return first.done ? null : first.value; }
  async function commit(action) {
    if (busy || !state) return false;
    const problem = firstInvalid();
    if (problem) {
      showAlert('Some links are not valid yet. Fix the highlighted fields, then try again.');
      const section = [...invalid.keys()][0].split(':')[0]; if (section !== selected) select(section);
      document.getElementById(problem)?.focus(); return false;
    }
    if (action === 'publish') {
      const ok = await confirmDialog({ title: 'Publish “' + page.label + '”?', text: 'Your changes will go live on the website right away. The previous version stays in Revision History.', ok: 'Publish Now' });
      if (!ok) return false;
    }
    busy = action; renderState(); showAlert('');
    const sequence = editSeq;
    try {
      const result = await request({ action, version: state.version, content: structuredClone(state.content) });
      applyServerState(result.state, { keepContent: true });
      if (sequence === editSeq) dirty = false;
      savedAt = clock();
      toast(action === 'publish' ? 'Published. Your changes are now live.' : 'Draft saved. It is not on the website until you publish.', action === 'publish' && page.route ? { link: { href: page.route, text: 'View page' } } : undefined);
      return true;
    } catch (error) {
      showAlert(error.message, error.status === 409 ? { label: 'Load Newest Version', run: reloadSaved } : undefined);
      toast(action === 'publish' ? 'Not published.' : 'Draft not saved.', { tone: 'error' });
      return false;
    } finally { busy = false; renderState(); }
  }
  async function restore(revision) {
    if (busy) return;
    if (dirty) {
      const ok = await confirmDialog({ title: 'Replace your unsaved changes?', text: 'Restoring this version replaces what you are editing now. It will be saved as a new draft, not published.', ok: 'Restore as Draft' });
      if (!ok) return;
    }
    busy = 'restore'; renderState();
    try {
      const result = await request({ action: 'restore', version: state.version, revision });
      applyServerState(result.state); dirty = false; editSeq++; savedAt = clock(); invalid.clear();
      if (!selected || (selected !== 'seo' && !find(selected))) selected = visibleSections()[0].id;
      renderAll(); refreshPreview(); $('pe-history-dialog').close();
      toast('Earlier version restored as a draft. Publish it when you are ready.');
    } catch (error) { showAlert(error.message, error.status === 409 ? { label: 'Load Newest Version', run: reloadSaved } : undefined); }
    finally { busy = false; renderState(); }
  }
  async function reloadSaved() {
    if (dirty) {
      const ok = await confirmDialog({ title: 'Discard unsaved changes?', text: 'Your unsaved edits will be replaced by the newest saved version.', ok: 'Discard and Reload', danger: true });
      if (!ok) return;
    }
    await load();
  }

  // ---------- revision history ----------
  const ACTIONS = { import: 'Original website content', save: 'Draft saved', publish: 'Published', restore: 'Restored as draft' };
  function openHistory() {
    const list = $('pe-history-list'); list.replaceChildren();
    state.history.forEach(revision => {
      const live = Number(revision.id) === state.published; const current = Number(revision.id) === state.draft;
      const row = h('li', { class: 'pe-rev' + (live ? ' is-live' : ''), 'data-revision': revision.id },
        h('div', {}, h('p', { class: 'pe-rev-title' }, h('strong', { text: ACTIONS[revision.action] || revision.action }),
          live ? h('span', { class: 'pe-badge is-published', text: 'Live now' }) : null, current ? h('span', { class: 'pe-badge is-draft', text: 'Current draft' }) : null),
          h('time', { datetime: String(revision.created_at).replace(' ', 'T') + 'Z', title: revision.created_at + ' UTC', text: formatTime(revision.created_at) })),
        current ? null : h('button', { type: 'button', class: 'pe-btn', text: 'Restore as Draft', onclick: () => restore(Number(revision.id)) }));
      list.append(row);
    });
    $('pe-more').open = false; $('pe-history-dialog').showModal();
  }

  // ---------- loading ----------
  function renderAll() { renderState(); renderSections(); renderSettings(); }
  async function load() {
    busy = true; root.setAttribute('aria-busy', 'true'); $('pe-status').textContent = 'Loading…'; document.querySelector('.pe-body').inert = true;
    try {
      const result = await request();
      state = result.state; templates = result.templates; media = result.media; page = result.page;
      $('pe-page-name').textContent = page.label; dirty = false; editSeq++; invalid.clear(); showAlert('');
      const requested = new URLSearchParams(location.search).get('section');
      selected = requested === 'seo' && state.content.seo ? 'seo' : (find(requested) && templates[find(requested).template].kind !== 'seo' ? requested : (visibleSections()[0]?.id || null));
      const slideParam = Number(new URLSearchParams(location.search).get('slide'));
      if (Number.isInteger(slideParam) && slideParam >= 1 && slideParam <= 10 && selected && selected !== 'seo') focusSlide(selected, slideParam - 1);
      busy = false; renderAll(); setMode(mode);
      root.setAttribute('aria-busy', 'false'); document.querySelector('.pe-body').inert = false;
      await refreshPreview({ reveal: requested && find(requested) ? requested : null });
    } catch (error) {
      state = null; showAlert(error.message); $('pe-status').textContent = 'Unable to load page'; root.setAttribute('aria-busy', 'false');
    } finally { busy = false; document.querySelector('.pe-body').inert = !state; if (state) renderState(); }
  }

  // ---------- wiring ----------
  $('pe-save').onclick = () => commit('save');
  $('pe-publish').onclick = () => commit('publish');
  $('pe-add-section').onclick = openAddDialog;
  $('pe-seo-open').onclick = () => select('seo', { from: 'list' });
  $('pe-open-history').onclick = openHistory;
  $('pe-reload').onclick = () => { $('pe-more').open = false; reloadSaved(); };
  $('pe-close-settings').onclick = () => setView('preview');
  $('pe-desktop').onclick = () => setMode('desktop');
  $('pe-mobile').onclick = () => setMode('mobile');
  $('pe-confirm-ok').onclick = () => { $('pe-confirm').returnValue = 'ok'; $('pe-confirm').close('ok'); };
  $('pe-confirm-cancel').onclick = () => $('pe-confirm').close('cancel');
  document.querySelectorAll('dialog [data-close]').forEach(button => button.onclick = () => button.closest('dialog').close());
  document.querySelectorAll('dialog.pe-dialog').forEach(dialog => dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); }));
  document.querySelectorAll('.pe-tabs [role="tab"]').forEach(tab => tab.onclick = () => setView(tab.dataset.view));
  document.addEventListener('click', event => { const more = $('pe-more'); if (more.open && !more.contains(event.target)) more.open = false; });
  $('pe-more').addEventListener('keydown', event => { if (event.key === 'Escape') { $('pe-more').open = false; $('pe-more').querySelector('summary').focus(); } });

  $('pe-preview-tab').onclick = async () => {
    const problem = firstInvalid(); if (problem) { showAlert('Fix the highlighted links before previewing.'); return; }
    const tab = window.open('', '_blank');
    try {
      const result = await request({ action: 'preview', clean: true, content: state.content });
      if (tab) tab.location.href = result.url; else toast('Your browser blocked the preview tab. Allow pop-ups for this site and try again.', { tone: 'error' });
    } catch (error) { tab?.close(); showAlert(error.message); }
  };

  addEventListener('message', event => {
    if (event.origin !== location.origin || event.source !== frame.contentWindow || !event.data || typeof event.data !== 'object') return;
    const data = event.data;
    if (data.type === 'edfund:select' && typeof data.id === 'string' && /^[a-f0-9]{24}$/.test(data.id)) select(data.id, { from: 'preview', slide: Number.isInteger(data.slide) && data.slide >= 0 && data.slide < 10 ? data.slide : null });
    else if (data.type === 'edfund:ready') sendSelection(false);
    else if (data.type === 'edfund:patched' && data.ok === false) refreshPreview();
  });
  addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
  addEventListener('keydown', event => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') { event.preventDefault(); if (dirty) commit('save'); }
  });
  new ResizeObserver(layoutStage).observe($('pe-stage'));
  narrow.addEventListener('change', () => setView('preview'));
  setView('preview');
  load();
})();
