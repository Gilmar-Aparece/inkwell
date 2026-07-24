<!-- Faculty profile popup (teacher or dean) — filled via /teacher-profile.php
     by assets/js/teacher-profile.js. When opened from a department's
     swipeable Faculty & Dean row (school.php / my-school.php), the ‹ ›
     buttons — plus left/right arrow keys and a left/right touch swipe —
     step to the next/previous person in that same row. -->
<div class="modal-backdrop" id="teacherProfileModal">
  <div class="modal">
    <div class="modal-head">
      <h2 id="teacherProfileModalTitle">Faculty profile</h2>
      <button type="button" data-modal-close aria-label="Close">✕</button>
    </div>
    <div class="faculty-profile-nav-wrap">
      <button type="button" class="faculty-profile-nav-btn faculty-profile-nav-prev" id="facultyProfilePrev" aria-label="Previous profile" hidden>‹</button>
      <div id="teacherProfileBody" class="faculty-profile-body">
        <p class="student-profile-loading">Loading…</p>
      </div>
      <button type="button" class="faculty-profile-nav-btn faculty-profile-nav-next" id="facultyProfileNext" aria-label="Next profile" hidden>›</button>
    </div>
    <div class="faculty-profile-dots" id="facultyProfileDots" hidden></div>
  </div>
</div>
