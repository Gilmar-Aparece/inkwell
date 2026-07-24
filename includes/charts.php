<?php
/**
 * Tiny dependency-free chart helpers. Renders plain inline SVG server-side
 * so the admin dashboard doesn't need a JS charting library or CDN — just
 * PHP building an SVG string. Values use CSS custom properties for color
 * (var(--nib) etc.) so bars/text follow the site's light/dark theme.
 */

/**
 * Vertical bar chart, e.g. monthly revenue trend.
 * $series = ['Jan' => 1200.0, 'Feb' => 3400.0, ...] (ordered).
 */
function inkwell_svg_bar_chart(array $series, array $opts = []) {
  $width = $opts['width'] ?? 640;
  $height = $opts['height'] ?? 220;
  $color = $opts['color'] ?? 'var(--nib)';
  $prefix = $opts['prefix'] ?? '';
  $n = count($series);
  if ($n === 0) return '<p class="admin-sub">No data yet.</p>';

  $padTop = 26; $padBottom = 24; $padSide = 10;
  $chartW = $width - $padSide * 2;
  $chartH = $height - $padTop - $padBottom;
  $max = max(array_merge(array_values($series), [1]));
  $gap = 14;
  $barW = max(6, ($chartW - $gap * ($n - 1)) / $n);

  $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" class="chart-svg" role="img" aria-label="Bar chart" preserveAspectRatio="xMidYMid meet">';
  $i = 0;
  foreach ($series as $label => $value) {
    $barH = $max > 0 ? ($value / $max) * $chartH : 0;
    $barH = max($barH, $value > 0 ? 3 : 0);
    $x = $padSide + $i * ($barW + $gap);
    $y = $padTop + ($chartH - $barH);
    $svg .= sprintf(
      '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="4" fill="%s"><title>%s: %s%s</title></rect>',
      $x, $y, $barW, $barH, $color, htmlspecialchars((string) $label), htmlspecialchars($prefix), number_format($value, 0)
    );
    if ($value > 0) {
      $svg .= sprintf(
        '<text x="%.1f" y="%.1f" font-size="10" text-anchor="middle" fill="var(--ink-dim)">%s%s</text>',
        $x + $barW / 2, max(12, $y - 6), htmlspecialchars($prefix), number_format($value, 0)
      );
    }
    $svg .= sprintf(
      '<text x="%.1f" y="%.1f" font-size="10.5" text-anchor="middle" fill="var(--ink-dim)">%s</text>',
      $x + $barW / 2, $height - 6, htmlspecialchars((string) $label)
    );
    $i++;
  }
  $svg .= '</svg>';
  return $svg;
}

/**
 * Horizontal proportional bars for a category breakdown, e.g. plans or
 * payment methods. $rows = [['label' => 'Pro Learner', 'value' => 42], ...].
 * Plain HTML/CSS (not SVG) — renders as a simple ranked bar list.
 */
function inkwell_hbar_list(array $rows, array $opts = []) {
  if (!$rows) return '<p class="admin-sub">No data yet.</p>';
  $prefix = $opts['prefix'] ?? '';
  $suffix = $opts['suffix'] ?? '';
  $color = $opts['color'] ?? 'var(--nib)';
  $decimals = $opts['decimals'] ?? 0;
  $max = max(array_map(fn($r) => (float) $r['value'], $rows));
  $max = $max > 0 ? $max : 1;

  $html = '<div class="hbar-list">';
  foreach ($rows as $r) {
    $pct = max(4, round(((float) $r['value'] / $max) * 100));
    $html .= '<div class="hbar-row">'
      . '<span class="hbar-label">' . htmlspecialchars($r['label']) . '</span>'
      . '<span class="hbar-track"><span class="hbar-fill" style="width:' . $pct . '%;background:' . $color . ';"></span></span>'
      . '<span class="hbar-value">' . htmlspecialchars($prefix) . number_format((float) $r['value'], $decimals) . htmlspecialchars($suffix) . '</span>'
      . '</div>';
  }
  $html .= '</div>';
  return $html;
}
