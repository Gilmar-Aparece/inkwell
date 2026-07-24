// Clicking any [data-post-user-id] element (paired with data-modal-open="postAuthorProfileModal")
// fetches that user's public profile + recent posts and renders it into #postAuthorProfileBody.
(function () {
  document.addEventListener('click', function (e) {
    const trigger = e.target.closest('[data-post-user-id]');
    if (!trigger) return;
    const body = document.getElementById('postAuthorProfileBody');
    if (!body) return;

    body.innerHTML = '<p class="student-profile-loading">Loading…</p>';
    fetch('/user-profile.php?id=' + encodeURIComponent(trigger.getAttribute('data-post-user-id')))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok) {
          body.innerHTML = data.html;
        } else {
          body.innerHTML = '<p class="student-profile-loading">' + (data.error || 'Could not load this profile.') + '</p>';
        }
      })
      .catch(function () {
        body.innerHTML = '<p class="student-profile-loading">Could not load this profile.</p>';
      });
  });
})();
