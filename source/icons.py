paths = {
 'chevron':'<path d="m6 9 6 6 6-6"/>',
 'shield':'<path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6z"/><path d="m8 12 3 3 5-6"/>',
 'people':'<circle cx="9" cy="7" r="3"/><path d="M3 21v-4a6 6 0 0 1 12 0v4M16 4a3 3 0 0 1 0 6M18 14a5 5 0 0 1 3 5v2"/>',
 'clipboard':'<rect x="5" y="5" width="14" height="17" rx="2"/><rect x="9" y="2" width="6" height="5" rx="1"/><path d="m8 14 2 2 5-5M9 19h6"/>',
 'phone':'<path d="M5 3h4l2 5-3 2a14 14 0 0 0 6 6l2-3 5 2v4c0 2-2 3-4 2C9 20 4 15 3 7c0-2 0-4 2-4z"/>',
 'pin':'<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
 'mail':'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
 'building':'<path d="M4 22V5l10-3v20M14 9h6v13M1 22h22M8 7h2M8 11h2M8 15h2M8 19h2M17 12h1M17 16h1"/>',
 'leaf':'<path d="M4 20C0 6 14 3 21 3c0 14-5 19-13 15M3 22 16 9"/>',
}
def icon(name):
 return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+paths.get(name,paths['shield'])+'</svg>'
