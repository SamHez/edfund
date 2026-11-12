'use strict';
// No content or commands cross this channel: only stable selection identifiers.
document.addEventListener('click', event => {
  const section = event.target.closest('[data-cms-section]');
  if (section && event.isTrusted) {
    window.parent.postMessage({type:'edfund:select', id:section.dataset.cmsSection}, window.location.origin);
    document.querySelectorAll('[data-cms-selected]').forEach(el => el.removeAttribute('data-cms-selected'));
    section.setAttribute('data-cms-selected','true');
  }
  const link = event.target.closest('a');
  if (link && !link.matches('.why-photo-link,.gallery-open,.kitchen-photo-link,.team-profile-open')) event.preventDefault();
});
document.addEventListener('submit', event => event.preventDefault());
