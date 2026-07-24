// Inkwell — global site behaviour: theme toggle (persisted via cookie so PHP
// can render the correct theme on first paint) and the mobile off-canvas
// menus (top nav drawer + lesson contents drawer), which share one backdrop.
(function () {
  const root = document.documentElement;
  const toggles = document.querySelectorAll('.theme-toggle');

  function setTheme(theme) {
    root.setAttribute('data-theme', theme);
    document.cookie = 'inkwell_theme=' + theme + ';path=/;max-age=' + (60 * 60 * 24 * 365);
    toggles.forEach(function (t) { t.textContent = theme === 'dark' ? '◑' : '◐'; });
    window.dispatchEvent(new CustomEvent('inkwell:theme', { detail: theme }));
  }

  if (toggles.length) {
    toggles.forEach(function (t) {
      t.textContent = root.getAttribute('data-theme') === 'dark' ? '◑' : '◐';
      t.addEventListener('click', function () {
        const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        setTheme(next);
      });
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
  const topnavDrawerClose = document.getElementById('topnavDrawerClose');
  if (topnavDrawerClose) topnavDrawerClose.addEventListener('click', closeAll);
  if (backdrop) backdrop.addEventListener('click', closeAll);
  // Tapping a lesson/playground link inside the drawer navigates, but the
  // overlay stayed open and covered the page, making it look like nothing
  // happened. Close it first so the jump/navigation is actually visible.
  if (topnav) {
    topnav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeAll);
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAll();
  });
  // Collapse the drawer automatically if the viewport grows past mobile.
  window.addEventListener('resize', function () {
    if (window.innerWidth > 900) closeAll();
  });
})();

// ---- Drive shell: mobile sidebar drawer (hamburger in .drive-topbar-row) ----
(function () {
  const sidebar = document.getElementById('driveSidebar');
  if (!sidebar) return;
  const backdrop = document.getElementById('driveSidebarBackdrop');
  const menuToggle = document.getElementById('driveMobileMenuToggle');

  function closeDrawer() {
    sidebar.classList.remove('open');
    if (backdrop) backdrop.classList.remove('visible');
    if (menuToggle) menuToggle.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }
  function openDrawer() {
    sidebar.classList.add('open');
    if (backdrop) backdrop.classList.add('visible');
    if (menuToggle) menuToggle.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }

  if (menuToggle) {
    menuToggle.addEventListener('click', function () {
      sidebar.classList.contains('open') ? closeDrawer() : openDrawer();
    });
  }
  if (backdrop) backdrop.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeDrawer();
  });
  window.addEventListener('resize', function () {
    if (window.innerWidth > 860) closeDrawer();
  });
})();

// ---- Drive shell: account dropdown (edit profile / log out) ----
(function () {
  const trigger = document.getElementById('driveUserTrigger');
  const menu = document.getElementById('driveUserMenu');
  if (!trigger || !menu) return;

  function close() {
    menu.classList.remove('open');
    trigger.setAttribute('aria-expanded', 'false');
  }
  function toggle() {
    const willOpen = !menu.classList.contains('open');
    menu.classList.toggle('open', willOpen);
    trigger.setAttribute('aria-expanded', String(willOpen));
  }

  trigger.addEventListener('click', function (e) {
    e.stopPropagation();
    toggle();
  });
  document.addEventListener('click', function (e) {
    if (!menu.contains(e.target) && e.target !== trigger && !trigger.contains(e.target)) close();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') close();
  });
})();

// ---- Top learners "+N" overflow panel (drive shell topbar) ----
(function () {
  var wrap = document.getElementById('topLearnersWrap');
  if (!wrap) return;
  var btn = document.getElementById('topLearnersMoreBtn');
  var panel = document.getElementById('topLearnersPanel');
  if (!btn || !panel) return;

  function close() {
    panel.classList.remove('open');
    btn.setAttribute('aria-expanded', 'false');
  }

  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    var willOpen = !panel.classList.contains('open');
    panel.classList.toggle('open', willOpen);
    btn.setAttribute('aria-expanded', String(willOpen));

    // Same viewport-pinning as the notification bell: below 640px the panel
    // is position:fixed via CSS, so its "top" needs computing from the
    // trigger's live position rather than the (removed) offset parent.
    if (willOpen && window.innerWidth <= 640) {
      var rect = btn.getBoundingClientRect();
      panel.style.top = Math.round(rect.bottom + 8) + 'px';
    } else if (willOpen) {
      panel.style.top = '';
    }
  });

  document.addEventListener('click', function (e) {
    if (!wrap.contains(e.target)) close();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') close();
  });
})();

// ---- Notification bell dropdown (drive shell topbar + dashboard sidebar/mobile row) ----
(function () {
  var wraps = document.querySelectorAll('.notif-bell-wrap');
  if (!wraps.length) return;

  function closeAll() {
    wraps.forEach(function (w) {
      var p = w.querySelector('.notif-bell-panel');
      var t = w.querySelector('.notif-bell-trigger');
      if (p) p.classList.remove('open');
      if (t) t.setAttribute('aria-expanded', 'false');
    });
  }

  function clearUnread(panel) {
    panel.querySelectorAll('.notif-bell-item.unread').forEach(function (a) { a.classList.remove('unread'); });
    document.querySelectorAll('.notif-bell-badge').forEach(function (b) { b.remove(); });
    var markAll = panel.querySelector('.notif-bell-markall');
    if (markAll) markAll.remove();
  }

  wraps.forEach(function (wrap) {
    var trigger = wrap.querySelector('.notif-bell-trigger');
    var panel = wrap.querySelector('.notif-bell-panel');
    if (!trigger || !panel) return;

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      var willOpen = !panel.classList.contains('open');
      closeAll();
      panel.classList.toggle('open', willOpen);
      trigger.setAttribute('aria-expanded', String(willOpen));

      // Below 640px the panel is CSS-pinned to the viewport (position:fixed,
      // left/right:12px) so it never overflows off-screen regardless of
      // where the bell sits in the row — but its "top" still needs to sit
      // just under whatever is currently the lowest sticky chrome (topbar
      // row, mobile nav row, etc). Compute that from the trigger itself
      // rather than a hardcoded value so it stays correct across pages.
      if (willOpen && window.innerWidth <= 640) {
        var rect = trigger.getBoundingClientRect();
        panel.style.top = Math.round(rect.bottom + 8) + 'px';
      } else if (willOpen) {
        panel.style.top = '';
      }

      if (willOpen) {
        var unreadIds = Array.prototype.slice.call(panel.querySelectorAll('.notif-bell-item.unread'))
          .map(function (a) { return a.getAttribute('data-notif-id'); })
          .filter(Boolean);
        if (unreadIds.length) {
          fetch('/notifications-read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'ids=' + encodeURIComponent(unreadIds.join(','))
          }).catch(function () {});
          // Reflect the read state everywhere (both dash mobile-row and
          // sidebar-head instances share the same underlying data).
          document.querySelectorAll('.notif-bell-panel').forEach(clearUnread);
        }
      }
    });

    var markAll = panel.querySelector('.notif-bell-markall');
    if (markAll) {
      markAll.addEventListener('click', function (e) {
        e.stopPropagation();
        fetch('/notifications-read.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'all=1'
        }).catch(function () {});
        document.querySelectorAll('.notif-bell-panel').forEach(clearUnread);
      });
    }
  });

  document.addEventListener('click', function (e) {
    wraps.forEach(function (w) {
      if (!w.contains(e.target)) {
        var p = w.querySelector('.notif-bell-panel');
        var t = w.querySelector('.notif-bell-trigger');
        if (p) p.classList.remove('open');
        if (t) t.setAttribute('aria-expanded', 'false');
      }
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAll();
  });
})();

// ---- Dashboard mobile drawer (admin/dean/registrar/teacher nav) ----
(function () {
  const sidebar = document.getElementById('dashSidebar');
  if (!sidebar) return;
  const backdrop = document.getElementById('dashSidebarBackdrop');
  const menuToggle = document.getElementById('dashMobileMenuToggle');

  function closeDrawer() {
    sidebar.classList.remove('open');
    if (backdrop) backdrop.classList.remove('visible');
    if (menuToggle) menuToggle.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }
  function openDrawer() {
    sidebar.classList.add('open');
    if (backdrop) backdrop.classList.add('visible');
    if (menuToggle) menuToggle.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  }

  if (menuToggle) {
    menuToggle.addEventListener('click', function () {
      sidebar.classList.contains('open') ? closeDrawer() : openDrawer();
    });
  }
  if (backdrop) backdrop.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeDrawer();
  });
  window.addEventListener('resize', function () {
    if (window.innerWidth > 899) closeDrawer();
  });
  sidebar.querySelectorAll('a').forEach(function (link) {
    link.addEventListener('click', closeDrawer);
  });
})();

// ---- Sticky mobile header: reveal the Inkwell logo once the page scrolls ----
(function () {
  let ticking = false;
  function update() {
    document.documentElement.classList.toggle('is-scrolled', window.scrollY > 24);
    ticking = false;
  }
  window.addEventListener('scroll', function () {
    if (!ticking) {
      window.requestAnimationFrame(update);
      ticking = true;
    }
  }, { passive: true });
  update();
})();

// ---- Reusable modal component ----
// Open:  <button data-modal-open="modalId">...</button>
// Modal: <div class="modal-backdrop" id="modalId"><div class="modal">...
//          <button data-modal-close>...</button> ... </div></div>
// Any element inside a modal with [data-modal-close] closes it; clicking the
// backdrop itself (not its content) and pressing Escape also close it.
(function () {
  function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('open');
    document.body.style.overflow = 'hidden';
    const firstField = el.querySelector('input, textarea, select');
    if (firstField) setTimeout(function () { firstField.focus(); }, 50);
  }

  function closeModal(el) {
    el.classList.remove('open');
    if (!document.querySelector('.modal-backdrop.open')) {
      document.body.style.overflow = '';
    }
  }

  document.addEventListener('click', function (e) {
    const opener = e.target.closest('[data-modal-open]');
    if (opener) {
      openModal(opener.getAttribute('data-modal-open'));
      return;
    }
    const closer = e.target.closest('[data-modal-close]');
    if (closer) {
      const backdrop = closer.closest('.modal-backdrop');
      if (backdrop) closeModal(backdrop);
      return;
    }
    if (e.target.classList && e.target.classList.contains('modal-backdrop')) {
      closeModal(e.target);
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.modal-backdrop.open').forEach(closeModal);
  });
})();

// ---- Reusable panel-tab switcher ----
// <div data-role="...-tabs"><button data-tab="a" class="drive-tab active">…</button>…</div>
// <div data-panel="a" class="drive-activity-panel active">…</div><div data-panel="b" …>…</div>
// The tab buttons and their panels just need to share a common ancestor.
(function () {
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-tab]');
    if (!btn) return;
    const tabId = btn.getAttribute('data-tab');
    const group = btn.closest('.drive-activity') || document;

    group.querySelectorAll('[data-tab]').forEach(function (b) { b.classList.toggle('active', b === btn); });
    group.querySelectorAll('[data-panel]').forEach(function (p) {
      p.classList.toggle('active', p.getAttribute('data-panel') === tabId);
    });
  });
})();

// ---- Sidebar nav sub-groups (e.g. Exams > Self-study / Official) ----
// A caret button next to a nav link expands/collapses a small list of
// sub-links right underneath it.
(function () {
  document.addEventListener('click', function (e) {
    const caret = e.target.closest('[data-nav-toggle]');
    if (!caret) return;
    e.preventDefault();
    const sub = document.getElementById(caret.getAttribute('data-nav-toggle'));
    if (!sub) return;
    const willOpen = sub.hasAttribute('hidden');
    if (willOpen) {
      sub.removeAttribute('hidden');
    } else {
      sub.setAttribute('hidden', '');
    }
    caret.classList.toggle('open', willOpen);
    caret.setAttribute('aria-expanded', String(willOpen));
  });
})();

// ---- Event copy-link (public feed, teacher/dean own-event lists) ----
// <input id="event-link-ID" readonly value="…url…"><button data-copy-event-link data-event-id="ID">
// Delegated globally (not per-page) since events.php, teacher/events.php,
// and dean/events.php all render the same copy-link row.
(function () {
  function flashCopied(btn) {
    if (!btn) return;
    const original = btn.textContent;
    btn.textContent = 'Copied!';
    btn.classList.add('copied-flash');
    setTimeout(function () {
      btn.textContent = original;
      btn.classList.remove('copied-flash');
    }, 1400);
  }

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-copy-event-link]');
    if (!btn) return;
    const eventId = btn.getAttribute('data-event-id');
    const input = document.getElementById('event-link-' + eventId);
    const url = input ? input.value : (window.location.origin + '/events.php#event-' + eventId);
    const done = function () { flashCopied(btn); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(done).catch(function () {
        if (input) { input.select(); document.execCommand('copy'); done(); }
      });
    } else if (input) {
      input.select();
      document.execCommand('copy');
      done();
    }
  });
})();

// Homepage lesson list: swipeable-by-department tabs (#deptTabs / #deptSwipe
// in index.php). Tapping a tab scrolls the panel row; swiping the row by
// hand keeps the matching tab highlighted; and a URL hash either jumps
// straight to a department (#course-bshm) or, for an individual track
// anchor coming from the nav drawer (#html), finds which department panel
// it lives in, switches to it, then scrolls down to that specific card.
(function () {
  const tabs = document.getElementById('deptTabs');
  const swipe = document.getElementById('deptSwipe');
  if (!tabs || !swipe) return;

  const tabButtons = Array.prototype.slice.call(tabs.querySelectorAll('.dept-tab'));
  const panels = Array.prototype.slice.call(swipe.querySelectorAll('.dept-panel'));

  function setActiveTab(id) {
    tabButtons.forEach(function (btn) {
      btn.classList.toggle('active', btn.getAttribute('data-dept-target') === id);
    });
  }

  function goToPanel(id, behavior) {
    const panel = document.getElementById(id);
    if (!panel || panel.parentNode !== swipe) return;
    swipe.scrollTo({ left: panel.offsetLeft - swipe.offsetLeft, behavior: behavior || 'smooth' });
    setActiveTab(id);
  }

  tabButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      goToPanel(btn.getAttribute('data-dept-target'));
    });
  });

  // Keep the active tab in sync while the person swipes/drags by hand.
  let scrollTimer = null;
  swipe.addEventListener('scroll', function () {
    if (scrollTimer) clearTimeout(scrollTimer);
    scrollTimer = setTimeout(function () {
      let closest = null;
      let closestDist = Infinity;
      panels.forEach(function (p) {
        const dist = Math.abs((p.offsetLeft - swipe.offsetLeft) - swipe.scrollLeft);
        if (dist < closestDist) { closestDist = dist; closest = p; }
      });
      if (closest) setActiveTab(closest.id);
    }, 100);
  }, { passive: true });

  function handleHash() {
    const hash = window.location.hash.replace('#', '');
    if (!hash) return false;
    const target = document.getElementById(hash);
    if (!target) return false;
    if (target.classList.contains('dept-panel')) {
      goToPanel(hash, 'auto');
      return true;
    }
    const panel = target.closest('.dept-panel');
    if (panel) {
      goToPanel(panel.id, 'auto');
      setTimeout(function () {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }, 200);
      return true;
    }
    return false;
  }

  if (!handleHash()) {
    const defaultId = swipe.getAttribute('data-default');
    if (defaultId) goToPanel(defaultId, 'auto');
  }
  window.addEventListener('hashchange', handleHash);
})();
