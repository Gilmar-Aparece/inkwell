// Clicking any [data-student-id] element (paired with data-modal-open="studentProfileModal")
// fetches that student's profile and renders it into #studentProfileBody.
(function () {
  document.addEventListener('click', function (e) {
    const trigger = e.target.closest('[data-student-id]');
    if (!trigger) return;
    const body = document.getElementById('studentProfileBody');
    if (!body) return;

    body.innerHTML = '<p class="student-profile-loading">Loading…</p>';
    fetch('/student-profile.php?id=' + encodeURIComponent(trigger.getAttribute('data-student-id')))
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
