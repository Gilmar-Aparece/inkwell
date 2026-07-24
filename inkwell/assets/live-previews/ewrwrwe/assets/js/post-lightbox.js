/**
 * Global fullscreen photo lightbox — delegated, so it works for any photo
 * anywhere in the document (community feed, shared/embedded posts, the
 * author-profile popup, and the school-page post carousels), including
 * ones inserted into the DOM after this script loads (AJAX post cards,
 * live feed polling, etc).
 *
 * Any element with [data-lightbox-src] opens the viewer on click. If its
 * closest [data-lightbox-full] ancestor carries a JSON array of image
 * entries (a multi-photo gallery), prev/next navigation cycles through
 * the whole set; otherwise it's a single-image view with no arrows.
 *
 * Facebook-style per-photo engagement: each entry in that JSON array can
 * carry {src, id, likeCount, liked, commentCount} — when an entry has a
 * real id (came from the post_images gallery table) and the wrapper
 * isn't marked data-lightbox-readonly, a like button + comment thread
 * scoped to THAT picture (not the post as a whole) is shown under it.
 */
(function () {
  let overlay, imgEl, counterEl, prevBtn, nextBtn, stageEl, engagementEl;
  let likeBtn, likeCountEl, commentToggleBtn, commentCountEl;
  let commentsPanel, commentsList, commentForm, commentInput;
  let currentList = [];
  let currentIndex = 0;
  let currentReadonly = false;
  const loadedComments = {}; // imageId -> true once we've fetched its comment thread this page load

  function postAjax(fields) {
    const body = new FormData();
    Object.keys(fields).forEach(function (k) { body.append(k, fields[k]); });
    return fetch('/posts.php', {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: body,
    }).then(function (r) { return r.json(); });
  }

  function build() {
    if (overlay) return;
    overlay = document.createElement('div');
    overlay.className = 'post-lightbox-overlay';
    overlay.innerHTML =
      '<button type="button" class="post-lightbox-close" aria-label="Close">✕</button>' +
      '<button type="button" class="post-lightbox-prev" aria-label="Previous">‹</button>' +
      '<div class="post-lightbox-stage">' +
      '  <img alt="">' +
      '  <div class="post-lightbox-engagement" hidden>' +
      '    <div class="post-lightbox-eng-bar">' +
      '      <button type="button" class="post-lightbox-eng-btn post-lightbox-like-btn"><span class="heart">♡</span><span class="post-lightbox-like-count">0</span></button>' +
      '      <button type="button" class="post-lightbox-eng-btn post-lightbox-comment-toggle">💬 <span class="post-lightbox-comment-count">0</span></button>' +
      '    </div>' +
      '    <div class="post-lightbox-comments-panel" hidden>' +
      '      <div class="post-lightbox-comments-list"></div>' +
      '      <form class="post-lightbox-comment-form">' +
      '        <input type="text" maxlength="500" placeholder="Write a comment…" required>' +
      '        <button type="submit" class="post-lightbox-comment-send" title="Send">➤</button>' +
      '      </form>' +
      '    </div>' +
      '  </div>' +
      '</div>' +
      '<button type="button" class="post-lightbox-next" aria-label="Next">›</button>' +
      '<span class="post-lightbox-counter"></span>';
    document.body.appendChild(overlay);

    stageEl = overlay.querySelector('.post-lightbox-stage');
    imgEl = overlay.querySelector('img');
    counterEl = overlay.querySelector('.post-lightbox-counter');
    prevBtn = overlay.querySelector('.post-lightbox-prev');
    nextBtn = overlay.querySelector('.post-lightbox-next');
    engagementEl = overlay.querySelector('.post-lightbox-engagement');
    likeBtn = overlay.querySelector('.post-lightbox-like-btn');
    likeCountEl = overlay.querySelector('.post-lightbox-like-count');
    commentToggleBtn = overlay.querySelector('.post-lightbox-comment-toggle');
    commentCountEl = overlay.querySelector('.post-lightbox-comment-count');
    commentsPanel = overlay.querySelector('.post-lightbox-comments-panel');
    commentsList = overlay.querySelector('.post-lightbox-comments-list');
    commentForm = overlay.querySelector('.post-lightbox-comment-form');
    commentInput = commentForm.querySelector('input');

    overlay.querySelector('.post-lightbox-close').addEventListener('click', close);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
    prevBtn.addEventListener('click', function (e) { e.stopPropagation(); show(currentIndex - 1); });
    nextBtn.addEventListener('click', function (e) { e.stopPropagation(); show(currentIndex + 1); });
    document.addEventListener('keydown', function (e) {
      if (!overlay.classList.contains('open')) return;
      if (e.key === 'Escape') close();
      if (e.key === 'ArrowLeft') show(currentIndex - 1);
      if (e.key === 'ArrowRight') show(currentIndex + 1);
    });

    likeBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      const item = currentList[currentIndex];
      if (!item || !item.id) return;
      postAjax({ action: 'toggle_image_like', image_id: item.id }).then(function (res) {
        if (!res.ok) return;
        item.liked = res.liked;
        item.likeCount = res.count;
        renderLikeState(item);
      });
    });

    commentToggleBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      const item = currentList[currentIndex];
      if (!item || !item.id) return;
      const isHidden = commentsPanel.hasAttribute('hidden');
      if (isHidden) {
        commentsPanel.removeAttribute('hidden');
        if (!loadedComments[item.id]) loadImageComments(item.id);
      } else {
        commentsPanel.setAttribute('hidden', '');
      }
    });

    commentsList.addEventListener('click', function (e) {
      const delBtn = e.target.closest('[data-image-comment-delete]');
      if (!delBtn) return;
      const commentId = delBtn.getAttribute('data-comment-id');
      const imageId = delBtn.getAttribute('data-image-id');
      postAjax({ action: 'delete_image_comment', comment_id: commentId, image_id: imageId }).then(function (res) {
        if (!res.ok) return;
        const node = document.getElementById('img-comment-' + commentId);
        if (node) node.remove();
        const item = currentList.find(function (it) { return it.id == imageId; });
        if (item) { item.commentCount = res.count; if (currentList[currentIndex] === item) renderCommentCount(item); }
      });
    });

    commentForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const item = currentList[currentIndex];
      const text = commentInput.value.trim();
      if (!item || !item.id || !text) return;
      postAjax({ action: 'add_image_comment', image_id: item.id, comment: text }).then(function (res) {
        if (!res.ok) return;
        removeEmptyNote();
        commentsList.insertAdjacentHTML('beforeend', res.html);
        commentsList.scrollTop = commentsList.scrollHeight;
        commentInput.value = '';
        item.commentCount = res.count;
        renderCommentCount(item);
      });
    });
  }

  function removeEmptyNote() {
    const note = commentsList.querySelector('.post-lightbox-comments-empty');
    if (note) note.remove();
  }

  function loadImageComments(imageId) {
    commentsList.innerHTML = '<p class="post-lightbox-comments-empty">Loading comments…</p>';
    postAjax({ action: 'get_image_comments', image_id: imageId }).then(function (res) {
      if (!res.ok) return;
      loadedComments[imageId] = true;
      commentsList.innerHTML = res.html || '<p class="post-lightbox-comments-empty">No comments yet — be the first.</p>';
    });
  }

  function renderLikeState(item) {
    likeBtn.classList.toggle('liked', !!item.liked);
    likeBtn.querySelector('.heart').textContent = item.liked ? '♥' : '♡';
    likeCountEl.textContent = item.likeCount || 0;
  }

  function renderCommentCount(item) {
    commentCountEl.textContent = item.commentCount || 0;
  }

  function updateEngagementPanel() {
    const item = currentList[currentIndex];
    commentsPanel.setAttribute('hidden', '');
    if (currentReadonly || !item || !item.id) {
      engagementEl.setAttribute('hidden', '');
      return;
    }
    engagementEl.removeAttribute('hidden');
    renderLikeState(item);
    renderCommentCount(item);
    commentsList.innerHTML = '';
  }

  function show(index) {
    if (!currentList.length) return;
    currentIndex = (index + currentList.length) % currentList.length;
    const item = currentList[currentIndex];
    imgEl.src = typeof item === 'string' ? item : item.src;
    const multi = currentList.length > 1;
    prevBtn.style.display = multi ? 'flex' : 'none';
    nextBtn.style.display = multi ? 'flex' : 'none';
    counterEl.style.display = multi ? 'block' : 'none';
    if (multi) counterEl.textContent = (currentIndex + 1) + ' / ' + currentList.length;
    updateEngagementPanel();
  }

  function open(list, index, readonly) {
    build();
    currentList = list;
    currentReadonly = !!readonly;
    show(index);
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function close() {
    if (!overlay) return;
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  document.addEventListener('click', function (e) {
    const trigger = e.target.closest('[data-lightbox-src]');
    if (!trigger) return;
    e.preventDefault();

    const wrap = trigger.closest('[data-lightbox-full]');
    let list, index, readonly = false;
    if (wrap) {
      try { list = JSON.parse(wrap.getAttribute('data-lightbox-full')); } catch (err) { list = null; }
      readonly = wrap.hasAttribute('data-lightbox-readonly');
    }
    if (list && list.length) {
      index = parseInt(trigger.getAttribute('data-lightbox-index'), 10) || 0;
    } else {
      list = [{ src: trigger.getAttribute('data-lightbox-src'), id: null }];
      index = 0;
    }
    open(list, index, readonly);
  });
})();
