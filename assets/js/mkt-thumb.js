/**
 * Marketplace card live-preview thumbnails (see inkwell_marketplace_thumb_html()
 * in includes/marketplace.php) embed the seller's actual static ZIP export,
 * shrunk into a little browser-chrome frame. If that preview can't render —
 * a stale entry point, a missing file, a host blocking it — the iframe used
 * to just sit there blank and white behind the chrome dots forever. This
 * detects that and swaps in the category icon instead, same as the "no
 * preview at all" case already looks.
 *
 * Note: a blocked/missing page (HTTP 403/404) still "loads" successfully as
 * far as the browser is concerned — the iframe's error event only fires for
 * network-level failures — so we also inspect the loaded document itself.
 */
(function () {
  function showFallback(embed) {
    const parent = embed.parentElement;
    if (!parent) return;
    const icon = embed.getAttribute('data-fallback-icon') || '💻';
    embed.remove();
    if (parent.querySelector('.mkt-card-thumb-fallback')) return;
    const span = document.createElement('span');
    span.className = 'mkt-card-thumb-fallback';
    span.textContent = icon;
    parent.appendChild(span);
  }

  function looksBlank(doc) {
    if (!doc || !doc.body) return true;
    const text = (doc.body.innerText || '').trim();
    const title = doc.title || '';
    if (/forbidden|not found|error/i.test(title) && text.length < 400) return true;
    return text.length === 0 && doc.body.children.length === 0;
  }

  document.querySelectorAll('[data-mkt-live-embed]').forEach(function (embed) {
    const iframe = embed.querySelector('.mkt-live-frame-wrap iframe');
    if (!iframe) return;
    let settled = false;

    const giveUp = setTimeout(function () {
      if (!settled) { settled = true; showFallback(embed); }
    }, 4000);

    iframe.addEventListener('error', function () {
      if (settled) return;
      settled = true;
      clearTimeout(giveUp);
      showFallback(embed);
    });

    iframe.addEventListener('load', function () {
      if (settled) return;
      clearTimeout(giveUp);
      settled = true;
      try {
        if (looksBlank(iframe.contentDocument)) showFallback(embed);
      } catch (e) {
        // Cross-origin somehow — can't inspect it, so just trust the load.
      }
    });
  });
})();
