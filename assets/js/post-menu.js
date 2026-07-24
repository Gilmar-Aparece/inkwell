/**
 * Facebook-style post "⋯" menu — shared across every page that renders
 * post cards (posts.php, account.php's Posts tab, profile.php's Timeline).
 * Loaded once, globally, via includes/footer.php. Uses document-level
 * event delegation so it works even on pages that have no other
 * post-related JS of their own.
 *
 * Delete uses a real in-app confirmation modal (#postDeleteConfirmModal,
 * injected by includes/footer.php) instead of the browser's confirm().
 */
(function () {
  function postAjax(fields) {
    const body = new FormData();
    Object.keys(fields).forEach(function (k) { body.append(k, fields[k]); });
    return fetch('/posts.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
      .then(function (r) { return r.json(); });
  }

  function closeAllMenus(except) {
    document.querySelectorAll('[data-post-menu-dropdown]').forEach(function (dd) {
      if (dd === except) return;
      dd.hidden = true;
      const btn = dd.parentElement ? dd.parentElement.querySelector('[data-post-menu-toggle]') : null;
      if (btn) btn.setAttribute('aria-expanded', 'false');
    });
  }

  function notifyResize() {
    // Lets pages with a swipeable tab carousel (account.php) re-measure
    // panel height after a card is added/removed/resized.
    window.dispatchEvent(new Event('resize'));
  }

  function removeCard(postId, emptyMessage) {
    const card = document.getElementById('post-' + postId);
    const feed = card ? card.closest('.post-feed') : null;
    if (card) card.remove();
    if (feed && !feed.querySelector('.post-card') && !feed.querySelector('[id$="Empty"]')) {
      feed.insertAdjacentHTML('afterbegin', '<div class="admin-card glass-card post-empty" id="postFeedEmpty"><span class="icon">🖼️</span><p class="admin-sub" style="margin:0;">' + (emptyMessage || 'No posts to show.') + '</p></div>');
    }
    notifyResize();
  }

  // ---- Proper "Delete this post?" confirmation modal (replaces confirm()) ----
  let pendingDeletePostId = null;

  function openDeleteConfirm(postId) {
    pendingDeletePostId = postId;
    const modal = document.getElementById('postDeleteConfirmModal');
    if (!modal) {
      // Fallback in the unlikely event the modal markup isn't present.
      if (confirm('Delete this post permanently?')) runDelete(postId);
      return;
    }
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeDeleteConfirm() {
    const modal = document.getElementById('postDeleteConfirmModal');
    if (!modal) return;
    modal.classList.remove('open');
    if (!document.querySelector('.modal-backdrop.open')) {
      document.body.style.overflow = '';
    }
    pendingDeletePostId = null;
  }

  function runDelete(postId) {
    const confirmBtn = document.getElementById('postDeleteConfirmBtn');
    if (confirmBtn) confirmBtn.disabled = true;
    postAjax({ action: 'delete_post', post_id: postId })
      .then(function (data) {
        if (!data.ok) { alert(data.error || 'Could not delete post.'); return; }
        removeCard(postId, 'No posts to show.');
      })
      .catch(function () { alert('Network error — please try again.'); })
      .finally(function () {
        if (confirmBtn) confirmBtn.disabled = false;
        closeDeleteConfirm();
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    const confirmBtn = document.getElementById('postDeleteConfirmBtn');
    if (confirmBtn) {
      confirmBtn.addEventListener('click', function () {
        if (pendingDeletePostId) runDelete(pendingDeletePostId);
      });
    }
    // The modal's own Cancel/✕ buttons carry [data-modal-close], which
    // app.js already wires up globally — but app.js won't know to reset
    // our pending id, so clear it whenever the modal closes.
    const modal = document.getElementById('postDeleteConfirmModal');
    if (modal) {
      modal.addEventListener('click', function (e) {
        if (e.target.closest('[data-modal-close]') || e.target === modal) {
          pendingDeletePostId = null;
        }
      });
    }
  });

  document.addEventListener('click', function (e) {
    // ---- Toggle the "⋯" dropdown open/closed ----
    const toggleBtn = e.target.closest('[data-post-menu-toggle]');
    if (toggleBtn) {
      const wrap = toggleBtn.closest('[data-post-menu]');
      const dropdown = wrap ? wrap.querySelector('[data-post-menu-dropdown]') : null;
      if (!dropdown) return;
      const willOpen = dropdown.hidden;
      closeAllMenus(willOpen ? dropdown : null);
      dropdown.hidden = !willOpen;
      toggleBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      e.stopPropagation();
      return;
    }

    // ---- Delete post: open the proper confirmation modal, not confirm() ----
    const deleteBtn = e.target.closest('[data-post-delete]');
    if (deleteBtn) {
      closeAllMenus();
      openDeleteConfirm(deleteBtn.getAttribute('data-post-id'));
      return;
    }

    // ---- Hide post (from my feed only) ----
    const hideBtn = e.target.closest('[data-post-hide]');
    if (hideBtn) {
      closeAllMenus();
      const postId = hideBtn.getAttribute('data-post-id');
      postAjax({ action: 'hide_post', post_id: postId })
        .then(function (data) {
          if (!data.ok) { alert(data.error || 'Could not hide post.'); return; }
          removeCard(postId, 'No posts to show.');
        })
        .catch(function () { alert('Network error — please try again.'); });
      return;
    }

    // ---- Click outside any open menu closes it ----
    if (!e.target.closest('[data-post-menu]')) closeAllMenus();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAllMenus();
  });
})();
