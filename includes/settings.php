<?php
/**
 * Account Settings: dark mode (reuses the existing cookie-based theme
 * system from includes/header.php / app.js — nothing to migrate there)
 * plus privacy controls for what shows up on the public teacher/dean
 * profile popup (see teacher-profile.php).
 *
 * Self-healing schema bump (same pattern as inkwell_ensure_billing_columns()
 * in includes/billing.php) — adds the privacy column to `users` if it isn't
 * there yet. Safe to call on every request; the ALTER only runs once per
 * request lifecycle, and SHOW COLUMNS makes it a no-op after the real
 * migration has been applied. Every `users` query in this codebase already
 * uses SELECT *, so this column comes through automatically everywhere
 * without touching those queries. A standalone copy also ships as
 * MIGRATION_ADD_privacy_settings.sql for hosts where the DB user can't ALTER.
 */
function inkwell_ensure_privacy_columns() {
  static $checked = false;
  if ($checked) return;
  $checked = true;

  $pdo = inkwell_db();

  $userCols = [
    // Off by default: matches how Facebook/most social apps treat contact
    // info — visible to you in Settings, hidden from anyone browsing your
    // public profile, until you opt in.
    'show_email_public' => "ALTER TABLE users ADD COLUMN show_email_public TINYINT(1) NOT NULL DEFAULT 0",
  ];

  try {
    $existing = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($userCols as $col => $sql) {
      if (!in_array($col, $existing, true)) {
        $pdo->exec($sql);
      }
    }
  } catch (PDOException $e) {
    // Host's DB user can't run SHOW COLUMNS / ALTER — fall back to the
    // standalone migration file; reads below default show_email_public to 0.
  }
}

/**
 * Whether $viewer (possibly null/guest) is allowed to see $profileUser's
 * email address. True if the profile owner opted into public visibility,
 * or if the viewer is looking at their own profile.
 */
function inkwell_can_view_email($profileUser, $viewer) {
  if ($viewer && (int) $viewer['id'] === (int) $profileUser['id']) return true;
  return !empty($profileUser['show_email_public']);
}

/** Saves the "show my email on my public profile" toggle. */
function inkwell_update_privacy_settings($userId, $showEmailPublic) {
  inkwell_ensure_privacy_columns();
  $pdo = inkwell_db();
  try {
    $stmt = $pdo->prepare('UPDATE users SET show_email_public = ? WHERE id = ?');
    $stmt->execute([$showEmailPublic ? 1 : 0, $userId]);
    return ['ok' => true];
  } catch (PDOException $e) {
    return ['ok' => false, 'error' => 'Privacy settings need a one-time database update — run MIGRATION_ADD_privacy_settings.sql, then try again.'];
  }
}
