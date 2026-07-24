<?php
/**
 * Shared header. Expects $pageTitle to be set by the including page.
 * Reads a theme preference cookie so there's no flash of the wrong theme.
 * Defaults to dark, which is the primary surface for the current design.
 */
$initialTheme = isset($_COOKIE['inkwell_theme']) && $_COOKIE['inkwell_theme'] === 'dark' ? 'dark' : 'light';
require_once __DIR__ . '/../data/lessons.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/billing.php';
$__navCats = inkwell_categories();
$__navCatsByCourse = inkwell_categories_by_course();
$__navUser = inkwell_current_user();
$__renewalNotice = $__navUser ? inkwell_renewal_notice($__navUser) : null;
$__showTopbar = empty($__hideTopbar);
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $initialTheme; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' · Inkwell' : 'Inkwell — Learn to code'; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
</head>
<body>
<canvas id="inkParticles" class="ink-particles" aria-hidden="true"></canvas>
<?php if ($__showTopbar): ?>
<header class="topbar">
  <a href="/index.php" style="text-decoration:none;">
    <div class="brand">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M18.5 2.5c1 1 1.1 2.6.2 3.7L9.8 16.9l-4.3 1.1 1.1-4.3L16.5 3c1.1-1 2.7-1.1 3.7-.2z" fill="#fff"/>
          <path d="M5.5 18l-2 3.5 3.5-2-1.5-1.5z" fill="#fff" opacity="0.55"/>
        </svg>
        <span class="nib-dot"></span>
      </span>
      Inkwell <small>learn by writing code</small>
    </div>
  </a>
  <div class="topbar-right">
    <nav class="topnav" id="topnav">
      <div class="topnav-drawer-head">
        <div class="brand brand-sm">
          <span class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M18.5 2.5c1 1 1.1 2.6.2 3.7L9.8 16.9l-4.3 1.1 1.1-4.3L16.5 3c1.1-1 2.7-1.1 3.7-.2z" fill="#fff"/>
              <path d="M5.5 18l-2 3.5 3.5-2-1.5-1.5z" fill="#fff" opacity="0.55"/>
            </svg>
            <span class="nib-dot"></span>
          </span>
          Inkwell
        </div>
        <button type="button" class="topnav-drawer-close" id="topnavDrawerClose" aria-label="Close menu">✕</button>
      </div>

  

      <a href="/index.php" class="playground-link playground-link-outline">📚 Lessons</a>
      <a href="/playground.php" class="playground-link">&lt;/&gt; Open playground</a>
    </nav>
    <button class="theme-toggle" id="themeToggle" title="Toggle dark mode" aria-label="Toggle dark mode">◐</button>
    <button class="menu-toggle" id="menuToggle" aria-label="Open menu" aria-expanded="false" aria-controls="topnav">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
  </div>
</header>
<div class="nav-backdrop" id="navBackdrop"></div>
<?php endif; ?>
<?php if ($__renewalNotice): ?>
  <div class="renewal-banner renewal-banner-<?php echo htmlspecialchars($__renewalNotice['level']); ?>">
    <span class="renewal-banner-msg"><?php echo htmlspecialchars($__renewalNotice['message']); ?></span>
    <a href="/my-billing.php" class="renewal-banner-link">Manage billing →</a>
  </div>
<?php endif; ?>
