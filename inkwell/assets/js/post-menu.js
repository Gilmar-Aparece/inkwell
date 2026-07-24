/**
 * Facebook-style post "⋯" menu — shared across every page that renders
 * post cards (posts.php, account.php's Posts tab, profile.php's Timeline).
 * Loaded once, globally, via includes/footer.php. Uses document-level
 * event delegation so it works even on pages (like profile.php) that
 * have no other post-related JS of their own.
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

    // ---- Delete post ----
    const deleteBtn = e.target.closest('[data-post-delete]');
    if (deleteBtn) {
      closeAllMenus();
      if (!confirm('Delete this post permanently?')) return;
      const postId = deleteBtn.getAttribute('data-post-id');
      postAjax({ action: 'delete_post', post_id: postId })
        .then(function (data) {
          if (!data.ok) { alert(data.error || 'Could not delete post.'); return; }
          removeCard(postId, 'No posts to show.');
        })
        .catch(function () { alert('Network error — please try again.'); });
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

    // ---- Report post ----
    const reportBtn = e.target.closest('[data-post-report]');
    if (reportBtn) {
      closeAllMenus();
      const postId = reportBtn.getAttribute('data-post-id');
      const reason = prompt('What\'s wrong with this post? (optional)') || '';
      postAjax({ action: 'report_post', post_id: postId, reason: reason })
        .then(function (data) {
          if (!data.ok) { alert(data.error || 'Could not submit report.'); return; }
          removeCard(postId, 'No posts to show.');
          alert('Thanks — this post has been reported and hidden from your feed.');
        })
        .catch(function () { alert('Network error — please try again.'); });
      return;
    }

    // ---- Edit post: show the inline edit form ----
    const editBtn = e.target.closest('[data-post-edit]');
    if (editBtn) {
      closeAllMenus();
      const postId = editBtn.getAttribute('data-post-id');
      const card = document.getElementById('post-' + postId);
      if (!card) return;
      const caption = card.querySelector('[data-post-caption]');
      const form = card.querySelector('[data-post-edit-form]');
      if (caption) caption.hidden = true;
      if (form) {
        form.hidden = false;
        const textarea = form.querySelector('textarea');
        if (textarea) { textarea.focus(); textarea.selectionStart = textarea.selectionEnd = textarea.value.length; }
      }
      return;
    }

    // ---- Cancel edit ----
    const cancelBtn = e.target.closest('[data-post-edit-cancel]');
    if (cancelBtn) {
      const form = cancelBtn.closest('[data-post-edit-form]');
      const postId = form ? form.getAttribute('data-post-id') : null;
      const card = postId ? document.getElementById('post-' + postId) : null;
      const caption = card ? card.querySelector('[data-post-caption]') : null;
      if (form) form.hidden = true;
      if (caption) caption.hidden = false;
      return;
    }

    // ---- Click outside any open menu closes it ----
    if (!e.target.closest('[data-post-menu]')) closeAllMenus();
  });

  // ---- Save an edited post ----
  document.addEventListener('submit', function (e) {
    const form = e.target.closest('[data-post-edit-form]');
    if (!form) return;
    e.preventDefault();
    const postId = form.getAttribute('data-post-id');
    const textarea = form.querySelector('textarea[name="caption"]');
    const caption = textarea ? textarea.value : '';
    const saveBtn = form.querySelector('button[type="submit"]');
    if (saveBtn) saveBtn.disabled = true;

    postAjax({ action: 'edit_post', post_id: postId, caption: caption })
      .then(function (data) {
        if (!data.ok) { alert(data.error || 'Could not update post.'); return; }
        const oldCard = document.getElementById('post-' + postId);
        if (oldCard && data.html) {
          oldCard.outerHTML = data.html;
        }
        notifyResize();
      })
      .catch(function () { alert('Network error — please try again.'); })
      .finally(function () { if (saveBtn) saveBtn.disabled = false; });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAllMenus();
  });
})();
