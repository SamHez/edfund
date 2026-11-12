'use strict';
// Behaviour for the shared owner shell on admin pages and the public website.
(() => {
  const body = document.body;
  const sidebar = document.getElementById('admin-sidebar');
  if (!sidebar) return;
  const toggle = document.getElementById('sidebar-toggle');
  const backdrop = document.getElementById('sidebar-backdrop');
  const close = document.getElementById('sidebar-close');
  const workspace = document.querySelector('.admin-workspace');
  const mobile = matchMedia('(max-width: 900px)');
  body.classList.add('admin-ready');
  const menus = () => [...document.querySelectorAll('.sh-menu-wrap, .sh-account')];
  let open = false;

  function drawer(value, focus = true) {
    open = value && mobile.matches;
    body.classList.toggle('sidebar-open', open);
    backdrop.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    sidebar.inert = mobile.matches && !open;
    if (workspace) workspace.inert = open;
    if (open) { menus().forEach(m => { m.open = false; }); (sidebar.querySelector('[aria-current="page"]') || sidebar.querySelector('.sh-link')).focus(); }
    else if (focus) toggle.focus();
  }
  toggle.onclick = () => drawer(!open);
  backdrop.onclick = () => drawer(false);
  close.onclick = () => drawer(false);
  mobile.addEventListener('change', () => drawer(false, false));
  drawer(false, false);

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      if (open) { event.preventDefault(); drawer(false); return; }
      const openMenu = menus().find(m => m.open);
      if (openMenu) { openMenu.open = false; openMenu.querySelector('summary').focus(); }
    }
    if (open && event.key === 'Tab') {
      const links = [...sidebar.querySelectorAll('a,button')].filter(el => el.getClientRects().length && !el.disabled);
      const first = links[0]; const last = links[links.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
  document.addEventListener('click', event => menus().forEach(m => { if (m.open && !m.contains(event.target)) m.open = false; }));
  menus().forEach(m => m.addEventListener('focusout', () => setTimeout(() => { if (!m.contains(document.activeElement)) m.open = false; }, 0)));
})();
