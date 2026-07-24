<?php
// Inside/dashboard page — suppress the marketing topbar (see includes/header.php).
$__hideTopbar = true;
require_once __DIR__ . '/includes/auth.php';

/**
 * Static admission-requirements page. The text below is plain content —
 * edit it directly whenever the school's official policy changes, no
 * database table needed for this. If Inkwell ever needs *different*
 * requirements per school, this is the file to turn into a per-school
 * lookup (keyed by school_id) instead of one fixed page.
 */

$user = inkwell_current_user();

$pageTitle = 'Admission Requirements';
include __DIR__ . '/includes/header.php';
$driveActive = 'admission';
$driveCrumbs = [['label' => 'Home', 'href' => '/index.php'], ['label' => 'Admission Requirements']];
$driveTitle = 'Admission Requirements';
$driveSubtitle = 'Everything you need to know before enrolling — eligibility, registration rules, and the credentials you\'ll be asked to submit.';
include __DIR__ . '/includes/drive_shell_top.php';
?>

<section class="admin-card glass-card">
  <p>A student who graduates from the secondary level of education from the Department of Education shall be eligible for admission to any degree program. However, a student who has not completed the secondary level, but who has qualified in the Philippines Educational Placement Test (PEPT), may be eligible for admission.</p>
  <p>A graduate of a foreign secondary school who may not fully satisfy the specific requirement of a certain degree program may be admitted, provided that any deficiency shall be corrected during their initial school term.</p>
</section>

<section class="admin-card glass-card">
  <h2>Conditional Program</h2>
  <p>Students who failed the admission examination can still enroll in the college and the program of their choice, provided they maintain a General Weighted Average (GWA) of 1.5 for two (2) consecutive semesters (1 year).</p>
  <p>Failure to do so will result in the student being advised to shift to another non-board program of the institution. However, in the case of a pandemic or any other similar extraordinary circumstance, the student(s) may be allowed to continue with the program for as long as the following conditions are met:</p>
  <ol>
    <li>They must sign an agreement that they will not have a grade below 2.0 in any major subject they enroll in.</li>
    <li>If not complied with, they must retake the said major subject.</li>
  </ol>
</section>

<section class="admin-card glass-card">
  <h2>Right to Enroll Until Graduation</h2>
  <p>In recognition of the Constitutional guarantee of institutional academic freedom, admission to any higher education institution is open to all students not otherwise disqualified by law or by the policies and rules of the Commission on Higher Education. Except in cases of academic delinquency; violation of institutional rules and regulations; failure to settle tuition and other fees or obligations; sickness or a condition that would prevent the student from handling the normal pressures of school work, or whose continued presence would be harmful to other members of the academic community; and the closure of a program or of the institution itself — a student who qualifies for enrollment shall remain qualified to stay for the entire period expected to complete their program, without prejudice to their right to transfer to another institution within the prescribed period.</p>
</section>

<section class="admin-card glass-card">
  <h2>Rules of Registration</h2>
  <p>Enrollment/registration of a student in a higher education institution shall be held during the registration days indicated in the approved school calendar, and is subject to the rules below.</p>
  <ul>
    <li>Enrollment or registration is for the entire term (semester or trimester).</li>
    <li>A student may enroll after the registration period specified in the school calendar and be admitted under the institution's rules for late enrollment, but in no case beyond two (2) weeks after classes open. No enrollment is allowed after that.</li>
    <li>After enrollment, transferring to another institution is discouraged, especially if the student is expected to graduate that academic year — though a student may transfer during the term with the consent of both institutions.</li>
    <li>No student shall be accepted for enrollment without presenting proper school credentials on or before the end of the enrollment period.</li>
    <li>A student is deemed officially enrolled once they've submitted the appropriate admission/transfer credentials, made an initial tuition/fee payment, and have been allowed to attend classes.</li>
  </ul>
  <h3 style="margin-top:16px;">For Irregular / Graduating Students (Old Curriculum, Failing Grades, Transferees)</h3>
  <ul>
    <li>A special class may be created for students who need a subject not offered that semester, or no longer offered under a new curriculum.</li>
    <li>A special class needs a minimum of ten (10) students — otherwise, the offering is dissolved for that semester, unless the students agree to shoulder the cost of a minimum-10 special class themselves.</li>
    <li>A special enrollment form must be filled out and signed, recommended for approval by the Department Dean, and approved by the College President before submission to the registrar.</li>
  </ul>
</section>

<section class="admin-card glass-card">
  <h2>Admission Credentials</h2>
  <p>Grade 12 graduates beginning Academic Year 2017–2018 are eligible to enter college regardless of the track or strand taken in Senior High School. No Grade 12 graduate shall be denied acceptance when applying to a higher education institution's entrance examination.</p>
  <ol>
    <li>For admission into the <strong>first year</strong> of any degree program: the uncancelled report card (Form 138), or its equivalent, from the last school attended, with the eligibility certificate signed by an authorized school official. The admitting school will then request the permanent record (Form 137).</li>
    <li>For admission into <strong>second year and beyond</strong>: the prescribed transfer credential, normally a Certificate of Transfer, from the institution last attended.</li>
    <li>Where a student can't present the credentials above, a certificate issued by the Chairman of the Commission (or a duly authorized representative) will be required instead.</li>
  </ol>
  <p>No institution may officially enroll a student without the proper admission credentials — doing so may subject the institution to administrative penalties, up to revocation of its permit or recognition.</p>
</section>

<div class="subject-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
  <section class="admin-card glass-card">
    <h2>A. Non-Degree Applicants</h2>
    <ul>
      <li>Must be a high school graduate.</li>
      <li>Must present High School report card / TOR for transferees during screening.</li>
      <li>Must submit a Certificate of Good Moral Character signed by the head of the institution last attended.</li>
      <li>Must pass the written and oral examinations.</li>
      <li>Must be certified physically fit by a school or government physician before enrollment.</li>
      <li>Must comply with any other fitness requirement of the specific course.</li>
      <li>Must submit a Birth Certificate and Certificate of Good Moral Character.</li>
    </ul>
  </section>

  <section class="admin-card glass-card">
    <h2>B. Non-Degree → Baccalaureate</h2>
    <p>A non-degree student proceeding to a Baccalaureate degree must have an 80% general average in the last course attended (except Teacher Education Curriculum). Records must be submitted to the registrar for evaluation before admission.</p>
  </section>

  <section class="admin-card glass-card">
    <h2>C. Degree Applicants</h2>
    <ul>
      <li>Must be a secondary school graduate.</li>
      <li>Must have a general weighted average of 80% in all courses (85% for Teacher Education).</li>
      <li>Must pass the written and oral examination.</li>
      <li>Must submit a Birth Certificate and Certificate of Good Moral Character.</li>
      <li>Must be certified physically fit by the school physician before enrollment.</li>
      <li>Must comply with all other requirements/physical fitness requirements of the specific course.</li>
    </ul>
  </section>
</div>

<section class="admin-card glass-card">
  <h2>Credentials Checklist</h2>
  <p>No applicant — new or old student — will be allowed to register without proper credentials.</p>
  <ul>
    <li>Old students must present their school ID for validation upon enrollment.</li>
    <li>New and old high school students must present Form 138 / Report Card.</li>
    <li>First-year college students must submit an original copy of their birth certificate.</li>
    <li>College students from other schools must present Transfer Credentials: Honorable Dismissal, Transcript of Records, original Birth Certificate, and Certificate of Good Moral Character from the school last attended.</li>
    <li>Old students must submit their clearance to the Office of the Registrar before registering online.</li>
  </ul>
</section>

<section class="admin-card glass-card">
  <h2>Schedule of Fees and Payments</h2>
  <p>Compulsory collection of fees for books, manuals, modules, and the like which are not approved by the College President is strictly prohibited.</p>
</section>

<?php if ($user && $user['role'] === 'student'): ?>
  <section class="admin-card glass-card">
    <h2>Ready to enroll?</h2>
    <p class="admin-sub">Once you meet the requirements above, head over to the enrollment portal to add your subjects.</p>
    <a class="btn primary" href="/enroll.php">Go to Enrollment Portal →</a>
  </section>
<?php elseif (!$user): ?>
  <section class="admin-card glass-card">
    <h2>Ready to enroll?</h2>
    <p class="admin-sub">Create a student account, then head over to the enrollment portal to add your subjects.</p>
    <a class="btn primary" href="/register.php">Create an account →</a>
  </section>
<?php endif; ?>

<?php include __DIR__ . '/includes/drive_shell_bottom.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
