'use strict';
// Live search over the Pages list.
(() => {
  const input = document.getElementById('pe-page-search');
  const empty = document.getElementById('pe-page-empty');
  if (!input) return;
  input.addEventListener('input', () => {
    const term = input.value.trim().toLowerCase();
    let shown = 0;
    document.querySelectorAll('#pe-page-grid .pe-page-card').forEach(card => {
      const match = card.dataset.search.includes(term);
      card.hidden = !match;
      if (match) shown++;
    });
    empty.hidden = shown > 0;
  });
})();
