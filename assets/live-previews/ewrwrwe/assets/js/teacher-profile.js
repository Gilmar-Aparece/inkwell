// Faculty profile popup — clicking any [data-person-id] (or legacy
// [data-teacher-id], which still works and is treated as role=teacher)
// element paired with data-modal-open="teacherProfileModal" fetches that
// person's public profile into #teacherProfileBody.
//
// When the clicked card sits inside a horizontally-swipeable row (a
// department's Dean + teachers — see inkwell_render_department_faculty_groups()
// on school.php / my-school.php), the popup also lets you step to the
// next/previous person in that same row: via the ‹ › buttons, the
// left/right arrow keys, or a left/right touch swipe — the same swipe
// gesture used for the tabs on account.php.
(function () {
  var body = document.getElementById('teacherProfileBody');
  if (!body) return;
  var title = document.getElementById('teacherProfileModalTitle');
  var prevBtn = document.getElementById('facultyProfilePrev');
  var nextBtn = document.getElementById('facultyProfileNext');
  var dotsWrap = document.getElementById('facultyProfileDots');
  var modal = document.getElementById('teacherProfileModal');

  var order = []; // [{id, role, name}, ...] — every card in the same swipe row as the one clicked
  var index = -1;

  function personFromEl(el) {
    var id = el.getAttribute('data-person-id') || el.getAttribute('data-teacher-id');
    if (!id) return null;
    return {
      id: id,
      role: el.getAttribute('data-person-role') || 'teacher',
      name: el.getAttribute('data-person-name') || ''
    };
  }

  function buildOrder(trigger) {
    var row = trigger.closest('.school-swipe-row');
    if (!row) return [personFromEl(trigger)].filter(Boolean);
    var cards = Array.prototype.slice.call(row.querySelectorAll('[data-person-id], [data-teacher-id]'));
    return cards.map(personFromEl).filter(Boolean);
  }

  function renderDots() {
    if (!dotsWrap) return;
    if (order.length < 2) { dotsWrap.hidden = true; dotsWrap.innerHTML = ''; return; }
    dotsWrap.hidden = false;
    dotsWrap.innerHTML = order.map(function (_, i) {
      return '<span class="faculty-profile-dot' + (i === index ? ' active' : '') + '"></span>';
    }).join('');
  }

  function updateNav() {
    var multi = order.length > 1;
    if (prevBtn) { prevBtn.hidden = !multi; prevBtn.disabled = index <= 0; }
    if (nextBtn) { nextBtn.hidden = !multi; nextBtn.disabled = index >= order.length - 1; }
    renderDots();
  }

  function load(i) {
    if (i < 0 || i >= order.length) return;
    index = i;
    var person = order[index];
    updateNav();

    if (title) title.textContent = person.role === 'dean' ? 'Dean profile' : 'Teacher profile';
    body.innerHTML = '<p class="student-profile-loading">Loading…</p>';
    fetch('/teacher-profile.php?id=' + encodeURIComponent(person.id) + '&role=' + encodeURIComponent(person.role))
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
  }

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest('[data-person-id], [data-teacher-id]');
    if (!trigger || !trigger.hasAttribute('data-modal-open')) return;

    order = buildOrder(trigger);
    var clicked = personFromEl(trigger);
    var startIndex = order.findIndex(function (p) { return clicked && p.id === clicked.id && p.role === clicked.role; });
    load(startIndex >= 0 ? startIndex : 0);
  });

  if (prevBtn) prevBtn.addEventListener('click', function () { load(index - 1); });
  if (nextBtn) nextBtn.addEventListener('click', function () { load(index + 1); });

  document.addEventListener('keydown', function (e) {
    if (!modal || !modal.classList.contains('open')) return;
    if (e.key === 'ArrowLeft') load(index - 1);
    if (e.key === 'ArrowRight') load(index + 1);
  });

  // ---- Touch swipe between profiles ----
  var startX = 0, startY = 0, dx = 0, dragging = false, lockedAxis = null;
  body.addEventListener('touchstart', function (e) {
    var t = e.touches[0];
    startX = t.clientX; startY = t.clientY; dx = 0; dragging = true; lockedAxis = null;
  }, { passive: true });
  body.addEventListener('touchmove', function (e) {
    if (!dragging) return;
    var t = e.touches[0];
    var moveX = t.clientX - startX;
    var moveY = t.clientY - startY;
    if (!lockedAxis) lockedAxis = Math.abs(moveX) > Math.abs(moveY) ? 'x' : 'y';
    if (lockedAxis === 'x') dx = moveX;
  }, { passive: true });
  body.addEventListener('touchend', function () {
    dragging = false;
    if (lockedAxis === 'x' && Math.abs(dx) > 45) {
      load(dx < 0 ? index + 1 : index - 1);
    }
    dx = 0; lockedAxis = null;
  });
})();
