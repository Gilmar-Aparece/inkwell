/**
 * Generic search-filter + pagination component.
 *
 * Markup contract:
 *   <div class="search-filter">
 *     <input type="search" class="search-filter-input" data-filter-target="#someId" placeholder="Search...">
 *     <div class="search-filter-buttons">
 *       <button type="button" class="search-filter-btn active" data-filter-when="all">All</button>
 *       <button type="button" class="search-filter-btn" data-filter-when="now">Now</button>
 *       <button type="button" class="search-filter-btn" data-filter-when="before">Before</button>
 *     </div>
 *   </div>
 *   ... inside #someId (the "root") ...
 *   <tr data-filter-row data-filter-date="2026-07-08">...</tr>   a filterable row/card.
 *     data-filter-date is optional (ISO "YYYY-MM-DD" or any date string
 *     Date.parse understands) — only rows that have it participate in the
 *     Now / Before chip filter; rows without it always pass the chip filter.
 *   <section data-filter-group>...rows...</section>   optional: a group that
 *     hides itself once every row inside it is filtered out (grouped tables).
 *
 * Chips: "all" shows everything, "now" shows rows dated today, "before"
 * shows rows dated any day before today. Text search and the active chip
 * combine (both must match).
 *
 * Pagination (optional): add data-paginate="10" to the root element (the
 * same element referenced by data-filter-target) to slice matching rows
 * into pages of 10 with Prev/Next + page-number controls rendered right
 * after the root. Re-paginates automatically whenever the filter changes.
 *
 * No build step, no dependencies — include this file once
 * (assets/js/search-filter.js) and drop the markup above wherever a
 * list/table needs a search box, quick-filter chips, and/or pagination.
 */
(function () {
  function getRoot(group) {
    const input = group.querySelector('.search-filter-input');
    const targetSel = input ? input.getAttribute('data-filter-target') : null;
    if (targetSel) return document.querySelector(targetSel);
    return group.nextElementSibling;
  }

  function todayStr() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  function rowDateStr(row) {
    const raw = row.getAttribute('data-filter-date');
    if (!raw) return null;
    const parsed = new Date(raw);
    if (isNaN(parsed.getTime())) return null;
    return parsed.getFullYear() + '-' + String(parsed.getMonth() + 1).padStart(2, '0') + '-' + String(parsed.getDate()).padStart(2, '0');
  }

  function matchesWhen(row, when) {
    if (!when || when === 'all') return true;
    const rd = rowDateStr(row);
    if (rd === null) return true; // rows without a date opt out of time filtering
    const today = todayStr();
    if (when === 'now') return rd === today;
    if (when === 'before') return rd < today;
    return true;
  }

  /** Builds/updates the pagination controls under `root` for the given set of matching rows. */
  function paginate(root, matchingRows) {
    const pageSize = parseInt(root.getAttribute('data-paginate'), 10);
    if (!pageSize || pageSize <= 0) {
      matchingRows.forEach((row) => { row.style.display = ''; });
      return;
    }

    const totalPages = Math.max(1, Math.ceil(matchingRows.length / pageSize));
    let page = parseInt(root.getAttribute('data-filter-page') || '1', 10);
    if (page > totalPages) page = totalPages;
    if (page < 1) page = 1;
    root.setAttribute('data-filter-page', String(page));

    const start = (page - 1) * pageSize;
    const end = start + pageSize;
    matchingRows.forEach((row, i) => {
      row.style.display = (i >= start && i < end) ? '' : 'none';
    });

    let nav = root.parentElement ? root.parentElement.querySelector('[data-pagination-for="' + (root.id || '') + '"]') : null;
    if (!nav) {
      nav = document.createElement('div');
      nav.className = 'pagination';
      if (root.id) nav.setAttribute('data-pagination-for', root.id);
      root.insertAdjacentElement('afterend', nav);
    }

    if (matchingRows.length === 0) {
      nav.innerHTML = '';
      nav.style.display = 'none';
      return;
    }
    nav.style.display = totalPages <= 1 ? 'none' : 'flex';

    const rangeStart = matchingRows.length === 0 ? 0 : start + 1;
    const rangeEnd = Math.min(end, matchingRows.length);

    nav.innerHTML = '';

    const info = document.createElement('span');
    info.className = 'pagination-info';
    info.textContent = rangeStart + '–' + rangeEnd + ' of ' + matchingRows.length;
    nav.appendChild(info);

    const makeBtn = (label, targetPage, opts) => {
      opts = opts || {};
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pagination-btn' + (opts.active ? ' active' : '');
      btn.textContent = label;
      btn.disabled = !!opts.disabled;
      btn.addEventListener('click', () => {
        root.setAttribute('data-filter-page', String(targetPage));
        paginate(root, matchingRows);
      });
      return btn;
    };

    nav.appendChild(makeBtn('‹ Prev', page - 1, { disabled: page <= 1 }));

    const pageNums = document.createElement('span');
    pageNums.className = 'pagination-pages';
    const maxButtons = 5;
    let from = Math.max(1, page - Math.floor(maxButtons / 2));
    let to = Math.min(totalPages, from + maxButtons - 1);
    from = Math.max(1, Math.min(from, to - maxButtons + 1));
    for (let p = from; p <= to; p++) {
      pageNums.appendChild(makeBtn(String(p), p, { active: p === page }));
    }
    nav.appendChild(pageNums);

    nav.appendChild(makeBtn('Next ›', page + 1, { disabled: page >= totalPages }));
  }

  function applyFilter(group) {
    const root = getRoot(group);
    if (!root) return;

    const input = group.querySelector('.search-filter-input');
    const query = input ? input.value.trim().toLowerCase() : '';
    const activeChip = group.querySelector('.search-filter-btn.active');
    const when = activeChip ? activeChip.getAttribute('data-filter-when') : 'all';

    const rows = root.querySelectorAll('[data-filter-row]');
    const matchingRows = [];

    rows.forEach((row) => {
      const textMatches = query === '' || row.textContent.toLowerCase().includes(query);
      const matches = textMatches && matchesWhen(row, when);
      if (matches) matchingRows.push(row);
      else row.style.display = 'none';
    });

    // Groups (e.g. grouped "per subject" tables) hide themselves once every
    // row inside them is filtered out.
    root.querySelectorAll('[data-filter-group]').forEach((g) => {
      const groupRows = g.querySelectorAll('[data-filter-row]');
      const hasVisible = Array.from(groupRows).some((r) => matchingRows.indexOf(r) !== -1);
      g.style.display = groupRows.length === 0 || hasVisible ? '' : 'none';
    });

    const emptyMsg = root.parentElement ? root.parentElement.querySelector('[data-filter-empty]') : null;
    if (emptyMsg) emptyMsg.style.display = matchingRows.length > 0 || query === '' ? 'none' : '';

    root.setAttribute('data-filter-page', '1');
    paginate(root, matchingRows);
  }

  function init() {
    document.querySelectorAll('.search-filter').forEach((group) => {
      const input = group.querySelector('.search-filter-input');
      if (input) input.addEventListener('input', () => applyFilter(group));

      const chips = group.querySelectorAll('.search-filter-btn');
      chips.forEach((chip) => {
        chip.addEventListener('click', () => {
          chips.forEach((c) => c.classList.remove('active'));
          chip.classList.add('active');
          applyFilter(group);
        });
      });

      // Run once on load so data-paginate containers get their controls
      // even before the person types or clicks a chip.
      applyFilter(group);
    });

    // Plain pagination with no search box at all — a bare
    // <table data-paginate="10"> with [data-filter-row] rows and no
    // matching .search-filter group.
    document.querySelectorAll('[data-paginate]').forEach((root) => {
      if (root.closest('.search-filter') || document.querySelector('.search-filter [data-filter-target="#' + root.id + '"]')) return;
      const rows = Array.from(root.querySelectorAll('[data-filter-row]'));
      if (rows.length) paginate(root, rows);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
