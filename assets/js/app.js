// Inkwell — global site behaviour: theme toggle (persisted via cookie so PHP
// can render the correct theme on first paint) and the mobile off-canvas
// menus (top nav drawer + lesson contents drawer), which share one backdrop.
(function () {
  const root = document.documentElement;
  const toggle = document.getElementById('themeToggle');

  function setTheme(theme) {
    root.setAttribute('data-theme', theme);
    document.cookie = 'inkwell_theme=' + theme + ';path=/;max-age=' + (60 * 60 * 24 * 365);
    if (toggle) toggle.textContent = theme === 'dark' ? '◑' : '◐';
    window.dispatchEvent(new CustomEvent('inkwell:theme', { detail: theme }));
  }

  if (toggle) {
    toggle.textContent = root.getAttribute('data-theme') === 'dark' ? '◑' : '◐';
    toggle.addEventListener('click', function () {
      const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      setTheme(next);
    });
  }

  // ---- Off-canvas drawers (mobile top nav + lesson contents sidebar) ----
  const backdrop = document.getElementById('navBackdrop');
  const topnav = document.getElementById('topnav');
  const menuToggle = document.getElementById('menuToggle');
  const sidebar = document.getElementById('sidebar');
  const sidebarClose = document.getElementById('sidebarClose');
  // The "Contents" button lives on lesson pages only; queried lazily since
  // it's inserted by lesson.php, not header.php.
  const tocToggle = document.querySelector('[data-role="toc-toggle"]');

  let openDrawer = null; // 'nav' | 'sidebar' | null

  function closeAll() {
    if (topnav) topnav.classList.remove('open');
    if (sidebar) sidebar.classList.remove('open');
    if (backdrop) backdrop.classList.remove('visible');
    if (menuToggle) menuToggle.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
    openDrawer = null;
  }

  function open(which) {
    closeAll();
    if (which === 'nav' && topnav) {
      topnav.classList.add('open');
      if (menuToggle) menuToggle.setAttribute('aria-expanded', 'true');
    } else if (which === 'sidebar' && sidebar) {
      sidebar.classList.add('open');
    } else {
      return;
    }
    if (backdrop) backdrop.classList.add('visible');
    document.body.style.overflow = 'hidden';
    openDrawer = which;
  }

  if (menuToggle) {
    menuToggle.addEventListener('click', function () {
      openDrawer === 'nav' ? closeAll() : open('nav');
    });
  }
  if (tocToggle) {
    tocToggle.addEventListener('click', function () {
      openDrawer === 'sidebar' ? closeAll() : open('sidebar');
    });
  }
  if (sidebarClose) sidebarClose.addEventListener('click', closeAll);
  if (backdrop) backdrop.addEventListener('click', closeAll);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAll();
  });
  // Collapse the drawer automatically if the viewport grows past mobile.
  window.addEventListener('resize', function () {
    if (window.innerWidth > 900) closeAll();
  });
})();
