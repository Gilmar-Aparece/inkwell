<?php
/**
 * Shared header. Expects $pageTitle to be set by the including page.
 * Reads a theme preference cookie so there's no flash of the wrong theme.
 * Defaults to dark, which is the primary surface for the current design.
 */
$initialTheme = isset($_COOKIE['inkwell_theme']) && $_COOKIE['inkwell_theme'] === 'light' ? 'light' : 'dark';
require_once __DIR__ . '/../data/lessons.php';
$__navCats = inkwell_categories();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo $initialTheme; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' · Inkwell' : 'Inkwell — Learn to code'; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,500;0,600;1,600&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
</head>
<body>
<header class="topbar">
  <a href="/index.php" style="text-decoration:none;">
    <div class="brand">
      <span class="brand-mark" aria-hidden="true"><span class="nib-dot"></span></span>
      Inkwell <small>learn by writing code</small>
    </div>
  </a>
  <div class="topbar-right">
    <nav class="topnav" id="topnav">
      <div class="topnav-links">
        <?php foreach ($__navCats as $__catKey => $__cat): ?>
          <a href="/index.php#<?php echo htmlspecialchars($__catKey); ?>"><?php echo htmlspecialchars($__cat['label']); ?></a>
        <?php endforeach; ?>
      </div>
      <a href="/playground.php" class="playground-link">&lt;/&gt; Playground</a>
    </nav>
    <button class="theme-toggle" id="themeToggle" title="Toggle dark mode" aria-label="Toggle dark mode">◐</button>
    <button class="menu-toggle" id="menuToggle" aria-label="Open menu" aria-expanded="false" aria-controls="topnav">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
  </div>
</header>
<div class="nav-backdrop" id="navBackdrop"></div>
