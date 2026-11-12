/* EdFund: small, progressively enhanced interactions. */
document.documentElement.classList.add('js');
const toggle = document.querySelector('.menu-toggle');
const nav = document.querySelector('#main-nav');
const mobile = matchMedia('(max-width: 1050px)');
function menu(open, focus = false) {
  nav.classList.toggle('is-open', open);
  toggle.setAttribute('aria-expanded', String(open));
  if (focus) toggle.focus();
}
toggle.addEventListener('click', () => menu(toggle.getAttribute('aria-expanded') !== 'true'));
nav.addEventListener('click', e => { if (e.target.closest('a')) menu(false); });
document.addEventListener('click', e => { if (!e.target.closest('.site-header')) menu(false); });
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && nav.classList.contains('is-open')) menu(false, true);
  if (e.key === 'Tab' && mobile.matches && nav.classList.contains('is-open')) {
    const items = [toggle, ...nav.querySelectorAll('a')];
    if (e.shiftKey && document.activeElement === items[0]) { e.preventDefault(); items.at(-1).focus(); }
    else if (!e.shiftKey && document.activeElement === items.at(-1)) { e.preventDefault(); items[0].focus(); }
  }
});
mobile.addEventListener('change', () => menu(false));
const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
const carousel = document.querySelector('.hero-slider');
if (carousel) {
  const slides = [...carousel.querySelectorAll('.hero-slide')];
  const dots = [...carousel.querySelectorAll('.slide-dot')];
  let index = 0, timer;
  const transition = Number(carousel.dataset.transition) || 1400;
  const leavingTimers = new Map();
  let request = 0;
  async function show(next) {
    const target = (next + slides.length) % slides.length;
    if (target === index) return;
    const ticket = ++request;
    const image = slides[target].querySelector('img');
    image.loading = 'eager';
    try { await image.decode(); } catch { return; }
    if (ticket !== request) return;
    const outgoing = slides[index];
    outgoing.classList.add('is-leaving');
    outgoing.classList.remove('is-active');
    clearTimeout(leavingTimers.get(outgoing));
    leavingTimers.set(outgoing, setTimeout(() => outgoing.classList.remove('is-leaving'), transition));
    index = target;
    const incoming = slides[index];
    clearTimeout(leavingTimers.get(incoming));
    incoming.classList.remove('is-leaving');
    void incoming.offsetWidth;
    incoming.classList.add('is-active');
    slides.forEach((slide, i) => {
      const active = i === index;
      slide.setAttribute('aria-hidden', String(!active));
      slide.inert = !active;
      dots[i].setAttribute('aria-pressed', String(active));
    });
    carousel.querySelector('.slide-counter').textContent = `${String(index + 1).padStart(2, '0')} / ${String(slides.length).padStart(2, '0')}`;
  }
  function schedule() {
    clearTimeout(timer);
    const paused = reducedMotion.matches;
    carousel.classList.toggle('is-paused', paused || document.hidden || carousel.contains(document.activeElement));
    if (paused || document.hidden || carousel.contains(document.activeElement)) return;
    timer = setTimeout(async () => { await show(index + 1); schedule(); }, Number(carousel.dataset.interval));
  }
  dots.forEach((dot, i) => dot.addEventListener('click', async () => { await show(i); schedule(); }));
  carousel.querySelector('[data-hero-prev]').addEventListener('click', async () => { await show(index - 1); schedule(); });
  carousel.querySelector('[data-hero-next]').addEventListener('click', async () => { await show(index + 1); schedule(); });
  carousel.addEventListener('focusin', schedule);
  carousel.addEventListener('focusout', () => setTimeout(schedule, 0));
  document.addEventListener('visibilitychange', schedule);
  reducedMotion.addEventListener('change', schedule);
  carousel.querySelector('.hero-controls').addEventListener('keydown', async e => {
    if (!['ArrowLeft', 'ArrowRight'].includes(e.key)) return;
    e.preventDefault();
    await show(index + (e.key === 'ArrowRight' ? 1 : -1));
    dots[index].focus();
    schedule();
  });
  const preload = () => slides.slice(1).forEach(slide => { const img = slide.querySelector('img'); img.fetchPriority = 'low'; img.loading = 'eager'; });
  if (document.readyState === 'complete') preload();
  else window.addEventListener('load', preload, {once: true});
  schedule();
}
const filters = document.querySelector('.gallery-filters');
if (filters) {
  filters.hidden = false;
  filters.addEventListener('click', e => {
    const button = e.target.closest('[data-filter]');
    if (!button) return;
    filters.querySelectorAll('button').forEach(b => b.setAttribute('aria-pressed', String(b === button)));
    document.querySelector('.gallery-mosaic').classList.toggle('is-filtered', button.dataset.filter !== 'all');
    let visible = 0;
    document.querySelectorAll('.gallery-grid figure').forEach(figure => {
      figure.hidden = button.dataset.filter !== 'all' && figure.dataset.category !== button.dataset.filter;
      if (!figure.hidden) visible++;
    });
    document.querySelector('#gallery-status').textContent = `${visible} photographs shown`;
  });
}
if ('IntersectionObserver' in window && !reducedMotion.matches) {
  const observer = new IntersectionObserver(entries => entries.forEach(entry => {
    if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); }
  }), {threshold: .04});
  document.documentElement.classList.add('reveal-ready');
  document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
}

// Local posters first; load a privacy-enhanced YouTube player on user request.
document.querySelectorAll('.video-frame').forEach(frame => {
  frame.querySelector('.video-play').addEventListener('click', event => {
    event.preventDefault();
    const player = document.createElement('iframe');
    player.src = `https://www.youtube-nocookie.com/embed/${frame.dataset.videoId}?autoplay=1&rel=0`;
    player.title = frame.dataset.videoTitle;
    player.width = '560'; player.height = '315';
    player.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
    player.allowFullscreen = true;
    player.referrerPolicy = 'strict-origin-when-cross-origin';
    frame.replaceChildren(player);
    player.focus();
  });
});

// Why Uganda: accessible chapter navigation and a gallery per chapter.
const whyStory = document.querySelector('.why-story');
if (whyStory) {
  const tablist = whyStory.querySelector('.why-tabs');
  const tabs = [...tablist.querySelectorAll('[role="tab"]')];
  const panels = [...whyStory.querySelectorAll('.why-chapter')];
  const arrows = whyStory.querySelector('.why-arrows');
  const chapterStatus = whyStory.querySelector('.why-navigation-status');
  chapterStatus.hidden = false;
  let current = 0;
  function selectChapter(index, focus = false) {
    const stage = whyStory.querySelector('.why-card-stage');
    const returnToStart = (index + tabs.length) % tabs.length !== current && stage.getBoundingClientRect().top < 110;
    current = (index + tabs.length) % tabs.length;
    tabs.forEach((tab, i) => {
      const selected = i === current;
      tab.setAttribute('aria-selected', String(selected));
      tab.tabIndex = selected ? 0 : -1;
      panels[i].hidden = !selected;
      if (selected) panels[i].querySelectorAll('img').forEach(img => { img.loading = 'eager'; });
    });
    chapterStatus.textContent = `Chapter ${String(current + 1).padStart(2, '0')} / ${String(tabs.length).padStart(2, '0')}`;
    if (focus) tabs[current].focus();
    if (returnToStart) window.scrollTo({top:stage.getBoundingClientRect().top + window.scrollY - 124, behavior:'instant'});
  }
  tablist.hidden = false;
  arrows.hidden = false;
  tabs.forEach((tab, i) => tab.addEventListener('click', () => selectChapter(i)));
  tablist.addEventListener('keydown', event => {
    let next;
    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') next = current + 1;
    if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') next = current - 1;
    if (event.key === 'Home') next = 0;
    if (event.key === 'End') next = tabs.length - 1;
    if (next !== undefined) { event.preventDefault(); selectChapter(next, true); }
  });
  whyStory.querySelector('[data-why-prev]').addEventListener('click', () => selectChapter(current - 1));
  whyStory.querySelector('[data-why-next]').addEventListener('click', () => selectChapter(current + 1));
  selectChapter(0);
}

// Modal photo browsing stays within the group that was opened.
const whyViewer = document.querySelector('#why-lightbox');
if (whyViewer && typeof whyViewer.showModal === 'function') {
  const image = whyViewer.querySelector('.why-viewer-stage img');
  const caption = whyViewer.querySelector('#why-lightbox-caption');
  const title = whyViewer.querySelector('#why-lightbox-title');
  const thumbnails = whyViewer.querySelector('.why-viewer-thumbnails');
  let group = [], photoIndex = 0, opener;
  function showPhoto(next) {
    photoIndex = (next + group.length) % group.length;
    const selected = group[photoIndex];
    const original = selected.querySelector('img');
    image.width = Number(original.getAttribute('width'));
    image.height = Number(original.getAttribute('height'));
    image.src = selected.href;
    image.alt = selected.dataset.caption;
    caption.textContent = `${photoIndex + 1} / ${group.length} · ${selected.dataset.caption}`;
    thumbnails.querySelectorAll('button').forEach((button, i) => button.setAttribute('aria-pressed', String(i === photoIndex)));
  }
  document.querySelectorAll('.why-photo-link, .gallery-open[data-caption], .kitchen-photo-link').forEach(link => link.addEventListener('click', event => {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    opener = link;
    const kitchen = link.closest('.response-project');
    const chapter = link.closest('.why-chapter');
    if (kitchen) {
      group = [...kitchen.querySelectorAll('.kitchen-photo-link')];
      title.textContent = `${kitchen.dataset.projectTitle} · Before and after`;
    } else if (chapter) {
      group = [...chapter.querySelectorAll('.why-photo-link')];
      title.textContent = document.querySelector(`#why-tab-${link.dataset.whyGallery} .why-tab-label`).textContent;
    } else {
      group = [...link.closest('.gallery-grid').querySelectorAll('figure:not([hidden]) .gallery-open[data-caption]')];
      title.textContent = 'Life at EdFund';
    }
    thumbnails.replaceChildren();
    group.forEach((item, i) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.setAttribute('aria-label', `View photograph ${i + 1}: ${item.dataset.caption}`);
      const thumb = document.createElement('img');
      thumb.src = item.querySelector('img').currentSrc || item.href;
      thumb.alt = '';
      thumb.width = 72; thumb.height = 48;
      button.append(thumb);
      button.addEventListener('click', () => showPhoto(i));
      thumbnails.append(button);
    });
    showPhoto(group.indexOf(link));
    whyViewer.showModal();
  }));
  whyViewer.querySelector('[data-why-close]').addEventListener('click', () => whyViewer.close());
  whyViewer.querySelector('[data-why-photo-prev]').addEventListener('click', () => showPhoto(photoIndex - 1));
  whyViewer.querySelector('[data-why-photo-next]').addEventListener('click', () => showPhoto(photoIndex + 1));
  whyViewer.addEventListener('keydown', event => {
    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    showPhoto(event.key === 'Home' ? 0 : event.key === 'End' ? group.length - 1 : photoIndex + (event.key === 'ArrowRight' ? 1 : -1));
  });
  whyViewer.addEventListener('close', () => opener?.focus());
  whyViewer.addEventListener('click', event => {
    if (event.target !== whyViewer) return;
    const r = whyViewer.getBoundingClientRect();
    if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) whyViewer.close();
  });
  let touchStart;
  image.addEventListener('pointerdown', event => { if (event.pointerType === 'touch') touchStart = {x:event.clientX, y:event.clientY}; });
  image.addEventListener('pointerup', event => {
    if (!touchStart || event.pointerType !== 'touch') return;
    const dx = event.clientX - touchStart.x, dy = event.clientY - touchStart.y;
    touchStart = null;
    if (Math.abs(dx) > 45 && Math.abs(dy) < 70) showPhoto(photoIndex + (dx < 0 ? 1 : -1));
  });
}
