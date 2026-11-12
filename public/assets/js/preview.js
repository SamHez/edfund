'use strict';
// Runs only inside the private editor preview. Messages carry section identifiers, a display
// name and server-rendered section markup; nothing is evaluated and only a fixed set of
// content attributes is ever copied into the page.
(() => {
  const parentWindow = window.parent;
  const SECTION_ID = /^[a-f0-9]{24}$/;
  const COPIED_ATTRIBUTES = ['src', 'srcset', 'width', 'height', 'alt', 'href', 'data-caption', 'data-video-id', 'data-video-title'];
  const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)');
  let selectedId = null;
  let selectedName = '';
  let tag = null;

  const sectionNodes = id => SECTION_ID.test(id) ? [...document.querySelectorAll('[data-cms-section="' + id + '"]')] : [];

  function ensureTag() {
    if (tag) return tag;
    tag = document.createElement('div');
    tag.setAttribute('aria-hidden', 'true');
    tag.style.cssText = 'position:fixed;z-index:2147483000;pointer-events:none;background:#0d6a60;color:#fff;font:700 12px/1 Manrope,Arial,sans-serif;padding:7px 11px;border-radius:0 0 6px 0;letter-spacing:.02em;display:none;max-width:70vw;white-space:nowrap;overflow:hidden;text-overflow:ellipsis';
    document.body.append(tag);
    return tag;
  }

  function placeTag() {
    const label = ensureTag();
    const nodes = sectionNodes(selectedId);
    if (!nodes.length) { label.style.display = 'none'; return; }
    const first = nodes[0].getBoundingClientRect();
    const last = nodes[nodes.length - 1].getBoundingClientRect();
    const visible = last.bottom > 0 && first.top < innerHeight;
    label.style.display = visible ? 'block' : 'none';
    label.textContent = selectedName;
    label.style.left = Math.max(first.left, 0) + 'px';
    label.style.top = Math.max(first.top, 0) + 'px';
  }

  let frame = 0;
  const schedulePlacement = () => { cancelAnimationFrame(frame); frame = requestAnimationFrame(placeTag); };

  function mark(id, name) {
    document.querySelectorAll('[data-cms-selected]').forEach(el => el.removeAttribute('data-cms-selected'));
    selectedId = SECTION_ID.test(id) ? id : null;
    selectedName = typeof name === 'string' ? name.slice(0, 80) : '';
    sectionNodes(selectedId).forEach(el => el.setAttribute('data-cms-selected', 'true'));
    schedulePlacement();
  }

  function reveal(id) {
    const node = sectionNodes(id)[0];
    if (!node) return;
    const header = document.querySelector('.site-header');
    const offset = (header && getComputedStyle(header).position === 'sticky' ? header.offsetHeight : 0) + 12;
    scrollTo({ top: Math.max(0, node.getBoundingClientRect().top + scrollY - offset), behavior: reduceMotion.matches ? 'auto' : 'smooth' });
  }

  // Copy only the differences, so slideshow position, tabs and other live interface state survive edits.
  function sync(current, next) {
    if (current.nodeType !== next.nodeType || current.nodeName !== next.nodeName) {
      current.replaceWith(document.importNode(next, true));
      return;
    }
    if (current.nodeType === Node.TEXT_NODE) {
      if (current.nodeValue !== next.nodeValue) current.nodeValue = next.nodeValue;
      return;
    }
    if (current.nodeType !== Node.ELEMENT_NODE) return;
    for (const name of COPIED_ATTRIBUTES) {
      const value = next.getAttribute(name);
      if (value === null) { if (current.hasAttribute(name)) current.removeAttribute(name); }
      else if (current.getAttribute(name) !== value) current.setAttribute(name, value);
    }
    const currentChildren = [...current.childNodes];
    const nextChildren = [...next.childNodes];
    if (currentChildren.length !== nextChildren.length) {
      current.replaceWith(document.importNode(next, true));
      return;
    }
    currentChildren.forEach((child, index) => sync(child, nextChildren[index]));
  }

  function patch(id, html) {
    const nodes = sectionNodes(id);
    const next = [...new DOMParser().parseFromString(html, 'text/html').body.children];
    if (!nodes.length || nodes.length !== next.length) return false;
    nodes.forEach((node, index) => sync(node, next[index]));
    // Replaced elements lose the marker, so apply it again.
    sectionNodes(id).forEach(el => el.setAttribute('data-cms-section', id));
    if (id === selectedId) mark(id, selectedName);
    return true;
  }

  document.addEventListener('click', event => {
    const section = event.target.closest('[data-cms-section]');
    if (section && event.isTrusted) {
      const message = { type: 'edfund:select', id: section.dataset.cmsSection };
      const slide = event.target.closest('.hero-slide');
      if (slide) message.slide = [...slide.parentNode.children].filter(el => el.classList.contains('hero-slide')).indexOf(slide);
      parentWindow.postMessage(message, location.origin);
    }
    const link = event.target.closest('a');
    if (link && !link.matches('.why-photo-link,.gallery-open,.kitchen-photo-link,.team-profile-open')) event.preventDefault();
  });
  document.addEventListener('submit', event => event.preventDefault());
  addEventListener('scroll', schedulePlacement, { passive: true });
  addEventListener('resize', schedulePlacement);

  addEventListener('message', event => {
    if (event.source !== parentWindow || event.origin !== location.origin) return;
    const data = event.data;
    if (data && typeof data === 'object' && data.type === 'edfund:clear') { mark(null, ''); return; }
    if (!data || typeof data !== 'object' || typeof data.id !== 'string' || !SECTION_ID.test(data.id)) return;
    if (data.type === 'edfund:select') {
      mark(data.id, data.name);
      if (data.scroll === true) reveal(data.id);
    } else if (data.type === 'edfund:slide' && Number.isInteger(data.slide) && data.slide >= 0 && data.slide < 10) {
      document.querySelector('.slide-dot[data-slide="' + data.slide + '"]')?.click();
    } else if (data.type === 'edfund:patch' && typeof data.html === 'string' && data.html.length < 400000) {
      const ok = patch(data.id, data.html);
      parentWindow.postMessage({ type: 'edfund:patched', id: data.id, ok }, location.origin);
    }
  });
  parentWindow.postMessage({ type: 'edfund:ready' }, location.origin);
})();
